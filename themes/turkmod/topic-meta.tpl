<div class="ui-theme-topic-part ui-theme-topic-part--meta">
<section class="topic-section topic-details ui-section" aria-labelledby="content-info-heading">
<h2 id="content-info-heading">İçerik Bilgileri</h2>
<div class="topic-info-grid ui-grid">
{loop topic.info_rows}
<div class="topic-info-row"><i class="bi {item.icon}" aria-hidden="true"></i><span>{item.label}</span><strong data-info-full="{item.value}" data-info-value tabindex="0">{if item.url}<a href="{item.url}">{item.value}</a>{else}{item.value}{/if}</strong></div>
{/loop}
</div>
</section>
</div>
