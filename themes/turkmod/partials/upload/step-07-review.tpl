<section class="ui-panel upload-wizard-panel" data-step="7"{if upload.hide_inactive_panels} hidden{/if}>
<div class="upload-step-eyebrow">7. Adim</div>
<h2 class="upload-step-title">Kontrol ve Onaya Gonder</h2>
<p class="upload-step-copy">Son kontrolden sonra iceriginiz moderator onayina iletilir.</p>
<div class="public-alert-note ui-alert"><i class="bi bi-shield-check" aria-hidden="true"></i><div><strong>{if upload.is_edit}Revizyon onay akisi{else}Guvenli Icerik Politikasi{/if}</strong><br>{if upload.is_edit}Kaydettiginizde mod durumu otomatik olarak Onay Bekliyor yapilir.{else}Yuklediginiz icerikler moderator onayindan sonra yayina alinir.{/if}</div></div>
<div class="upload-review-list" aria-label="Onay oncesi hatirlatmalar">
<div><i class="bi bi-send-check" aria-hidden="true"></i><span>{if upload.is_edit}Bu islem mevcut modunuzu gunceller.{else}Icerik moderator onayina gonderilecek.{/if}</span></div>
<div><i class="bi bi-asterisk" aria-hidden="true"></i><span>Zorunlu alanlari, kapak ve galeri kurallarini kontrol edin.</span></div>
<div><i class="bi bi-link-45deg" aria-hidden="true"></i><span>Indirme linklerinin calistigindan emin olun.</span></div>
{if upload.block_duplicate_titles}<div><i class="bi bi-copy" aria-hidden="true"></i><span>Ayni baslikla tekrar gonderim sunucuda engellenir.</span></div>{/if}
</div>
{if upload.has_limits}
<div class="upload-limit-summary" aria-live="polite"><i class="bi bi-speedometer2" aria-hidden="true"></i><div><strong>Gonderim hakki</strong>{loop upload.limit_rows}<span>{item.label}: {item.text}</span>{/loop}</div></div>
{/if}
{if upload.show_profile_followup}
<div class="upload-profile-followup"><i class="bi bi-person-lines-fill" aria-hidden="true"></i><div><strong>Gonderdiginiz konuyu takip edin</strong><span>{upload.notice}</span></div>{if upload.show_profile_button}<a href="{upload.profile_topics_url}" class="upload-profile-followup-link">Konularima Git</a>{/if}</div>
{/if}
<div class="public-actions upload-final-actions"><button type="submit" class="btn-submit-mod"><i class="bi bi-send-check" aria-hidden="true"></i> {if upload.is_edit}Degisiklikleri Onaya Gonder{else}Onaya Gonder{/if}</button><a href="{upload.cancel_url}" class="btn-cancel-mod">Iptal Et</a></div>
</section>