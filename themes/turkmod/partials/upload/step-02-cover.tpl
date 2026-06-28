<section class="ui-panel upload-wizard-panel" data-step="2"{if upload.hide_inactive_panels} hidden{/if}>
<div class="upload-step-eyebrow">2. Adim</div>
<h2 class="upload-step-title">Kapak Gorseli</h2>
<p class="upload-step-copy">{if upload.is_edit}Mevcut kapagi koruyabilir veya yenisiyle degistirebilirsiniz.{else}Liste ve konu kartinda ilk gorunecek ana gorseli yukleyin.{/if}</p>
<div class="public-upload-grid mb-4 ui-grid">
<article class="ui-surface public-media-card">
<div class="public-media-head ui-panel__head"><div><h3><i class="bi bi-image" aria-hidden="true"></i> Kapak Gorseli</h3><p>{upload.cover_help_text} En dikkat cekici gorselinizi kapak yapin.</p></div><div class="public-pill"><i class="bi bi-star-fill" aria-hidden="true"></i> Ana Gorsel</div></div>
{if upload.has_cover_media}
<div class="topic-edit-existing-media">
{loop upload.cover_media}<div class="public-preview-item"><img src="{item.url}" alt="" width="160" height="96" loading="lazy" decoding="async"><label class="topic-edit-keep-toggle"><input type="checkbox" name="keep_media[]" value="{item.id}" checked> Koru</label></div>{/loop}
</div>
{/if}
<div class="public-dropzone" data-uploader="cover">
<input type="file" name="topic_first_image_file" id="publicCoverInput" class="d-none" accept="{upload.accept_image_attr}"{if upload.cover_required} required{/if}>
<div class="public-dropzone-trigger" data-open-input="publicCoverInput"><i class="bi bi-cloud-arrow-up" aria-hidden="true"></i><strong>{if upload.is_edit}Yeni kapak gorseli secin{else}Kapak gorselini buraya surukleyin{/if}</strong><span>{if upload.is_edit}Degistirmek istemiyorsaniz mevcut gorseli korulu birakin{else}veya secmek icin tiklayin (PNG, JPG, WEBP){/if}</span></div>
<div class="upload-image-rules" aria-live="polite"><span><i class="bi bi-filetype-jpg" aria-hidden="true"></i> {upload.allowed_image_ext_text}</span><span><i class="bi bi-hdd" aria-hidden="true"></i> Maks. {upload.cover_max_size_mb} MB</span><span><i class="bi bi-aspect-ratio" aria-hidden="true"></i> {upload.image_dimension_rule_text}</span></div>
<div class="public-preview-grid ui-grid" id="publicCoverPreview"></div>
</div>
</article>
</div>
</section>
