<section class="ui-panel upload-wizard-panel" data-step="3"{if upload.hide_inactive_panels} hidden{/if}>
<div class="upload-step-eyebrow">3. Adım</div>
<h2 class="upload-step-title">Açıklama</h2>
<p class="upload-step-copy">Özellikleri, kurulumu ve uyumluluk bilgilerini açıkça anlatın.</p>
{if upload.has_moderation_note}<div class="public-alert-note topic-edit-moderation-note ui-alert"><i class="bi bi-chat-left-text" aria-hidden="true"></i><div><strong>Moderasyon notu</strong><br>{upload.moderation_note}</div></div>{/if}
<div class="mb-4">
<label class="form-label">Mod Açıklaması <span class="text-danger">*</span></label>
<textarea name="content" rows="8" class="ui-admin-form-control rich-editor" data-default-align="{upload.default_content_align}" data-min-length="{upload.min_content_length}" required placeholder="Modunuzun özelliklerini ve kurulum adımlarını yazın...">{upload.content_value}</textarea>
<div class="upload-field-rules"><span><i class="bi bi-card-text" aria-hidden="true"></i> En az {upload.min_content_length} karakter</span><span><i class="bi bi-text-{upload.default_content_align}" aria-hidden="true"></i> Varsayılan hizalama: {upload.default_content_align}</span></div>
<div class="upload-live-hint" data-live-hint="content" aria-live="polite"></div>
</div>
</section>