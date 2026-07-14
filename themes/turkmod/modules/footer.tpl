<footer class="site-footer">
<div class="container ui-container">
<div class="site-footer-bar">
<ul class="nav site-footer-nav lh-1">
{loop footer_nav_items}
<li class="nav-item"><a class="nav-link" href="{item.url}">{item.label}</a></li>
{/loop}
</ul>
<p class="site-footer-copy mb-0">&copy; {current_year}. <a href="{base_url}/index.php" class="site-footer-brand-link">{site_name}</a> - Tüm hakları saklıdır.</p>
</div>
</div>
</footer>
