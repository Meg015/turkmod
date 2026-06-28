<section class="ui-panel upload-wizard-panel" data-step="4"{if upload.hide_inactive_panels} hidden{/if}>
<div class="upload-step-eyebrow">4. Adim</div>
<h2 class="upload-step-title">Galeri ve Video</h2>
<p class="upload-step-copy">{if upload.is_edit}Mevcut galeri gorsellerini koruyabilir, kaldirabilir ve yeni gorseller ekleyebilirsiniz.{else}En fazla {upload.max_images} gorsel yukleyin; varsa tanitim videosu baglantisini ekleyin.{/if}</p>
<div class="public-upload-grid mb-4 ui-grid">
<article class="ui-surface public-media-card">
<div class="public-media-head ui-panel__head"><div><h3><i class="bi bi-images" aria-hidden="true"></i> Mod Galerisi</h3><p>{upload.gallery_help_text} Oyuncular modunuzu daha yakindan tanisin.</p></div><div class="public-pill"><i class="bi bi-collection" aria-hidden="true"></i> Maks. {upload.max_images} Gorsel</div></div>
{if upload.has_gallery_media}
<div class="topic-edit-existing-media topic-edit-existing-gallery">
{loop upload.gallery_media}<div class="public-preview-item"><img src="{item.url}" alt="" width="160" height="96" loading="lazy" decoding="async"><label class="topic-edit-keep-toggle"><input type="checkbox" name="keep_media[]" value="{item.id}" checked> Koru</label></div>{/loop}
</div>
{/if}
<div class="public-dropzone" data-uploader="gallery">
<input type="file" name="topic_images_files[]" id="publicGalleryInput" class="d-none" accept="{upload.accept_image_attr}" multiple{if upload.gallery_required} required{/if}>
<div class="public-dropzone-trigger" data-open-input="publicGalleryInput"><i class="bi bi-images" aria-hidden="true"></i><strong>{if upload.is_edit}Yeni galeri gorselleri secin{else}Galeri resimlerini toplu yukleyin{/if}</strong><span>Birden cok gorseli secebilir veya surukleyebilirsiniz</span></div>
<div class="upload-image-rules" aria-live="polite"><span><i class="bi bi-filetype-jpg" aria-hidden="true"></i> {upload.allowed_image_ext_text}</span><span><i class="bi bi-hdd" aria-hidden="true"></i> Maks. {upload.gallery_max_size_mb} MB</span><span><i class="bi bi-aspect-ratio" aria-hidden="true"></i> {upload.image_dimension_rule_text}</span><span><i class="bi bi-collection" aria-hidden="true"></i> Maks. {upload.max_images} gorsel</span></div>
<div class="public-preview-grid ui-grid" id="publicGalleryPreview"></div>
</div>
{if upload.video_allowed}
<div class="mt-4 pt-4 upload-section-divider topic-edit-video-row ui-section">
<label class="form-label"><i class="bi bi-youtube text-danger" aria-hidden="true"></i> Video URL</label>
<input type="url" name="topic_video_url" class="ui-admin-form-control" value="{upload.video_value}" placeholder="Orn: https://www.youtube.com/watch?v=...">
<div class="upload-field-rules"><span><i class="bi bi-camera-video" aria-hidden="true"></i> Video URL aktif</span><span><i class="bi bi-globe2" aria-hidden="true"></i> {upload.allowed_video_hosts_text}</span></div>
<div class="upload-live-hint" data-live-hint="video" aria-live="polite"></div>
<div class="form-text mt-2"><i class="bi bi-info-circle" aria-hidden="true"></i> Tanitim videonuz varsa buraya ekleyin.</div>
</div>
{/if}
</article>
</div>
</section>
