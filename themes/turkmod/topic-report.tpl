<div class="ui-theme-topic-report">
<div class="topic-report-modal" id="topicReportModal" role="dialog" aria-modal="true" aria-labelledby="report-heading" hidden aria-hidden="true">
<div class="topic-report-backdrop" data-report-modal-close data-ui-modal-close></div>
<div class="topic-report-dialog ui-panel">
<div class="topic-report-header ui-panel__head">
<h2 id="report-heading"><i class="bi bi-flag" aria-hidden="true"></i> İçeriği Raporla</h2>
<button type="button" class="topic-report-close" data-report-modal-close data-ui-modal-close aria-label="Kapat"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
</div>
{if topic.report_logged_in}
<form class="topic-report-form" action="{topic.report_endpoint}" method="post">
<input type="hidden" name="_token" value="{topic.csrf_token}">
<input type="hidden" name="action" value="create">
<input type="hidden" name="topic_id" value="{topic.id}">
<div class="topic-report-grid ui-grid">
<label><span>Neden</span><select name="reason" required>{loop topic.report_reasons}<option value="{item.value}">{item.label}</option>{/loop}</select></label>
<label><span>Detay</span><textarea name="details" rows="3" maxlength="1000" placeholder="Ek bilgi varsa yazın"></textarea></label>
</div>
<button type="submit" class="topic-report-submit"><i class="bi bi-send" aria-hidden="true"></i> Rapor Gönder</button>
<div class="topic-report-feedback" aria-live="polite"></div>
</form>
{else}
<div class="topic-report-login"><i class="bi bi-shield-exclamation" aria-hidden="true"></i><span>Rapor göndermek için giriş yapmalısınız.</span><a href="{base_url}/giris">Giriş yap</a></div>
{/if}
</div>
</div>
</div>
