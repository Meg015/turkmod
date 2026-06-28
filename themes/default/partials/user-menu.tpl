<div class="ui-theme-theme-user-menu">
  {if logged_in}
    <a href="{profile_url}">{user_name}</a>
    <a href="{logout_url}">Cikis</a>
  {else}
    <a href="{login_url}">Giris</a>
    <a href="{register_url}">Kayit ol</a>
  {/if}
</div>
