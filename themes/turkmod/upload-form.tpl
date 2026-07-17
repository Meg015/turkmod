<div class="ui-container container pb-5 upload-topic-form-container {if upload.is_edit}topic-edit-page topic-edit-upload-page{/if}">
<section class="ui-section public-upload-shell">
<div class="ui-panel public-upload-card">
{if upload.is_edit}
<div class="public-alert-note public-alert-note-strong ui-alert">
<i class="bi bi-hourglass-split" aria-hidden="true"></i>
<div><strong>Yeniden onay gerekir</strong><br>Kaydettiginiz degisiklikler dogrudan yayina cikmaz; konu tekrar onaya gonderilir.</div>
</div>
{/if}
<div class="public-upload-body ui-panel__body">
<form id="uploadForm" method="post" action="{upload.form_action}" enctype="multipart/form-data"{if upload.is_edit} data-topic-edit-wizard{/if} data-lock-after-submit="{upload.lock_after_submit}" data-max-images="{upload.max_images}" data-cover-max-size-mb="{upload.cover_max_size_mb}" data-gallery-max-size-mb="{upload.gallery_max_size_mb}" data-attachment-max-size-mb="{upload.attachment_max_size_mb}" data-allowed-image-ext="{upload.allowed_image_ext}" data-image-min-width="{upload.image_min_width}" data-image-min-height="{upload.image_min_height}" data-image-max-width="{upload.image_max_width}" data-image-max-height="{upload.image_max_height}" data-min-title-length="{upload.min_title_length}" data-max-title-length="{upload.max_title_length}" data-min-content-length="{upload.min_content_length}" data-require-cover="{upload.require_cover_data}" data-require-gallery="{upload.require_gallery_data}" data-require-author="{upload.require_author_data}" data-require-version="{upload.require_version_data}" data-require-download-link="{upload.require_download_link_data}" data-allow-video-url="{upload.allow_video_url_data}" data-allowed-video-hosts="{upload.allowed_video_hosts_data}" data-upload-rate-limit="{upload.upload_rate_limit}" data-upload-rate-window="{upload.upload_rate_window}" data-block-duplicate-titles="{upload.block_duplicate_titles_data}" data-wizard-enabled="{if upload.wizard_enabled}1{else}0{/if}" data-allow-step-skip="{if upload.allow_step_skip}1{else}0{/if}">
<input type="hidden" name="_token" value="{upload.csrf_token}">
{if upload.has_submit_token}<input type="hidden" name="upload_submit_token" value="{upload.submit_token}">{/if}

<div class="upload-composer-layout upload-composer-layout--single ui-section">
<div class="upload-form-fields">
<div class="upload-wizard-progress {upload.wizard_class}" aria-label="Mod yukleme adimlari">
<button type="button" class="upload-wizard-step is-active" data-step-target="1"><span>1</span><strong>Temel Bilgiler</strong></button>
<button type="button" class="upload-wizard-step" data-step-target="2"><span>2</span><strong>Kapak Gorseli</strong></button>
<button type="button" class="upload-wizard-step" data-step-target="3"><span>3</span><strong>Aciklama</strong></button>
<button type="button" class="upload-wizard-step" data-step-target="4"><span>4</span><strong>Galeri ve Video</strong></button>
<button type="button" class="upload-wizard-step" data-step-target="5"><span>5</span><strong>Yapimci / Surum</strong></button>
<button type="button" class="upload-wizard-step" data-step-target="6"><span>6</span><strong>Indirme Kaynaklari</strong></button>
<button type="button" class="upload-wizard-step" data-step-target="7"><span>7</span><strong>Kontrol ve Onay</strong></button>
</div>

{include "partials/upload/step-01-basics.tpl"}

{include "partials/upload/step-02-cover.tpl"}

{include "partials/upload/step-03-description.tpl"}

{include "partials/upload/step-04-gallery.tpl"}

{include "partials/upload/step-05-author.tpl"}

{include "partials/upload/step-06-downloads.tpl"}

{include "partials/upload/step-07-review.tpl"}

<div class="upload-wizard-controls {upload.wizard_class}">
<button type="button" class="btn-cancel-mod" data-wizard-prev><i class="bi bi-arrow-left" aria-hidden="true"></i> Geri Don</button>
<button type="button" class="btn-submit-mod" data-wizard-next>Devam Et <i class="bi bi-arrow-right" aria-hidden="true"></i></button>
</div>
</div>

</div>

<div class="public-actions"><button type="submit" class="btn-submit-mod"><i class="bi bi-send-check" aria-hidden="true"></i> {if upload.is_edit}Degisiklikleri Onaya Gonder{else}Modu Onaya Sun{/if}</button><a href="{upload.cancel_url}" class="btn-cancel-mod">Iptal Et</a></div>
</form>
</div>
</div>
</section>
</div>
