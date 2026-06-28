<section class="ui-panel upload-wizard-panel" data-step="6"{if upload.hide_inactive_panels} hidden{/if}>
<div class="upload-step-eyebrow">6. Adim</div>
<h2 class="upload-step-title">Indirme Kaynaklari</h2>
<p class="upload-step-copy">Oyuncularin modu indirecegi kaynaklari ekleyin. Birden fazla ayna link kullanabilirsiniz.</p>
<div class="mb-4">
<label class="form-label"><i class="bi bi-link-45deg" aria-hidden="true"></i> Indirme Baglantilari</label>
<div class="form-text mb-3">Modu indirebilecekleri kaynaklari ekleyin.</div>
<div id="dlRows">
{loop upload.download_links}<div class="dl-row"><input type="text" name="dl_name[]" class="ui-admin-form-control w-25" placeholder="Kaynak Adi" value="{item.name}"><input type="url" name="dl_url[]" class="ui-admin-form-control flex-grow-1" placeholder="https://..." value="{item.url}"{if upload.download_required} required{/if}><button type="button" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" data-dl-remove title="Kaldir"><i class="bi bi-trash3" aria-hidden="true"></i></button></div>{/loop}
</div>
<button type="button" class="btn-add-link mt-2" data-add-dl-row><i class="bi bi-plus-circle" aria-hidden="true"></i> Yeni Baglanti Ekle</button>
<input type="hidden" name="topic_download_links" id="dlHidden">
<div class="upload-field-rules"><span><i class="bi bi-link-45deg" aria-hidden="true"></i> {upload.download_rule_text}</span></div>
<div class="upload-live-hint" data-live-hint="download" aria-live="polite"></div>
</div>
<div class="mb-4 d-none">
<label class="form-label">Mod Dosyasi (Opsiyonel)</label>
<input type="file" name="attachment" class="ui-admin-form-control" accept="{upload.attachment_accept}">
<div class="upload-field-rules"><span><i class="bi bi-archive" aria-hidden="true"></i> Maks. {upload.attachment_max_size_mb} MB</span></div>
<div class="upload-live-hint" data-live-hint="attachment" aria-live="polite"></div>
</div>
</section>