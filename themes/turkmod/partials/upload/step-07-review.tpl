<section class="ui-panel upload-wizard-panel" data-step="7"{if upload.hide_inactive_panels} hidden{/if}>
<div class="upload-step-eyebrow">7. Adım</div>
<h2 class="upload-step-title">Kontrol ve Onaya Gönder</h2>
<p class="upload-step-copy">Son kontrolden sonra içeriğiniz moderatör onayına iletilir.</p>
<div class="public-alert-note ui-alert"><i class="bi bi-shield-check" aria-hidden="true"></i><div><strong>{if upload.is_edit}Revizyon onay akışı{else}Güvenli İçerik Politikası{/if}</strong><br>{if upload.is_edit}Kaydettiğinizde güncellemeniz yeniden onaya gönderilir.{else}Yüklediğiniz içerikler moderatör onayından sonra yayınlanır.{/if}</div></div>
<div class="upload-review-list" aria-label="Onay öncesi hatırlatmalar">
<div><i class="bi bi-send-check" aria-hidden="true"></i><span>{if upload.is_edit}Bu işlem mevcut modunuzu günceller.{else}İçerik moderatör onayına gönderilecek.{/if}</span></div>
<div><i class="bi bi-asterisk" aria-hidden="true"></i><span>Zorunlu alanları, kapak ve galeri kurallarını kontrol edin.</span></div>
<div><i class="bi bi-link-45deg" aria-hidden="true"></i><span>İndirme bağlantılarının çalıştığından emin olun.</span></div>
{if upload.block_duplicate_titles}<div><i class="bi bi-copy" aria-hidden="true"></i><span>Aynı başlıkla tekrar gönderim engellenir.</span></div>{/if}
</div>
{if upload.has_limits}
<div class="upload-limit-summary" aria-live="polite"><i class="bi bi-speedometer2" aria-hidden="true"></i><div><strong>Gönderim hakkı</strong>{loop upload.limit_rows}<span>{item.label}: {item.text}</span>{/loop}</div></div>
{/if}
{if upload.show_profile_followup}
<div class="upload-profile-followup"><i class="bi bi-person-lines-fill" aria-hidden="true"></i><div><strong>Gönderdiğin konuyu takip et</strong><span>{upload.notice}</span></div>{if upload.show_profile_button}<a href="{upload.profile_topics_url}" class="upload-profile-followup-link">Konularıma Git</a>{/if}</div>
{/if}
<div class="public-actions upload-final-actions"><button type="submit" class="btn-submit-mod"><i class="bi bi-send-check" aria-hidden="true"></i> {if upload.is_edit}Değişiklikleri onaya gönder{else}Onaya Gönder{/if}</button><a href="{upload.cancel_url}" class="btn-cancel-mod">İptal et</a></div>
</section>