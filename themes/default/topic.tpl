<article class="ui-theme-theme-container ui-theme-theme-article" data-topic-view-id="{topic.id}" data-topic-view-url="{base_url}/api/track-view.php">
  <header class="ui-theme-theme-page-head">
    <span class="ui-theme-theme-eyebrow">{topic.category}</span>
    <h1>{topic.title}</h1>
    <p>{topic.excerpt}</p>
  </header>
  {if topic.image}
    <img class="ui-theme-theme-hero" src="{topic.image}" alt="{topic.title}" width="1200" height="675">
  {/if}
  <div class="ui-theme-theme-content">{content}</div>
</article>
