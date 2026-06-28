<section class="ui-panel upload-wizard-panel is-active" data-step="1">
<div class="upload-step-eyebrow">1. Adim</div>
<h2 class="upload-step-title">Temel Bilgiler</h2>
<p class="upload-step-copy">{if upload.is_edit}Mod basligini ve kategorisini guncelleyin.{else}Mod basligini net yazin ve icerigin gorunecegi kategoriyi secin.{/if}</p>
<div class="row mb-4">
<div class="col-md-8 mb-3 mb-md-0">
<label class="form-label">Mod Basligi <span class="text-danger">*</span></label>
<input type="text" name="title" class="ui-admin-form-control" required minlength="{upload.min_title_length}" maxlength="{upload.max_title_length}" value="{upload.title_value}" placeholder="Harika bir baslik dusunun...">
<div class="upload-field-rules"><span><i class="bi bi-type" aria-hidden="true"></i> {upload.min_title_length}-{upload.max_title_length} karakter</span>{if upload.block_duplicate_titles}<span><i class="bi bi-shield-check" aria-hidden="true"></i> Ayni baslik tekrar edilemez</span>{/if}</div>
<div class="upload-live-hint" data-live-hint="title" aria-live="polite"></div>
</div>
<div class="col-md-4">
<label class="form-label">Kategori <span class="text-danger">*</span></label>
<select name="category_id" class="form-select" required>
{loop upload.categories}<option value="{item.id}"{if item.selected} selected{/if}>{item.label}</option>{/loop}
</select>
<div class="upload-field-rules"><span><i class="bi bi-asterisk" aria-hidden="true"></i> Zorunlu secim</span></div>
</div>
</div>
</section>