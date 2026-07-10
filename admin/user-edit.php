<?php

declare(strict_types=1);

require_once __DIR__ . '/init.php';

// Admin yetkisi kontrolü
if (!isset($_SESSION["_auth_user_id"]) || !userHasPermission($pdo, (int) $_SESSION["_auth_user_id"], "users.edit")) {
    adminRenderForbiddenPage('Kullanıcı düzenlemek için gerekli izin hesabınıza tanımlanmamış.');
}

$pageTitle = "Kullanıcı Düzenle";
$userId = (int)($_GET['id'] ?? 0);

if ($userId <= 0) {
    header("Location: users.php");
    exit;
}

// Fetch user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: users.php");
    exit;
}

if (function_exists('usersDecorateUserWithPrimaryGroup')) {
    $user = usersDecorateUserWithPrimaryGroup($pdo, $user);
}

$displayUsername = (string) ($user['username'] ?? '');

$groups = function_exists('usersGetGroups') ? usersGetGroups($pdo, false) : [];
$validGroupIds = array_map('intval', array_column($groups, 'id'));

$permissionCatalog = function_exists('usersPermissionCatalog') ? usersPermissionCatalog() : [];
$permissionGroupLabels = [
    'admin' => 'Yönetim & Moderasyon Yetkileri',
    'public' => 'Üye & Genel Yetkiler',
];
$publicKeys = [
    'topics.view' => true,
    'topics.create' => true,
    'comments.view' => true,
    'comments.create' => true,
];
$permissionGroups = [
    'admin' => [],
    'public' => [],
];
foreach ($permissionCatalog as $permissionKey => $permissionLabel) {
    $category = isset($publicKeys[$permissionKey]) ? 'public' : 'admin';
    $permissionGroups[$category][(string)$permissionKey] = (string)$permissionLabel;
}

$userOverridesMap = [];
if (function_exists('usersGetUserPermissionOverrides')) {
    foreach (usersGetUserPermissionOverrides($pdo, $userId) as $ov) {
        $userOverridesMap[$ov['permission_key']] = (int)$ov['permission_value'];
    }
}

$message = "";
$messageType = "";
$formErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['_token'] ?? ($_POST['csrf_token'] ?? ''))) {
        $message = "Guvenlik dogrulamasi basarisiz.";
        $messageType = "error";
    } else {

    // Prepare data
    $rawUsername = sanitizeInput($_POST['username'] ?? '');
    $username = function_exists('usersValidateUsernameInput')
        ? usersValidateUsernameInput($rawUsername)
        : trim((string) $rawUsername);
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $groupId = (int)($_POST['group_id'] ?? ($user['group_id'] ?? 0));
    $status = $_POST['status'] ?? 'active';

    $avatar = sanitizeInput($_POST['avatar'] ?? '');
    $bio = sanitizeInput($_POST['bio'] ?? '');
    $website = sanitizeInput($_POST['website'] ?? '');
    $location = sanitizeInput($_POST['location'] ?? '');
    $github = sanitizeInput($_POST['social_github'] ?? '');
    $twitter = sanitizeInput($_POST['social_twitter'] ?? '');
    $discord = sanitizeInput($_POST['social_discord'] ?? '');

    $publicProfile = isset($_POST['public_profile']) ? 1 : 0;
    $publicTopics = isset($_POST['public_show_topics']) ? 1 : 0;
    $publicComments = isset($_POST['public_show_comments']) ? 1 : 0;
    $publicSocials = isset($_POST['public_show_socials']) ? 1 : 0;

    $isBanned = isset($_POST['is_banned']) ? 1 : 0;
    $banReason = sanitizeInput($_POST['ban_reason'] ?? '');

    // Kritik alanların güncelleme ÖNCESİ değerleri (audit + undo için)
    $prevGroupIds = function_exists('usersUserGroupIds') ? usersUserGroupIds($pdo, $userId) : [];
    $prevStatus = (string)($user['status'] ?? '');
    $prevBanned = (int)($user['is_banned'] ?? 0);
    $prevBanReason = (string)($user['ban_reason'] ?? '');
    $editReason = trim((string)($_POST['edit_reason'] ?? ''));

    if ($username === '') {
        $formErrors['username'] = "Kullanici adi 3-30 karakter olmali ve sadece harf, rakam, _ veya - icermelidir.";
    }
    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $formErrors['email'] = "Geçerli bir email adresi girin.";
    }
    if (!in_array($groupId, $validGroupIds, true)) {
        $formErrors['group_id'] = "Gecersiz grup.";
    }
    if ($userId === (int)($_SESSION["_auth_user_id"] ?? 0) && function_exists('usersGroupGrantsAdmin') && !usersGroupGrantsAdmin($pdo, $groupId)) {
        $formErrors['group_id'] = "Kendi admin grubunuzu kaldiramazsiniz.";
    }

    if ($formErrors !== []) {
        $message = reset($formErrors);
        $messageType = "error";
    } else {
        $updateSql = "UPDATE users SET
                        username = ?, email = ?, status = ?,
                        avatar = ?, bio = ?, website = ?, location = ?,
                        social_github = ?, social_twitter = ?, social_discord = ?,
                        public_profile = ?, public_show_topics = ?, public_show_comments = ?, public_show_socials = ?,
                        is_banned = ?, ban_reason = ?, updated_at = NOW()";

        $params = [
            $username, $email, $status,
            $avatar, $bio, $website, $location,
            $github, $twitter, $discord,
            $publicProfile, $publicTopics, $publicComments, $publicSocials,
            $isBanned, $banReason
        ];

        // Check if password is being changed
        $password = $_POST['password'] ?? '';
        if ($password !== '') {
            $policyError = validatePasswordPolicy($password, null, 'Şifre');
            if ($policyError !== '') {
                $formErrors['password'] = $policyError;
                $message = $policyError;
                $messageType = "error";
            } else {
                $updateSql .= ", password = ?, password_changed_at = NOW(), remember_token = NULL";
                $params[] = password_hash($password, PASSWORD_DEFAULT);
            }
        }

        if ($formErrors === []) {
            $updateSql .= " WHERE id = ?";
            $params[] = $userId;

            try {
                $updateStmt = $pdo->prepare($updateSql);
                $updateStmt->execute($params);

                $message = "Kullanıcı bilgileri başarıyla güncellendi.";
                $messageType = "success";

                if (function_exists('invalidatePublicContentCache')) {
                    invalidatePublicContentCache();
                }

                if (function_exists('usersSyncUserGroups')) {
                    $groupError = usersSyncUserGroups($pdo, $userId, [$groupId], (int)$_SESSION["_auth_user_id"], 'admin_user_edit_group_update');
                    if ($groupError !== '') {
                        $message = $groupError;
                        $messageType = "error";
                    }
                }

                if (function_exists('usersSaveUserPermissionOverrides')) {
                    $postedOverrides = $_POST['overrides'] ?? [];
                    $overridesToSave = [];
                    foreach ($postedOverrides as $key => $val) {
                        if ($val === 'grant') {
                            $overridesToSave[$key] = 1;
                        } elseif ($val === 'deny') {
                            $overridesToSave[$key] = 0;
                        }
                    }
                    usersSaveUserPermissionOverrides($pdo, $userId, $overridesToSave, (int)$_SESSION["_auth_user_id"], $editReason ?: 'Kullanıcı düzenleme formu');
                }

                // Kritik alan değişimlerini merkezi audit log'a yaz (geri-alınabilir)
                if (function_exists('adminLogAction')) {
                    $auditReason = $editReason !== '' ? $editReason : 'Kullanıcı düzenleme formu';
                    if (!in_array($groupId, $prevGroupIds, true) || count($prevGroupIds) !== 1) {
                    adminAuditLogger()->logAction($pdo, 'group_change', 'user', $userId, $auditReason,
                        ['group_ids' => $prevGroupIds], ['group_id' => $groupId], true);
                    }
                    if ($status !== $prevStatus) {
                    adminAuditLogger()->logAction($pdo, 'status_change', 'user', $userId, $auditReason,
                        ['status' => $prevStatus], ['status' => $status], true);
                    }
                    if ($isBanned !== $prevBanned) {
                        adminAuditLogger()->logAction($pdo, $isBanned === 1 ? 'ban' : 'unban', 'user', $userId,
                            $isBanned === 1 ? ($banReason ?: $auditReason) : $auditReason,
                            ['is_banned' => $prevBanned, 'ban_reason' => $prevBanReason],
                            ['is_banned' => $isBanned, 'ban_reason' => $banReason], true);
                    }
                }

                $profileOld = [];
                $profileNew = [];
                $profileFieldMap = [
                    'username' => $username,
                    'email' => $email,
                    'avatar' => $avatar,
                    'bio' => $bio,
                    'website' => $website,
                    'location' => $location,
                    'social_github' => $github,
                    'social_twitter' => $twitter,
                    'social_discord' => $discord,
                    'public_profile' => $publicProfile,
                    'public_show_topics' => $publicTopics,
                    'public_show_comments' => $publicComments,
                    'public_show_socials' => $publicSocials,
                ];
                foreach ($profileFieldMap as $field => $newValue) {
                    $oldValue = $user[$field] ?? null;
                    if ((string) $oldValue !== (string) $newValue) {
                        $profileOld[$field] = $oldValue;
                        $profileNew[$field] = $newValue;
                    }
                }
                if ($password !== '') {
                    $profileOld['password_changed'] = false;
                    $profileNew['password_changed'] = true;
                }
                if ($profileNew !== [] && function_exists('adminAuditLogger')) {
                    $auditReason = $editReason !== '' ? $editReason : 'Kullanıcı düzenleme formu';
                    adminAuditLogger()->logAction($pdo, 'admin_user_updated', 'user', $userId, $auditReason, $profileOld, $profileNew, false);
                }
                // Refresh user data
                $stmt->execute([$userId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($user && function_exists('usersDecorateUserWithPrimaryGroup')) {
                    $user = usersDecorateUserWithPrimaryGroup($pdo, $user);
                }
            } catch (PDOException $e) {
                // Handle duplicate email error
                if ($e->getCode() == 23000) {
                    $message = "Bu email adresi veya kullanici adi baska bir kullanici tarafindan kullaniliyor.";
                    $messageType = "error";
                } else {
                    $message = 'Bir hata oluştu: ' . safeErrorMessage($e);
                    $messageType = "error";
                }
            }
        }
    }
    }
}

$displayUsername = (string) ($user['username'] ?? $displayUsername);

require_once __DIR__ . "/header.php";
?>

<div class="ui-admin-container ui-container">
    <div class="ui-admin-page-hero">
        <div>
            <h1 class="ui-admin-page-title"><i class="bi bi-person-lines-fill"></i> Kullanıcı Düzenle: <?= htmlspecialchars($displayUsername) ?></h1>
            <p class="ui-admin-page-subtitle">Kullanıcı profili, grupları, sosyal bağlantıları ve gizlilik ayarlarını yönetin.</p>
        </div>
        <div class="ui-admin-hero-actions">
            <a href="users.php" class="ui-admin-btn ui-admin-btn-secondary"><i class="bi bi-arrow-left"></i> Kullanıcılara Dön</a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="ui-admin-alert ui-admin-alert-<?= $messageType ?> ui-alert">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <form method="post" action="user-edit.php?id=<?= $userId ?>" class="ui-admin-form ui-admin-grid-editor ui-grid">
        <?= csrf_field() ?>

        <div class="ui-admin-column-stack">
            <!-- Temel Bilgiler -->
            <div class="ui-admin-premium-card ui-card">
                <div class="ui-admin-card-header ui-panel__head ui-card">
                    <h3 class="ui-admin-card-title ui-card"><i class="bi bi-info-circle"></i> Temel Bilgiler</h3>
                </div>
                <div class="ui-admin-card-body ui-admin-card-body-stack ui-panel__body ui-card">
                    <div class="ui-admin-form-group">
                        <label class="ui-admin-form-label">Kullanıcı Adı <span class="ui-admin-required">*</span></label>
                        <input type="text" name="username" class="ui-admin-input" value="<?= htmlspecialchars((string) ($user['username'] ?? '')) ?>" required minlength="3" maxlength="30" pattern="[A-Za-z0-9_-]{3,30}" aria-invalid="<?= isset($formErrors['username']) ? 'true' : 'false' ?>" aria-describedby="user-edit-username-error">
                        <?php if (isset($formErrors['username'])): ?><small class="ui-admin-form-error" id="user-edit-username-error"><?= htmlspecialchars($formErrors['username']) ?></small><?php endif; ?>
                    </div>

                    <div class="ui-admin-form-group">
                        <label class="ui-admin-form-label">Email Adresi <span class="ui-admin-required">*</span></label>
                        <input type="email" name="email" class="ui-admin-input" value="<?= htmlspecialchars($user['email']) ?>" required aria-invalid="<?= isset($formErrors['email']) ? 'true' : 'false' ?>" aria-describedby="user-edit-email-error">
                        <?php if (isset($formErrors['email'])): ?><small class="ui-admin-form-error" id="user-edit-email-error"><?= htmlspecialchars($formErrors['email']) ?></small><?php endif; ?>
                    </div>

                    <div class="ui-admin-form-group">
                        <label class="ui-admin-form-label">Yeni Şifre</label>
                        <input type="password" name="password" class="ui-admin-input" placeholder="Değiştirmek istemiyorsanız boş bırakın" aria-invalid="<?= isset($formErrors['password']) ? 'true' : 'false' ?>" aria-describedby="user-edit-password-help user-edit-password-error">
                        <small class="ui-admin-form-help" id="user-edit-password-help"><?= htmlspecialchars(passwordPolicyHint()) ?></small>
                        <?php if (isset($formErrors['password'])): ?><small class="ui-admin-form-error" id="user-edit-password-error"><?= htmlspecialchars($formErrors['password']) ?></small><?php endif; ?>
                    </div>

                    <div class="ui-admin-form-group">
                        <label class="ui-admin-form-label">Avatar URL</label>
                        <input type="text" name="avatar" class="ui-admin-input" value="<?= htmlspecialchars($user['avatar'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <!-- Profil ve Sosyal -->
            <div class="ui-admin-premium-card ui-card">
                <div class="ui-admin-card-header ui-panel__head ui-card">
                    <h3 class="ui-admin-card-title ui-card"><i class="bi bi-person-badge"></i> Profil ve Sosyal</h3>
                </div>
                <div class="ui-admin-card-body ui-admin-card-body-stack ui-panel__body ui-card">
                    <div class="ui-admin-form-group">
                        <label class="ui-admin-form-label">Biyografi</label>
                        <textarea name="bio" class="ui-admin-input" rows="3"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                    </div>

                    <div class="ui-admin-form-grid-2 ui-grid">
                        <div class="ui-admin-form-group">
                            <label class="ui-admin-form-label">Konum</label>
                            <input type="text" name="location" class="ui-admin-input" value="<?= htmlspecialchars($user['location'] ?? '') ?>">
                        </div>
                        <div class="ui-admin-form-group">
                            <label class="ui-admin-form-label">Website</label>
                            <input type="url" name="website" class="ui-admin-input" value="<?= htmlspecialchars($user['website'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="ui-admin-form-group">
                        <label class="ui-admin-form-label"><i class="bi bi-github"></i> GitHub</label>
                        <input type="text" name="social_github" class="ui-admin-input" value="<?= htmlspecialchars($user['social_github'] ?? '') ?>">
                    </div>

                    <div class="ui-admin-form-group">
                        <label class="ui-admin-form-label"><i class="bi bi-twitter-x"></i> Twitter</label>
                        <input type="text" name="social_twitter" class="ui-admin-input" value="<?= htmlspecialchars($user['social_twitter'] ?? '') ?>">
                    </div>

                    <div class="ui-admin-form-group">
                        <label class="ui-admin-form-label"><i class="bi bi-discord"></i> Discord</label>
                        <input type="text" name="social_discord" class="ui-admin-input" value="<?= htmlspecialchars($user['social_discord'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="ui-admin-column-stack">
            <!-- Grup ve Durum -->
            <div class="ui-admin-premium-card ui-card">
                <div class="ui-admin-card-header ui-panel__head ui-card">
                    <h3 class="ui-admin-card-title ui-card"><i class="bi bi-shield-lock"></i> Grup ve Durum</h3>
                </div>
                <div class="ui-admin-card-body ui-admin-card-body-stack ui-panel__body ui-card">
                    <div class="ui-admin-form-group">
                        <label class="ui-admin-form-label">Kullanıcı Grubu</label>
                        <select name="group_id" class="ui-admin-select">
                            <?php foreach ($groups as $group): ?>
                                <option value="<?= (int) $group['id'] ?>" <?= (int) $group['id'] === (int) ($user['group_id'] ?? 0) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string) $group['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="ui-admin-form-group">
                        <label class="ui-admin-form-label">Hesap Durumu</label>
                        <select name="status" class="ui-admin-select">
                            <option value="active" <?= $user['status'] === 'active' ? 'selected' : '' ?>>Aktif</option>
                            <option value="inactive" <?= $user['status'] === 'inactive' ? 'selected' : '' ?>>Pasif / Onay Bekliyor</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Bireysel Yetki Ezme (Overrides) -->
            <div class="ui-admin-premium-card ui-card ui-admin-mt-md">
                <div class="ui-admin-card-header ui-panel__head ui-card">
                    <h3 class="ui-admin-card-title ui-card"><i class="bi bi-shield-lock"></i> Özel Yetki Tanımlamaları</h3>
                </div>
                <div class="ui-admin-card-body ui-admin-card-body-stack ui-panel__body ui-card">
                    <p class="ui-admin-form-help ui-admin-mb-md">Gruptan gelen yetkileri bu kullanıcı için ezebilirsiniz (İzin ver/Engelle).</p>

                    <div class="user-permission-override-groups">
                        <?php foreach ($permissionGroups as $prefix => $items): ?>
                            <details class="ui-admin-mb-sm ui-card" style="padding: 8px 12px; background: rgba(0,0,0,0.02);">
                                <summary style="font-weight: 600; cursor: pointer;">
                                    <?= htmlspecialchars($permissionGroupLabels[$prefix] ?? ucfirst($prefix)) ?>
                                </summary>
                                <div style="margin-top: 8px; display: flex; flex-direction: column; gap: 8px;">
                                    <?php foreach ($items as $permissionKey => $permissionLabel): ?>
                                        <?php
                                        $currentOverride = $userOverridesMap[$permissionKey] ?? null;
                                        $selectedVal = $currentOverride === null ? 'default' : ($currentOverride === 1 ? 'grant' : 'deny');
                                        ?>
                                        <div style="display: flex; align-items: center; justify-content: space-between; padding: 4px 0; border-bottom: 1px dashed rgba(0,0,0,0.1);">
                                            <span style="font-size: 0.85rem; display: flex; flex-direction: column;">
                                                <strong><?= htmlspecialchars($permissionLabel) ?></strong>
                                                <code style="font-size: 0.7rem; opacity: 0.7;"><?= htmlspecialchars($permissionKey) ?></code>
                                            </span>
                                            <select name="overrides[<?= htmlspecialchars($permissionKey) ?>]" class="ui-admin-select" style="width: auto; padding: 2px 6px; font-size: 0.8rem; height: auto;">
                                                <option value="default" <?= $selectedVal === 'default' ? 'selected' : '' ?>>Gruptan Gelsin</option>
                                                <option value="grant" <?= $selectedVal === 'grant' ? 'selected' : '' ?>>İzin Ver</option>
                                                <option value="deny" <?= $selectedVal === 'deny' ? 'selected' : '' ?>>Engelle</option>
                                            </select>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </details>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Gizlilik Ayarları -->
            <div class="ui-admin-premium-card ui-card">
                <div class="ui-admin-card-header ui-panel__head ui-card">
                    <h3 class="ui-admin-card-title ui-card"><i class="bi bi-eye"></i> Gizlilik Ayarları</h3>
                </div>
                <div class="ui-admin-card-body ui-admin-card-body-stack ui-panel__body ui-card">
                    <label class="ui-admin-form-checkbox">
                        <input type="checkbox" name="public_profile" <?= $user['public_profile'] ? 'checked' : '' ?>>
                        <span>Genel Profil Görünürlüğü (Profili Herkese Açık Yap)</span>
                    </label>
                    <label class="ui-admin-form-checkbox">
                        <input type="checkbox" name="public_show_topics" <?= $user['public_show_topics'] ? 'checked' : '' ?>>
                        <span>Kullanıcının Konularını Profilinde Göster</span>
                    </label>
                    <label class="ui-admin-form-checkbox">
                        <input type="checkbox" name="public_show_comments" <?= $user['public_show_comments'] ? 'checked' : '' ?>>
                        <span>Kullanıcının Yorumlarını Profilinde Göster</span>
                    </label>
                    <label class="ui-admin-form-checkbox">
                        <input type="checkbox" name="public_show_socials" <?= $user['public_show_socials'] ? 'checked' : '' ?>>
                        <span>Sosyal Medya Linklerini Profilinde Göster</span>
                    </label>
                </div>
            </div>

            <!-- Ban Yönetimi -->
            <div class="ui-admin-premium-card ui-admin-danger-card ui-card">
                <div class="ui-admin-card-header ui-panel__head ui-card">
                    <h3 class="ui-admin-card-title ui-admin-danger-text ui-card"><i class="bi bi-slash-circle"></i> Ban Yönetimi</h3>
                </div>
                <div class="ui-admin-card-body ui-admin-card-body-stack ui-panel__body ui-card">
                    <label class="ui-admin-form-checkbox">
                        <input type="checkbox" name="is_banned" <?= $user['is_banned'] ? 'checked' : '' ?>>
                        <span class="ui-admin-danger-strong">Bu Kullanıcıyı Banla</span>
                    </label>
                    <div class="ui-admin-form-group">
                        <label class="ui-admin-form-label">Ban Sebebi (Eğer banlıysa görünür)</label>
                        <textarea name="ban_reason" class="ui-admin-input" rows="2" placeholder="Kullanıcıya gösterilecek ban sebebi..."><?= htmlspecialchars($user['ban_reason'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <div class="ui-admin-form-group">
                <label class="ui-admin-form-label">Değişiklik Gerekçesi <span class="ui-admin-muted">(grup/durum/ban değişiminde denetim kaydına yazılır)</span></label>
                <input type="text" name="edit_reason" class="ui-admin-input" placeholder="Örn: Spam nedeniyle düşürüldü, talep üzerine yükseltildi...">
            </div>

            <div class="ui-admin-form-submit-row">
                <button type="submit" class="ui-admin-btn ui-admin-btn-primary ui-admin-btn-xl">
                    <i class="bi bi-save"></i> Değişiklikleri Kaydet
                </button>
            </div>
        </div>
    </form>
</div>
<?php require_once __DIR__ . "/footer.php"; ?>

