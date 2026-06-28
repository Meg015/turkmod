<header class="ui-theme-theme-header">
  <div class="ui-theme-theme-container ui-theme-theme-header-inner">
    <a class="ui-theme-theme-brand" href="{base_url}/index.php">
      <span class="ui-theme-theme-brand-mark">{site_initial}</span>
      <span>{site_name}</span>
    </a>
    <nav class="ui-theme-theme-nav" aria-label="Ana menu">
      {loop menu_items}
        <a href="{menu_item.url}">{if menu_item.icon}<i class="bi {menu_item.icon}" aria-hidden="true"></i>{/if}{menu_item.label}</a>
      {/loop}
    </nav>
    <div class="ui-theme-theme-actions">
      {if logged_in}
        <a href="{profile_url}">{user_name}</a>
      {else}
        <a href="{login_url}">Giris</a>
        <a class="ui-theme-theme-button" href="{register_url}">Kayit ol</a>
      {/if}
    </div>
  </div>
</header>
