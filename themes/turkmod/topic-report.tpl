<div class="ui-theme-topic-report">
<div class="topic-report-modal" id="topicReportModal" role="dialog" aria-modal="true" aria-labelledby="report-heading" aria-describedby="report-description" hidden aria-hidden="true">
<div class="topic-report-backdrop" data-report-modal-close data-ui-modal-close></div>
<div class="topic-report-dialog ui-panel">
<div class="topic-report-header ui-panel__head">
<div class="topic-report-heading">
<span class="topic-report-heading-icon" aria-hidden="true"><i class="bi bi-shield-exclamation"></i></span>
<div class="topic-report-titleblock">
<span class="topic-report-kicker">Moderasyon bildirimi</span>
<h2 id="report-heading">Konuyu raporla</h2>
<p class="topic-report-lead" id="report-description">Sorunu kısa ve net biçimde moderasyon ekibine iletin.</p>
</div>
</div>
<button type="button" class="topic-report-close" data-report-modal-close data-ui-modal-close aria-label="Pencereyi kapat"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
</div>
<form class="topic-report-form" action="{topic.report_endpoint}" method="post">
<input type="hidden" name="_token" value="{topic.csrf_token}">
<input type="hidden" name="action" value="create">
<input type="hidden" name="topic_id" value="{topic.id}">
<div class="topic-report-summary">
<i class="bi bi-eye-slash" aria-hidden="true"></i>
<span>Raporunuz yalnızca moderasyon ekibi tarafından görülür. Üyelik bilgileriniz varsa alanlara otomatik eklenir.</span>
</div>
<div class="topic-report-grid topic-report-grid--identity ui-grid">
<label class="topic-report-field"><span>Ad soyad</span><input type="text" name="reporter_name" value="{topic.reporter_name}" placeholder="Adınız ve soyadınız" autocomplete="name" maxlength="255" required {if topic.report_is_member}readonly aria-readonly="true"{/if}></label>
<label class="topic-report-field"><span>E-posta</span><input type="email" name="reporter_email" value="{topic.reporter_email}" placeholder="ornek@eposta.com" autocomplete="email" maxlength="255" required {if topic.report_is_member}readonly aria-readonly="true"{/if}></label>
<label class="topic-report-field topic-report-field--full"><span>Rapor nedeni</span><select name="reason" required>{loop topic.report_reasons}<option value="{item.value}">{item.label}</option>{/loop}</select></label>
<label class="topic-report-field topic-report-field--full"><span>Açıklama <small>(isteğe bağlı)</small></span><textarea name="details" rows="3" maxlength="1000" placeholder="Sorunu anlamamıza yardımcı olacak kısa bir açıklama yazın"></textarea></label>
</div>
<div class="topic-report-actions">
<span class="topic-report-privacy"><i class="bi bi-info-circle" aria-hidden="true"></i> Gereksiz raporlar inceleme süresini uzatabilir.</span>
<button type="submit" class="topic-report-submit" data-loading-label="Gönderiliyor..."><i class="bi bi-flag" aria-hidden="true"></i> Raporu gönder</button>
</div>
<div class="topic-report-feedback" role="status" aria-live="polite" aria-atomic="true"></div>
</form>
</div>
</div>
</div>
