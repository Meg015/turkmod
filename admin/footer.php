            </main>
            <?php
            $adminFooterSettings = function_exists('getAdminSettings') && isset($pdo) ? getAdminSettings($pdo) : [];
            $adminFooterSiteName = trim((string) ($adminFooterSettings['site_name'] ?? ''));
            if ($adminFooterSiteName === '') {
                $adminFooterSiteName = 'İçerik Topic';
            }
            ?>
            <footer class="admin-footer ui-panel__foot">
                &copy; <?= date('Y') ?> <?= htmlspecialchars($adminFooterSiteName) ?> - Yönetim Paneli
            </footer>
        </div>
    </div>
    
    <?php 
    $adminSettingsForToast = function_exists('getAdminSettings') && isset($pdo) ? getAdminSettings($pdo) : [];
    $_tEnabled = ($adminSettingsForToast['toast_enabled'] ?? '1') === '1';
    $_tDuration = (int)($adminSettingsForToast['toast_duration'] ?? 5000);
    $_tPos = $adminSettingsForToast['toast_position'] ?? 'bottom-right';
    $_tTheme = $adminSettingsForToast['toast_theme'] ?? 'default';
    $_tAnim = $adminSettingsForToast['toast_animation'] ?? 'slide';
    $_tProgress = ($adminSettingsForToast['toast_progress_bar'] ?? '1') === '1' ? 'true' : 'false';
    $_tClose = ($adminSettingsForToast['toast_close_button'] ?? '1') === '1' ? 'true' : 'false';
    $_tMax = (int)($adminSettingsForToast['toast_max_visible'] ?? 5);
    $_tStack = $adminSettingsForToast['toast_stack_direction'] ?? 'down';
    $_tClick = ($adminSettingsForToast['toast_click_to_close'] ?? '1') === '1' ? 'true' : 'false';
    $_tPause = ($adminSettingsForToast['toast_pause_on_hover'] ?? '1') === '1' ? 'true' : 'false';
    $_tDurSuccess = (int)($adminSettingsForToast['toast_duration_success'] ?? 0);
    $_tDurError = (int)($adminSettingsForToast['toast_duration_error'] ?? 0);
    $_tDurWarning = (int)($adminSettingsForToast['toast_duration_warning'] ?? 0);
    
    $suppressAdminFooterToasts = !empty($suppressAdminFooterToasts);
    // Catch any flashes that might be floating around
    $fSuccess = $suppressAdminFooterToasts ? '' : ($successMsg ?? $_SESSION['_flash_success'] ?? $_SESSION['_flash']['success'] ?? '');
    $fError = $suppressAdminFooterToasts ? '' : ($errorMsg ?? $_SESSION['_flash_error'] ?? $_SESSION['_flash']['error'] ?? '');
    $fInfo = $suppressAdminFooterToasts ? '' : ($infoMsg ?? $_SESSION['_flash_info'] ?? $_SESSION['_flash']['info'] ?? '');
    $fWarning = $suppressAdminFooterToasts ? '' : ($warningMsg ?? $_SESSION['_flash_warning'] ?? $_SESSION['_flash']['warning'] ?? '');
    if (!$suppressAdminFooterToasts) {
        if (isset($_SESSION['_flash_success'])) unset($_SESSION['_flash_success']);
        if (isset($_SESSION['_flash_error'])) unset($_SESSION['_flash_error']);
        if (isset($_SESSION['_flash_info'])) unset($_SESSION['_flash_info']);
        if (isset($_SESSION['_flash_warning'])) unset($_SESSION['_flash_warning']);
        if (isset($_SESSION['_flash']['success'])) unset($_SESSION['_flash']['success']);
        if (isset($_SESSION['_flash']['error'])) unset($_SESSION['_flash']['error']);
        if (isset($_SESSION['_flash']['info'])) unset($_SESSION['_flash']['info']);
        if (isset($_SESSION['_flash']['warning'])) unset($_SESSION['_flash']['warning']);
    }
    // Flash mesaji varsa toast'i zorla aktif et
    if ((!$suppressAdminFooterToasts && ($fSuccess || $fError || $fInfo || $fWarning))) { $_tEnabled = true; }
    ?>
    <?php if ($_tEnabled): ?>
    <div class="topic-toast-container toast-pos-<?= htmlspecialchars($_tPos) ?> ui-panel__foot" id="toastContainer" aria-live="polite" aria-atomic="true"
         data-toast-duration="<?= $_tDuration ?>"
         data-toast-theme="<?= htmlspecialchars($_tTheme) ?>"
         data-toast-animation="<?= htmlspecialchars($_tAnim) ?>"
         data-toast-progress="<?= $_tProgress ?>"
         data-toast-close="<?= $_tClose ?>"
         data-toast-max="<?= $_tMax ?>"
         data-toast-stack="<?= htmlspecialchars($_tStack) ?>"
         data-toast-click-close="<?= $_tClick ?>"
         data-toast-pause-hover="<?= $_tPause ?>"
         data-toast-dur-success="<?= $_tDurSuccess ?>"
         data-toast-dur-error="<?= $_tDurError ?>"
         data-toast-dur-warning="<?= $_tDurWarning ?>"
         data-toast-success="<?= htmlspecialchars((string)$fSuccess) ?>"
         data-toast-error="<?= htmlspecialchars((string)$fError) ?>"
         data-toast-warning="<?= htmlspecialchars((string)$fWarning) ?>"
         data-toast-info="<?= htmlspecialchars((string)$fInfo) ?>"></div>
    <?php else: ?>
    <div class="topic-toast-container ui-admin-hidden ui-panel__foot" id="toastContainer"></div>
    <?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js" integrity="sha384-QUJ+ckWz1M+a7w0UfG1sEn4pPrbQwSxGm/1TIPyioqXBrwuT9l4f9gdHWLDLbVWI" crossorigin="anonymous"></script>
<script src="<?= asset_url('admin/assets/admin-shell.js', $baseUri) ?>"></script>
</body>
</html>
