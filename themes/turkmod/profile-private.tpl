<div class="ui-container container public-container public-content profile-page-shell profile-shell profile-private-shell ui-section" data-profile-page data-profile-active-tab="{profile.active_tab}">
{if profile.success}
<div class="ui-admin-alert ui-admin-alert-success ui-alert ui-alert--success" role="alert">{profile.success}<button type="button" class="ui-admin-alert-close" aria-label="Kapat"><i class="bi bi-x-lg" aria-hidden="true"></i></button></div>
{/if}
{if profile.error}
<div class="ui-admin-alert ui-admin-alert-danger ui-alert ui-alert--error" role="alert">{profile.error}<button type="button" class="ui-admin-alert-close" aria-label="Kapat"><i class="bi bi-x-lg" aria-hidden="true"></i></button></div>
{/if}
{if profile.followup_message}
<div class="profile-followup-panel ui-panel" role="status">
<i class="bi bi-send-check" aria-hidden="true"></i>
<div><strong>{profile.followup_title}</strong><span>{profile.followup_message}</span></div>
</div>
{/if}
{if profile.has_restrictions}
<section class="profile-restriction-panel ui-panel" aria-label="Aktif hesap kisitlamalari">
<div class="profile-restriction-panel-head ui-panel__head">
<span class="profile-restriction-icon"><i class="bi bi-shield-exclamation" aria-hidden="true"></i></span>
<div><strong>Hesabınızda aktif kısıtlama var</strong><span>İşlem yapmadan önce kapsam ve bitiş tarihini kontrol edin.</span></div>
<a href="{base_url}/ban-appeals.php" class="profile-restriction-appeal"><i class="bi bi-envelope" aria-hidden="true"></i> İtiraz</a>
</div>
<div class="profile-restriction-list">
{loop profile.restrictions}
<div class="profile-restriction-item">
<strong>{item.label}</strong>
<span>{item.expires_label}</span>
{if item.reason}<small>{item.reason}</small>{/if}
</div>
{/loop}
</div>
</section>
{/if}

<div class="profile-tabs">
{loop profile.tabs}
<a href="{item.url}" class="{item.class}"><i class="bi {item.icon} me-1" aria-hidden="true"></i>{item.label}</a>
{/loop}
</div>

<section class="profile-quick-access" aria-label="Profil hızlı erişim">
{loop profile.quick_links}
<a href="{item.url}" class="profile-quick-card ui-card">
<i class="bi {item.icon}" aria-hidden="true"></i>
<span><strong>{item.value}</strong> {item.label}</span>
</a>
{/loop}
</section>

{if profile.tab_overview}
<div class="profile-two-column-layout ui-section">
<div class="profile-main-content ui-section">
<div class="profile-section ui-card ui-section">
<div class="profile-section-title"><i class="bi bi-file-earmark-text" aria-hidden="true"></i>Son Konular</div>
{if profile.has_topics}
{loop profile.topics_preview}
<div class="profile-topic-item">
<div class="profile-stack-fill">
<a href="{item.url}" class="profile-topic-title">{item.title}</a>
<div class="profile-topic-meta">
<span><i class="bi bi-folder2" aria-hidden="true"></i> {item.category}</span>
<span><i class="bi bi-eye" aria-hidden="true"></i> {item.views}</span>
<span><i class="bi bi-calendar3" aria-hidden="true"></i> {item.date}</span>
</div>
</div>
</div>
{/loop}
{if profile.more_topics_url}<div class="profile-center-cta"><a href="{profile.more_topics_url}">Tümünü gör &rarr;</a></div>{/if}
{else}
<div class="profile-empty profile-empty-action ui-empty"><i class="bi bi-journal-x" aria-hidden="true"></i><p>Henüz konu oluşturmadınız.</p><a href="{upload_topic_url}">İlk içeriği yükle</a></div>
{/if}
</div>
<div class="profile-section ui-card ui-section">
<div class="profile-section-title"><i class="bi bi-chat-dots" aria-hidden="true"></i>Son Yorumlar</div>
{if profile.has_comments}
{loop profile.comments_preview}
<div class="profile-comment-item">
<div class="profile-mini-row">
<a href="{item.url}" class="profile-link-strong">{item.topic}</a>
<span class="profile-small-muted">{item.date_short}</span>
</div>
<div class="profile-comment-body ui-panel__body">{item.body}</div>
</div>
{/loop}
{else}
<div class="profile-empty profile-empty-action ui-empty"><i class="bi bi-chat-square-text" aria-hidden="true"></i><p>Henüz yorum yapmadınız.</p><a href="{base_url}/index.php">İçerikleri keşfet</a></div>
{/if}
</div>
<div class="profile-section ui-card ui-section">
<div class="profile-section-title"><i class="bi bi-clock-history" aria-hidden="true"></i>Son Aktivite</div>
{if profile.has_activity}
{loop profile.activity_preview}
<div class="profile-activity-item">
<div class="profile-activity-dot {item.tone}"></div>
<div class="profile-stack-fill">
<strong>{if item.url}<a href="{item.url}" class="profile-activity-title-link">{item.title}</a>{else}{item.title}{/if}</strong>
{if item.detail}<span class="profile-muted"> - {item.detail}</span>{/if}
</div>
<span class="profile-date-muted">{item.date}</span>
</div>
{/loop}
{else}
<div class="profile-empty profile-empty-action ui-empty"><i class="bi bi-hourglass" aria-hidden="true"></i><p>Henüz aktivite yok.</p><a href="{base_url}/profile.php?tab=settings">Profili tamamla</a></div>
{/if}
</div>
</div>
{include "profile-sidebar.tpl"}
</div>
{/if}

{if profile.tab_topics}
<div class="profile-single-column">
<div class="profile-section ui-card ui-section">
<div class="profile-section-title"><i class="bi bi-file-earmark-text" aria-hidden="true"></i>Tüm Konularım ({profile.total_topics})</div>
<div class="profile-topic-status-filter" aria-label="Konu durum filtresi">
{loop profile.topic_status_options}
<a href="{item.url}" class="{item.class}"><i class="bi {item.icon}" aria-hidden="true"></i>{item.label}</a>
{/loop}
</div>
{if profile.has_pending_topics}
<div class="profile-section-title profile-section-title-tight"><i class="bi bi-pencil-square" aria-hidden="true"></i>Taslak ve Revizyon Durumu</div>
<div class="profile-pending-list">
{loop profile.pending_topics}
<div class="profile-pending-card ui-card">
<div class="profile-stack-fill">
<a href="{item.edit_url}" class="profile-pending-title">{item.title}</a>
<div class="profile-pending-meta"><span><i class="bi bi-folder2" aria-hidden="true"></i> {item.category}</span><span><i class="bi bi-clock-history" aria-hidden="true"></i> {item.updated}</span></div>
{if item.moderation_note}<div class="profile-moderation-note"><i class="bi bi-chat-left-text" aria-hidden="true"></i><div><strong>Moderasyon notu</strong><span>{item.moderation_note}</span></div></div>{/if}
{if item.needs_revision}<div class="profile-correction-tips"><strong><i class="bi bi-lightbulb" aria-hidden="true"></i> Düzenlemeden önce kontrol edin</strong><span>Moderasyon notunu karşılayın, çalışan indirme linki ekleyin, kapak/galeri görsellerini yenileyin ve uyumlu oyun sürümünü açık yazın.</span></div><div class="profile-resubmit-form"><a href="{item.edit_url}" class="profile-resubmit-action"><i class="bi bi-pencil-square" aria-hidden="true"></i>Düzenle ve tekrar gönder</a></div>{/if}
</div>
<span class="profile-pending-badge"><i class="bi {item.status_icon}" aria-hidden="true"></i> {item.status_label}</span>
</div>
{/loop}
</div>
{/if}
{if profile.has_topics}
{loop profile.topics}
<div class="profile-topic-item">
<div class="profile-stack-fill">
<a href="{item.url}" class="profile-topic-title">{item.title}</a>
<div class="profile-topic-meta"><span><i class="bi bi-folder2" aria-hidden="true"></i> {item.category}</span><span><i class="bi bi-eye" aria-hidden="true"></i> {item.views}</span><span><i class="bi bi-download" aria-hidden="true"></i> {item.downloads}</span><span><i class="bi bi-calendar3" aria-hidden="true"></i> {item.date}</span></div>
</div>
<a href="{item.edit_url}" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-outline" title="Düzenle"><i class="bi bi-pencil" aria-hidden="true"></i></a>
</div>
{/loop}
{if profile.topics_pagination_groups}{include "profile-pagination.tpl"}{/if}
{else}
<div class="profile-empty-cta ui-empty"><i class="bi bi-stars" aria-hidden="true"></i><h3>İlk konunu oluşturmaya hazır mısın?</h3><p>Henüz yayınlanmış bir konun görünmüyor. Yeni içerik ekleyerek profilini güçlendirebilirsin.</p><a href="{upload_topic_url}" class="ui-admin-btn ui-admin-btn-warning fw-bold"><i class="bi bi-plus-circle" aria-hidden="true"></i> İlk Konuyu Oluştur</a></div>
{/if}
</div>
</div>
{/if}

{if profile.tab_comments}
<div class="profile-single-column"><section class="ui-card profile-section ui-section">
<div class="profile-section-title"><i class="bi bi-chat-dots" aria-hidden="true"></i>Tüm Yorumlarım ({profile.total_comments})</div>
{if profile.has_comments}
{loop profile.comments}
<div class="profile-comment-item">
<div class="profile-mini-row-wrap"><a href="{item.url}" class="profile-link-strong"><i class="bi bi-chat-quote me-1" aria-hidden="true"></i>{item.topic}</a><span class="profile-small-muted">{item.date}</span></div>
<div class="profile-comment-body ui-panel__body">{item.body}</div>
</div>
{/loop}
{if profile.comments_pagination_groups}{include "profile-pagination.tpl"}{/if}
{else}
<div class="profile-empty profile-empty-action ui-empty"><i class="bi bi-chat-square-text" aria-hidden="true"></i><p>Henüz yorum yapmadınız.</p><a href="{base_url}/index.php">Yorum yapılacak içerik bul</a></div>
{/if}
</section></div>
{/if}

{if profile.tab_favorites}
<div class="profile-single-column"><div class="profile-section ui-card ui-section">
<div class="profile-section-title"><i class="bi bi-heart-fill" aria-hidden="true"></i>Favorilerim ({profile.total_favorites})</div>
<form method="post" action="{base_url}/profile.php?tab=favorites" class="collection-create-form">
<input type="hidden" name="_token" value="{profile.csrf_token}"><input type="hidden" name="action" value="create_collection">
<div><label for="collection_name">Yeni Koleksiyon</label><input type="text" id="collection_name" name="collection_name" maxlength="120" placeholder="Örn. En iyi kamyon modları" required></div>
<div><label for="collection_description">Not</label><input type="text" id="collection_description" name="collection_description" maxlength="500" placeholder="İsteğe bağlı kısa açıklama"></div>
<button type="submit"><i class="bi bi-plus-circle" aria-hidden="true"></i> Oluştur</button>
</form>
{if profile.has_collections}
<div class="collection-summary-grid ui-grid">
{loop profile.collections}
<div class="collection-summary-card">
<div><strong>{item.name}</strong><span>{item.count}</span>{if item.description}<small>{item.description}</small>{/if}<small>{item.visibility_label}</small></div>
<div class="collection-summary-actions">
<form method="post" action="{base_url}/profile.php?tab=favorites"><input type="hidden" name="_token" value="{profile.csrf_token}"><input type="hidden" name="action" value="update_collection_visibility"><input type="hidden" name="collection_id" value="{item.id}"><input type="hidden" name="visibility" value="{item.toggle_visibility}"><button type="submit" title="{item.toggle_title}"><i class="bi {item.toggle_icon}" aria-hidden="true"></i></button></form>
<form method="post" action="{base_url}/profile.php?tab=favorites" data-app-confirm="Bu koleksiyonu silmek istiyor musunuz?" data-app-confirm-title="Koleksiyon silinsin mi?" data-app-confirm-ok="Sil"><input type="hidden" name="_token" value="{profile.csrf_token}"><input type="hidden" name="action" value="delete_collection"><input type="hidden" name="collection_id" value="{item.id}"><button type="submit" title="Koleksiyonu sil"><i class="bi bi-trash" aria-hidden="true"></i></button></form>
</div>
</div>
{/loop}
</div>
{/if}
{if profile.has_favorites}
<div class="profile-favorites-list">
{loop profile.favorites}
<div class="profile-topic-item profile-topic-item-compact">
<div class="profile-stack-fill">
<a href="{item.url}" class="profile-topic-title">{item.title}</a>
<div class="profile-topic-meta"><span><i class="bi bi-folder2" aria-hidden="true"></i> <a href="{item.category_url}" class="profile-link-plain">{item.category}</a></span><span><i class="bi bi-person" aria-hidden="true"></i> {item.author}</span><span><i class="bi bi-eye" aria-hidden="true"></i> {item.views}</span><span><i class="bi bi-heart-fill" aria-hidden="true"></i> Favori Eklenme Tarihi: {item.date}</span></div>
</div>
<div class="favorite-actions">
<form method="post" action="{item.url}" class="ttb-favorite-form profile-favorite-remove-form m-0" data-topic-id="{item.id}"><input type="hidden" name="_token" value="{profile.csrf_token}"><input type="hidden" name="action" value="toggle_favorite"><button type="submit" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-outline" title="Favorilerden Kaldır"><i class="bi bi-heart-fill" aria-hidden="true"></i></button></form>
</div>
</div>
{/loop}
</div>
{if profile.favorites_pagination_groups}{include "profile-pagination.tpl"}{/if}
{else}
<div class="profile-empty-cta ui-empty"><i class="bi bi-heart" aria-hidden="true"></i><h3>Henüz favori içeriğiniz yok</h3><p>Beğendiğiniz konuları favorilere ekleyerek daha sonra kolayca erişebilirsiniz.</p><a href="{base_url}/index.php" class="ui-admin-btn ui-admin-btn-warning fw-bold"><i class="bi bi-compass me-1" aria-hidden="true"></i>İçerikleri Keşfet</a></div>
{/if}
</div></div>
{/if}

{if profile.tab_reports}
<div class="profile-single-column"><div class="profile-section ui-card ui-section">
<div class="profile-section-title"><i class="bi bi-flag" aria-hidden="true"></i>Gönderdiğim Raporlar ({profile.total_reports})</div>
<div class="profile-report-info"><i class="bi bi-bell" aria-hidden="true"></i><span>Rapor durumunuz değiştiğinde bildirim merkezinde ayrıca haber verilir.</span></div>
{if profile.has_reports}
<div class="profile-report-list">
{loop profile.reports}
<article class="profile-report-card ui-card">
<div><a href="{item.url}" class="profile-topic-title">{item.title}</a><div class="profile-topic-meta"><span><i class="bi bi-folder2" aria-hidden="true"></i> {item.category}</span><span><i class="bi bi-calendar3" aria-hidden="true"></i> {item.date}</span><span><i class="bi bi-flag" aria-hidden="true"></i> {item.reason}</span></div>{if item.details}<p>{item.details}</p>{/if}{if item.admin_note}<div class="profile-report-note"><strong>Admin notu:</strong> {item.admin_note}</div>{/if}<small class="profile-report-notify-note"><i class="bi bi-bell" aria-hidden="true"></i> Durum değişirse bildirim merkezine de düşer.</small></div>
<span class="{item.status_class}">{item.status_label}</span>
</article>
{/loop}
</div>
{if profile.reports_pagination_groups}{include "profile-pagination.tpl"}{/if}
{else}
<div class="profile-empty profile-empty-action ui-empty"><i class="bi bi-flag" aria-hidden="true"></i><p>Henüz rapor göndermediniz.</p><a href="{base_url}/index.php">İçerikleri incele</a></div>
{/if}
</div></div>
{/if}

{if profile.tab_activity}
<div class="profile-single-column"><div class="profile-section ui-card ui-section">
<div class="profile-section-title"><i class="bi bi-clock-history" aria-hidden="true"></i>Aktivite Geçmişi</div>
<div class="profile-topic-status-filter profile-activity-filter" aria-label="Aktivite filtreleri">
{loop profile.activity_filter_options}
<a href="{item.url}" class="{item.class}"><i class="bi {item.icon}" aria-hidden="true"></i><span>{item.label}</span><strong>{item.count}</strong></a>
{/loop}
</div>
{if profile.has_activity}
<div class="profile-activity-list">
{loop profile.activity}
<div class="profile-activity-item">
<div class="profile-activity-dot {item.tone}"></div>
<div class="profile-activity-main"><div class="profile-activity-title">{if item.url}<a href="{item.url}" class="profile-activity-title-link">{item.title}</a>{else}<span>{item.title}</span>{/if}<span class="profile-activity-badge"><i class="bi bi-lightning-charge" aria-hidden="true"></i>{item.badge}</span></div>{if item.detail}<div class="profile-activity-detail">{item.detail}</div>{/if}</div>
<div class="profile-activity-meta"><span>{item.date}</span><strong>{item.time}</strong></div>
</div>
{/loop}
</div>
{if profile.activity_pagination_groups}{include "profile-pagination.tpl"}{/if}
{else}
<div class="profile-empty profile-empty-action ui-empty"><i class="bi bi-hourglass" aria-hidden="true"></i><p>{profile.activity_empty_text}</p><a href="{profile.activity_empty_url}">{profile.activity_empty_action}</a></div>
{/if}
</div></div>
{/if}

{if profile.tab_settings}
<section class="ui-section profile-single-column profile-settings-section">
<div class="row g-4 mb-4">
<div class="col-lg-8"><section class="ui-card profile-section h-100 mb-0 ui-section">
<div class="profile-section-title"><i class="bi bi-person-gear" aria-hidden="true"></i>Profil Bilgileri</div>
<form method="post" action="{base_url}/profile.php?tab=settings">
<input type="hidden" name="_token" value="{profile.csrf_token}"><input type="hidden" name="action" value="update_profile">
<div class="profile-form-row"><div class="profile-form-group"><label for="pf_username">Kullanici Adi</label><input type="text" id="pf_username" name="username" value="{profile.username}" required minlength="3" maxlength="30" pattern="[A-Za-z0-9_-]{3,30}" autocomplete="username"></div><div class="profile-form-group"><label>E-posta <span class="profile-form-hint">(degistirilemez)</span></label><input type="email" value="{profile.email}" disabled class="profile-disabled-input"></div></div>
<div class="profile-form-group"><label for="pf_bio">Hakkımda</label><textarea id="pf_bio" name="bio" rows="3" maxlength="500" placeholder="Kendinizi kısaca tanıtın...">{profile.bio}</textarea></div>
<div class="profile-form-group"><label for="pf_location"><i class="bi bi-geo-alt me-1" aria-hidden="true"></i>Konum</label><input type="text" id="pf_location" name="location" value="{profile.location}" placeholder="İstanbul, Türkiye" maxlength="255"></div>
<div class="profile-section-title profile-section-title-offset"><i class="bi bi-share" aria-hidden="true"></i>Sosyal Bağlantılar</div>
<div class="profile-form-row"><div class="profile-form-group"><label><i class="bi bi-twitter-x me-1" aria-hidden="true"></i>Twitter / X</label><div class="profile-social-input"><span class="prefix">x.com/</span><input type="text" name="social_twitter" value="{profile.social_twitter}" placeholder="kullanici" maxlength="255"></div></div><div class="profile-form-group"><label><i class="bi bi-discord me-1" aria-hidden="true"></i>Discord</label><input type="text" name="social_discord" value="{profile.social_discord}" placeholder="kullanici#0000" maxlength="255"></div></div>
<div class="profile-form-row"><div class="profile-form-group"><label><i class="bi bi-globe me-1" aria-hidden="true"></i>Web Sitesi</label><input type="url" name="website" value="{profile.website}" placeholder="https://ornek.com" maxlength="255"></div><div class="profile-form-group"><label><i class="bi bi-github me-1" aria-hidden="true"></i>GitHub</label><div class="profile-social-input"><span class="prefix">github.com/</span><input type="text" name="social_github" value="{profile.social_github}" placeholder="kullanici" maxlength="255"></div></div></div>
<div class="profile-section-title profile-section-title-offset"><i class="bi bi-eye" aria-hidden="true"></i>Profil Gizliliği</div>
<div class="profile-privacy-grid ui-grid">{loop profile.privacy_options}<label class="profile-privacy-card ui-card" for="{item.id}"><span class="profile-privacy-icon"><i class="bi {item.icon}" aria-hidden="true"></i></span><span class="profile-privacy-copy"><strong>{item.title}</strong><small>{item.description}</small></span><span class="profile-privacy-switch"><input type="checkbox" role="switch" name="{item.field}" id="{item.id}" value="1" {item.checked}><span aria-hidden="true"></span></span></label>{/loop}</div>
<button type="submit" class="ui-admin-btn ui-admin-btn-warning fw-bold mt-2"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>Kaydet</button>
</form>
</section></div>
<div class="col-lg-4"><section class="ui-card profile-section ui-section">
<div class="profile-section-title"><i class="bi bi-camera" aria-hidden="true"></i>Profil Fotoğrafı</div>
<form method="post" action="{base_url}/profile.php?tab=settings" enctype="multipart/form-data" id="profileAvatarForm" class="profile-avatar-form">
<input type="hidden" name="_token" value="{profile.csrf_token}"><input type="hidden" name="action" value="upload_avatar">
<label class="profile-avatar-upload" for="avatar_input" data-avatar-upload>
<div class="profile-avatar-preview default-avatar" data-avatar-preview>{if profile.has_avatar}<img src="{profile.avatar}" alt="" width="64" height="64" data-avatar-img data-ui-avatar-img data-ui-avatar-fallback="{profile.avatar_fallback}">{else}<img src="{profile.avatar_fallback}" alt="" width="64" height="64" data-avatar-img data-ui-avatar-img data-ui-avatar-fallback="{profile.avatar_fallback}">{/if}</div>
<div class="profile-avatar-upload-copy"><div class="profile-upload-title">Fotoğraf Yükle</div><div class="profile-small-muted">JPG, PNG, WebP - Maks 2 MB</div><span class="profile-avatar-upload-action"><i class="bi bi-upload" aria-hidden="true"></i><span data-avatar-action-text>Dosya seç</span></span><span class="profile-avatar-selected" data-avatar-selected>Henüz yeni dosya seçilmedi.</span></div>
<input type="file" id="avatar_input" name="avatar" accept="image/jpeg,image/png,image/webp,image/gif" class="profile-file-input-hidden" data-avatar-input>
</label>
<div class="profile-avatar-actions"><button type="submit" class="ui-admin-btn ui-admin-btn-warning fw-bold" data-avatar-submit disabled><i class="bi bi-check-lg me-1" aria-hidden="true"></i>Kaydet</button><button type="button" class="ui-admin-btn ui-admin-btn-outline" data-avatar-reset hidden><i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>Temizle</button></div>
</form>
</section>
<section class="ui-card profile-section ui-section"><div class="profile-section-title"><i class="bi bi-info-circle" aria-hidden="true"></i>Hesap Bilgileri</div><div class="profile-info-list"><div><strong>Kayıt:</strong> {profile.created_at}</div><div><strong>Son Güncelleme:</strong> {profile.updated_at}</div><div><strong>Kullanıcı Grubu:</strong> <span class="badge profile-group-badge profile-group-badge--{profile.group_slug}">{profile.group}</span></div><div><strong>Durum:</strong> <span class="badge-modern {profile.status_badge_class}">{profile.status_label}</span></div><div><strong>Konu:</strong> {profile.total_topics} - <strong>Yorum:</strong> {profile.total_comments}</div></div></section>
</div>
</div>
</section>
{/if}

{if profile.tab_security}
<section class="ui-section profile-single-column profile-security-section">
<div class="row g-4 mb-4">
<div class="col-lg-6"><section class="ui-card profile-section h-100 mb-0 ui-section">
<div class="profile-section-title"><i class="bi bi-key" aria-hidden="true"></i>Şifre Değiştir</div>
{if profile.pw_success}<div class="ui-admin-alert ui-admin-alert-success profile-alert-sm ui-alert ui-alert--success">{profile.pw_success}</div>{/if}
{if profile.pw_error}<div class="ui-admin-alert ui-admin-alert-danger profile-alert-sm ui-alert ui-alert--error">{profile.pw_error}</div>{/if}
<form method="post" action="{base_url}/profile.php?tab=security" id="profilePasswordForm">
<input type="hidden" name="_token" value="{profile.csrf_token}"><input type="hidden" name="action" value="change_password">
<div class="profile-form-group"><label for="pw_current">Mevcut Şifre</label><input type="password" id="pw_current" name="current_password" required autocomplete="current-password"></div>
<div class="profile-form-group"><label for="pw_new">Yeni Şifre</label><input type="password" id="pw_new" name="new_password" required minlength="{profile.password_min_length}" autocomplete="new-password" data-password-strength data-password-confirm="#pw_confirm" data-password-require-uppercase="{profile.password_require_uppercase}" data-password-require-numbers="{profile.password_require_numbers}" data-password-require-special="{profile.password_require_special}"><small class="profile-form-hint">{profile.password_policy_hint}</small></div>
<div class="profile-form-group"><label for="pw_confirm">Yeni Şifre (Tekrar)</label><input type="password" id="pw_confirm" name="new_password_confirm" required minlength="{profile.password_min_length}" autocomplete="new-password"></div>
<button type="submit" class="ui-admin-btn ui-admin-btn-warning fw-bold"><i class="bi bi-shield-check me-1" aria-hidden="true"></i>Şifreyi Güncelle</button>
</form>
</section></div>
<div class="col-lg-6">
<section class="ui-card profile-section ui-section"><div class="profile-section-title"><i class="bi bi-shield-exclamation" aria-hidden="true"></i>Güvenlik Durumu</div><div class="profile-check-list">{loop profile.security_checks}<div class="profile-check-row"><i class="bi {item.icon} {item.class}" aria-hidden="true"></i><span>{item.label}</span></div>{/loop}</div></section>
<section class="ui-card profile-section ui-section"><div class="profile-section-title"><i class="bi bi-person-check" aria-hidden="true"></i>Aktif Oturum</div><div class="profile-session-card ui-card">{loop profile.session_info}<div><strong>{item.label}</strong><span>{item.value}</span></div>{/loop}</div><form method="post" action="{base_url}/profile.php?tab=security" class="profile-session-logout-form" data-app-confirm="Bu hesaba bağlı diğer tüm cihaz ve tarayıcılardaki oturumlar kapatılacak. Bu cihazdaki oturumunuz açık kalır. Devam etmek istiyor musunuz?" data-app-confirm-title="Tüm cihazlardan çıkış yapılsın mı?" data-app-confirm-ok="Evet, hepsinden çıkış yap" data-app-confirm-icon="bi-box-arrow-right"><input type="hidden" name="_token" value="{profile.csrf_token}"><input type="hidden" name="action" value="logout_all_devices"><button type="submit" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-danger w-100"><i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i>Tüm Cihazlardan Çıkış Yap</button><p class="profile-session-logout-hint"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>Hesabınızın açık olduğu diğer tüm cihazlardaki oturumlar sonlandırılır.</p></form></section>
<section class="ui-card profile-section ui-section"><div class="profile-section-title"><i class="bi bi-clock-history" aria-hidden="true"></i>Son Güvenlik Olayları</div>{if profile.has_security_events}{loop profile.security_events}<div class="profile-activity-item"><div class="profile-activity-dot {item.tone}"></div><div class="profile-activity-copy"><strong>{if item.url}<a href="{item.url}" class="profile-activity-title-link">{item.title}</a>{else}{item.title}{/if}</strong>{if item.detail}<span class="profile-muted">{item.detail}</span>{/if}</div><span class="profile-date-muted">{item.date}</span></div>{/loop}{else}<div class="profile-empty-mini ui-empty">Kayıt yok</div>{/if}</section>
</div>
</div>
</section>
{/if}
</div>


