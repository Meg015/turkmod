<footer class="site-footer">
<div class="container ui-container">
<div class="site-footer-bar">
<ul class="nav site-footer-nav lh-1">
{loop footer_nav_items}
<li class="nav-item"><a class="nav-link" href="{item.url}">{item.label}</a></li>
{/loop}
</ul>
<p class="site-footer-copy mb-0">{footer_copyright}</p>
</div>
</div>
</footer>
