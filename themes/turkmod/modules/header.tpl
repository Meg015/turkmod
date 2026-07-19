<header class="navbar-light fixed-top header-static bg-mode site-header">
<nav class="navbar navbar-expand-lg">
<div class="container ui-container">

<a class="header__logo" href="{base_url}/index.php" aria-label="{site_name}">
<span class="header__logo-mark" aria-hidden="true">
{if logo_url}
<img class="header__logo-img" src="{logo_url}" alt="" title="{site_name}">
{else}
<img class="header__logo-img" src="{base_url}/themes/turkmod/images/logo.svg" alt="" title="{site_name}">
{/if}
</span>
<span class="header__logo-text">{site_name}</span>
</a>

<button class="navbar-toggler ms-auto icon-md btn btn-light p-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse" aria-controls="navbarCollapse" aria-expanded="false" aria-label="Menu">
<span class="navbar-toggler-animation"><span></span><span></span><span></span></span>
</button>

<div class="collapse navbar-collapse" id="navbarCollapse">
<ul class="navbar-nav navbar-nav-scroll me-auto">
{loop menu_items}
{if item.is_category_menu}
<li class="nav-item dropdown"><a class="nav-link dropdown-toggle" href="{item.url}" id="{item.dropdown_id}" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">{if item.icon}<i class="bi {item.icon}" aria-hidden="true"></i> {/if}{item.label}</a>
<ul class="dropdown-menu cat-dropdown" aria-labelledby="{item.dropdown_id}">
<li><a class="dropdown-item cat-menu__all-link" href="{category_list_url}"><i class="bi bi-grid-3x3-gap" aria-hidden="true"></i><span>T&uuml;m Kategoriler</span></a></li>
{if has_header_category_menu}
<li class="cat-dropdown-tree"><ul class="nav nav-link-secondary flex-column fw-bold gap-1 cat-menu cat-menu--nested cat-menu--topbar" data-cat-menu>
{loop header_category_menu_items}
<li class="{item.item_class}" data-cat-item>
<div class="cat-menu__row"><a class="nav-link cat-menu__link {item.link_class}" href="{item.url}"{if item.is_active} aria-current="page"{/if}><span class="cat-menu__name"><i class="bi {item.icon_class}" aria-hidden="true"></i><span class="cat-menu__label">{item.name}</span></span><small class="cat-menu__count">{item.count}</small></a>{if item.has_children}<button class="cat-menu__toggle" type="button" data-cat-toggle aria-expanded="{item.expanded}" aria-controls="{item.panel_id}" aria-label="{item.toggle_label}"><i class="bi bi-chevron-down" aria-hidden="true"></i></button>{/if}</div>
{if item.has_children}
<ul id="{item.panel_id}" class="nav nav-link-secondary flex-column cat-menu__children" data-cat-panel{if item.is_collapsed} hidden{/if}>
{loop item.children}
<li class="{item.item_class}" data-cat-item>
<div class="cat-menu__row"><a class="nav-link cat-menu__link {item.link_class}" href="{item.url}"{if item.is_active} aria-current="page"{/if}><span class="cat-menu__name"><i class="bi {item.icon_class}" aria-hidden="true"></i><span class="cat-menu__label">{item.name}</span></span><small class="cat-menu__count">{item.count}</small></a>{if item.has_children}<button class="cat-menu__toggle" type="button" data-cat-toggle aria-expanded="{item.expanded}" aria-controls="{item.panel_id}" aria-label="{item.toggle_label}"><i class="bi bi-chevron-down" aria-hidden="true"></i></button>{/if}</div>
{if item.has_children}
<ul id="{item.panel_id}" class="nav nav-link-secondary flex-column cat-menu__children" data-cat-panel{if item.is_collapsed} hidden{/if}>
{loop item.children}
<li class="{item.item_class}" data-cat-item>
<div class="cat-menu__row"><a class="nav-link cat-menu__link {item.link_class}" href="{item.url}"{if item.is_active} aria-current="page"{/if}><span class="cat-menu__name"><i class="bi {item.icon_class}" aria-hidden="true"></i><span class="cat-menu__label">{item.name}</span></span><small class="cat-menu__count">{item.count}</small></a></div>
</li>
{/loop}
</ul>
{/if}
</li>
{/loop}
</ul>
{/if}
</li>
{/loop}
</ul></li>
{/if}
</ul>
</li>
{else}
<li class="nav-item"><a class="nav-link" href="{item.url}">{if item.icon}<i class="bi {item.icon}" aria-hidden="true"></i> {/if}<span>{item.label}</span></a></li>
{/if}
{/loop}
{if logged_in}
<li class="nav-item d-lg-none"><a class="nav-link" href="{profile_url}">Hesabim</a></li>
{else}
<li class="nav-item d-lg-none"><a class="nav-link" href="{login_url}">Giriş</a></li>
<li class="nav-item d-lg-none"><a class="nav-link" href="{register_url}">Kayıt Ol</a></li>
{/if}
</ul>
</div>

<div class="header-right d-flex align-items-center gap-2 ms-auto">
<form class="search topic-nav-search d-none d-md-flex" action="{base_url}/index.php" method="get" role="search">
<i class="bi bi-search" aria-hidden="true"></i>
<input type="search" name="q" value="{search_query}" placeholder="Sitede ara" aria-label="Sitede ara" autocomplete="off" data-search-autocomplete>
</form>

<button class="theme-toggle btn btn-light icon-md p-0 rounded-circle" title="Tema Degistir" type="button"><i class="bi bi-moon-stars-fill fs-6" id="theme-icon" aria-hidden="true"></i></button>

{if logged_in}
{if messages_enabled}
<div class="notif-dropdown" id="messagesDropdown" data-messages-dropdown data-messages-api="{messages_api_url}">
<button class="notif-toggle btn btn-light icon-md p-0 rounded-circle position-relative" type="button" aria-expanded="false" aria-label="Mesajlari ac" data-messages-toggle>
<i class="bi bi-chat-left-text-fill fs-6" aria-hidden="true"></i>
<span class="notif-badge" id="msgBadge">0</span>
</button>
<div class="notif-menu dropdown-menu dropdown-menu-end mt-2 shadow">
<div class="notif-menu-header d-flex justify-content-between p-3 border-bottom">
<span class="fw-bold">Mesajlar</span>
<a href="#" class="small text-decoration-none" data-messages-mark-all>Tumunu okundu isaretle</a>
</div>
<div class="notif-menu-list p-2" id="msgList">
<div class="notif-menu-state is-loading text-center p-3 text-muted">Yukleniyor...</div>
</div>
<div class="notif-menu-footer p-2 border-top text-center">
<a href="{messages_url}" class="small text-decoration-none">Tum mesajlari gor</a>
</div>
</div>
</div>
{/if}
{if notifications_enabled}
<div class="notif-dropdown" id="notifDropdown" data-notif-dropdown data-notif-api="{notifications_api_url}" data-notif-read-api="{notifications_read_api_url}" data-notif-url="{notifications_menu_url}">
<button class="notif-toggle btn btn-light icon-md p-0 rounded-circle position-relative" type="button" aria-expanded="false" aria-label="Bildirimleri ac" data-notif-toggle>
<i class="bi bi-bell-fill fs-6" aria-hidden="true"></i>
<span class="notif-badge{if notifications_has_unread} is-visible{/if}" id="notifBadge">{notifications_unread_count_text}</span>
</button>
<div class="notif-menu dropdown-menu dropdown-menu-end mt-2 shadow">
<div class="notif-menu-header d-flex justify-content-between p-3 border-bottom">
<span class="fw-bold">Bildirimler</span>
<a href="#" class="small text-decoration-none" data-notif-mark-all>Tumunu okundu isaretle</a>
</div>
<div class="notif-menu-list p-2" id="notifList">
<div class="notif-menu-state is-loading text-center p-3 text-muted">Yukleniyor...</div>
</div>
<div class="notif-menu-footer p-2 border-top text-center">
<a href="{notifications_url}" class="small text-decoration-none">Tum bildirimleri gor</a>
</div>
</div>
</div>
{/if}

{include "modules/user-menu.tpl"}
{if messages_enabled}<script src="{base_url}/assets/js/public-messages-menu.js" defer></script>{/if}
{else}
<div class="header-auth-actions" role="group" aria-label="Hesap işlemleri">
<a class="header-auth-link header-auth-link--secondary" href="{login_url}">
<i class="bi bi-box-arrow-right" aria-hidden="true"></i>
<span>Giriş</span>
</a>
<a class="header-auth-link header-auth-link--primary" href="{register_url}">
<i class="bi bi-person-circle" aria-hidden="true"></i>
<span>Kayıt</span>
</a>
</div>
{/if}
</div>
</div>
</nav>
</header>
