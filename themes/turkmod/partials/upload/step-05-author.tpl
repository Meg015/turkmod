<section class="ui-panel upload-wizard-panel" data-step="5"{if upload.hide_inactive_panels} hidden{/if}>
<div class="upload-step-eyebrow">5. Adim</div>
<h2 class="upload-step-title">Yapimci ve Oyun Surumu</h2>
<p class="upload-step-copy">Yapimci bilgisini ve modun hangi oyun surumuyle uyumlu oldugunu belirtin.</p>
<div class="row mb-4">
<div class="col-md-6 mb-3 mb-md-0"><label class="form-label">Mod Yapimcisi</label><input type="text" name="author_topic" class="ui-admin-form-control" value="{upload.author_value}" placeholder="Orn: SCS Software"{if upload.author_required} required{/if}><div class="upload-field-rules"><span><i class="bi bi-person-badge" aria-hidden="true"></i> {upload.author_rule_text}</span></div><div class="upload-live-hint" data-live-hint="author" aria-live="polite"></div></div>
<div class="col-md-6"><label class="form-label">Gerekli Oyun Surumu</label><input type="text" name="topic_version" class="ui-admin-form-control" value="{upload.version_value}" placeholder="Orn: 1.50"{if upload.version_required} required{/if}><div class="upload-field-rules"><span><i class="bi bi-controller" aria-hidden="true"></i> {upload.version_rule_text}</span></div><div class="upload-live-hint" data-live-hint="version" aria-live="polite"></div></div>
</div>
</section>