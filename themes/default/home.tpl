<section class="ui-theme-theme-container ui-theme-theme-stack">
  <div class="ui-theme-theme-page-head">
    <span class="ui-theme-theme-eyebrow">{site_name}</span>
    <h1>{page_title}</h1>
    <p>{page_description}</p>
  </div>
  <div class="ui-theme-theme-grid">
    {loop topics}
      <article class="ui-theme-theme-card">
        <a href="{topic.url}"><img src="{topic.image}" alt="{topic.title}" width="640" height="360"></a>
        <div class="ui-theme-theme-card-body">
          <span>{topic.category}</span>
          <h2><a href="{topic.url}">{topic.title}</a></h2>
          <p>{topic.excerpt}</p>
        </div>
      </article>
    {/loop}
  </div>
  {items_html}
</section>
