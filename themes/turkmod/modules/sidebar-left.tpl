<aside class="sidebar sidebar-left ui-theme-sidebar-builder ui-theme-sidebar-builder-left" data-ui-style-number="--ui-theme-sidebar-width:{sidebar_width}px">
<div class="d-flex align-items-center d-lg-none">
<button class="border-0 bg-transparent" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasSideNavbar" aria-controls="offcanvasSideNavbar">
<i class="btn btn-primary fw-bold bi bi-sliders"></i>
<span class="h6 mb-0 fw-bold d-lg-none ms-2">Menu</span>
</button>
</div>
<nav class="navbar navbar-expand-lg mx-0">
<div class="offcanvas offcanvas-start" tabindex="-1" id="offcanvasSideNavbar">
<div class="offcanvas-header"><button type="button" class="btn-close text-reset ms-auto" data-bs-dismiss="offcanvas" aria-label="Kapat"></button></div>
<div class="offcanvas-body d-block px-2 px-lg-0">
{loop sidebar_widgets}
{if sidebar_widget.is_navigation_menu}
<div class="{sidebar_widget.class} card ui-panel" data-ui-style-color="{sidebar_widget.color_style}">
<div class="card-header pb-0 border-0 ui-panel__head"><h5 class="card-title mb-0">{sidebar_widget.title}</h5></div>
<div class="card-body ui-panel__body">
<ul class="nav nav-link-secondary flex-column fw-bold gap-2">
{loop sidebar_widget.items}
<li class="nav-item"><a class="nav-link" href="{item.url}">{if item.icon}<i class="bi {item.icon} me-2" aria-hidden="true"></i>{/if}<span>{item.label}</span></a></li>
{/loop}
</ul>
</div>
</div>
{/if}

{if sidebar_widget.is_category_tree}
<section class="{sidebar_widget.class} cat-widget card ui-panel" data-ui-style-color="{sidebar_widget.color_style}" data-widget="categories">
<div class="cat-widget__header">
<span class="cat-widget__icon"><i class="bi {sidebar_widget.icon}" aria-hidden="true"></i></span>
<h2>{sidebar_widget.title}</h2>
</div>
<div class="cat-widget__body">
<ul class="nav nav-link-secondary flex-column fw-bold gap-2 cat-menu cat-menu--nested cat-menu--sidebar" data-cat-menu>
{loop sidebar_widget.category_items}
<li class="{item.item_class}" data-cat-item>
{if item.is_sidebar_root_toggle}
<button class="cat-menu__row cat-menu__trigger" type="button" data-cat-toggle aria-expanded="{item.expanded}" aria-controls="{item.panel_id}" aria-label="{item.toggle_label}"><span class="cat-menu__name"><i class="bi {item.icon_class}" aria-hidden="true"></i><span class="cat-menu__label">{item.name}</span></span><small class="cat-menu__count">{item.count}</small><i class="bi bi-arrow-right cat-menu__arrow" aria-hidden="true"></i></button>
{else}
{if item.is_sidebar_root_link}
<a class="nav-link cat-menu__row cat-menu__link cat-menu__link--parent cat-menu__root-link" href="{item.url}"{if item.is_active} aria-current="page"{/if}><span class="cat-menu__name"><i class="bi {item.icon_class}" aria-hidden="true"></i><span class="cat-menu__label">{item.name}</span></span><small class="cat-menu__count">{item.count}</small><span class="cat-menu__arrow-spacer" aria-hidden="true"></span></a>
{else}
<div class="cat-menu__row"><a class="nav-link cat-menu__link {item.link_class}" href="{item.url}"{if item.is_active} aria-current="page"{/if}><span class="cat-menu__name"><i class="bi {item.icon_class}" aria-hidden="true"></i><span class="cat-menu__label">{item.name}</span></span><small class="cat-menu__count">{item.count}</small></a>{if item.has_children}<button class="cat-menu__toggle" type="button" data-cat-toggle aria-expanded="{item.expanded}" aria-controls="{item.panel_id}" aria-label="{item.toggle_label}"><i class="bi bi-chevron-down" aria-hidden="true"></i></button>{/if}</div>
{/if}
{/if}
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
</ul>
</div>
</section>
{/if}

{if sidebar_widget.is_recent_comments}
<div class="{sidebar_widget.class} card ui-panel" data-ui-style-color="{sidebar_widget.color_style}"><div class="card-header pb-0 border-0 ui-panel__head"><h5 class="card-title mb-0">{sidebar_widget.title}</h5></div><div class="card-body ui-panel__body">{loop sidebar_widget.items}<a class="d-flex gap-3 mb-3 comment-item" href="{item.url}"><span class="avatar avatar-sm"><span class="avatar-img rounded-circle avatar-fallback default-avatar"><img src="{user_avatar_fallback}" alt="{item.author}" title="{item.author}" width="40" height="40" loading="lazy" data-ui-avatar-img data-ui-avatar-fallback="{user_avatar_fallback}"></span></span><span class="min-w-0"><strong class="d-block text-truncate">{item.author}</strong><small class="d-block text-secondary comment-excerpt">{item.excerpt}</small><small class="text-secondary">{item.date}</small></span></a>{/loop}</div></div>
{/if}

{if sidebar_widget.is_popular_topics}
<div class="{sidebar_widget.class} card ui-panel" data-ui-style-color="{sidebar_widget.color_style}"><div class="card-header pb-0 border-0 ui-panel__head"><h5 class="card-title mb-0">{sidebar_widget.title}</h5></div><div class="card-body ui-panel__body">{loop sidebar_widget.items}<a class="d-flex gap-3 mb-3 mini-topic" href="{item.url}">{if item.image}<img class="rounded" src="{item.image}" alt="{item.title}" title="{item.title}" width="54" height="54" loading="lazy" decoding="async">{/if}<span><strong>{item.title}</strong><small class="d-block text-secondary">{item.meta}</small></span></a>{/loop}<div class="d-grid mt-3 ui-grid"><a class="btn btn-sm btn-primary-soft" href="{base_url}/index.php?sort=popular">Tum Populerler</a></div></div></div>
{/if}

{if sidebar_widget.is_tag_cloud}
<div class="{sidebar_widget.class} card ui-panel" data-ui-style-color="{sidebar_widget.color_style}"><div class="card-header pb-0 border-0 ui-panel__head"><h5 class="card-title mb-0">{sidebar_widget.title}</h5></div><div class="card-body ui-panel__body"><div class="tagcloud d-flex flex-wrap gap-2">{loop sidebar_widget.items}<a class="tag" href="{item.url}">{item.label}</a>{/loop}</div></div></div>
{/if}

{if sidebar_widget.is_trending_tags}
<div class="{sidebar_widget.class} card ui-panel" data-ui-style-color="{sidebar_widget.color_style}"><div class="card-header pb-0 border-0 ui-panel__head"><h5 class="card-title mb-0"><i class="bi {sidebar_widget.icon} me-2"></i>{sidebar_widget.title}</h5></div><div class="card-body ui-panel__body"><div class="ui-theme-trend-tags">{loop sidebar_widget.items}<a href="{item.url}"><span>{item.label}</span><small>{item.count}</small></a>{/loop}</div></div></div>
{/if}

{if sidebar_widget.is_editor_picks}
<div class="{sidebar_widget.class} card ui-panel" data-ui-style-color="{sidebar_widget.color_style}"><div class="card-header pb-0 border-0 ui-panel__head"><h5 class="card-title mb-0"><i class="bi {sidebar_widget.icon} me-2"></i>{sidebar_widget.title}</h5></div><div class="card-body ui-panel__body">{loop sidebar_widget.items}<a class="ui-theme-topic-mini" href="{item.url}">{if item.image}<img src="{item.image}" alt="{item.title}" title="{item.title}" width="54" height="54" loading="lazy" decoding="async">{/if}<span><strong>{item.title}</strong><small>{item.meta}</small></span></a>{/loop}</div></div>
{/if}

{if sidebar_widget.is_latest_downloads}
<div class="{sidebar_widget.class} card ui-panel" data-ui-style-color="{sidebar_widget.color_style}"><div class="card-header pb-0 border-0 ui-panel__head"><h5 class="card-title mb-0"><i class="bi {sidebar_widget.icon} me-2"></i>{sidebar_widget.title}</h5></div><div class="card-body ui-panel__body">{loop sidebar_widget.items}<a class="ui-theme-topic-mini" href="{item.url}">{if item.image}<img src="{item.image}" alt="{item.title}" title="{item.title}" width="54" height="54" loading="lazy" decoding="async">{/if}<span><strong>{item.title}</strong><small>{item.meta}</small></span></a>{/loop}</div></div>
{/if}

{if sidebar_widget.is_related_content}
<div class="{sidebar_widget.class} card ui-panel" data-ui-style-color="{sidebar_widget.color_style}"><div class="card-header pb-0 border-0 ui-panel__head"><h5 class="card-title mb-0"><i class="bi {sidebar_widget.icon} me-2"></i>{sidebar_widget.title}</h5></div><div class="card-body ui-panel__body">{loop sidebar_widget.items}<a class="ui-theme-topic-mini" href="{item.url}">{if item.image}<img src="{item.image}" alt="{item.title}" title="{item.title}" width="54" height="54" loading="lazy" decoding="async">{/if}<span><strong>{item.title}</strong><small>{item.meta}</small></span></a>{/loop}</div></div>
{/if}

{if sidebar_widget.is_category_showcase}
<div class="{sidebar_widget.class} card ui-panel" data-ui-style-color="{sidebar_widget.color_style}"><div class="card-header pb-0 border-0 ui-panel__head"><h5 class="card-title mb-0"><i class="bi {sidebar_widget.icon} me-2"></i>{sidebar_widget.title}</h5></div><div class="card-body ui-panel__body"><div class="ui-theme-category-showcase">{loop sidebar_widget.items}<a href="{item.url}"><i class="bi {item.icon}"></i><span>{item.label}</span><small>{item.count}</small></a>{/loop}</div></div></div>
{/if}

{if sidebar_widget.is_announcement_band}
<div class="{sidebar_widget.class} card ui-theme-announcement-card ui-theme-announcement-{sidebar_widget.tone} ui-card ui-panel" data-ui-style-color="{sidebar_widget.color_style}"><div class="card-body ui-panel__body"><span class="ui-theme-announcement-icon"><i class="bi {sidebar_widget.icon}"></i></span><strong>{sidebar_widget.title}</strong><p>{sidebar_widget.message}</p>{if sidebar_widget.button_label}<a href="{sidebar_widget.button_url}">{sidebar_widget.button_label}</a>{/if}</div></div>
{/if}

{if sidebar_widget.is_community_activity}
<div class="{sidebar_widget.class} card ui-panel" data-ui-style-color="{sidebar_widget.color_style}"><div class="card-header pb-0 border-0 ui-panel__head"><h5 class="card-title mb-0"><i class="bi {sidebar_widget.icon} me-2"></i>{sidebar_widget.title}</h5></div><div class="card-body ui-panel__body"><div class="ui-theme-stat-mini-grid ui-theme-activity-grid ui-grid">{loop sidebar_widget.items}<div><i class="bi {item.icon}"></i><strong>{item.value}</strong><small>{item.label}</small></div>{/loop}</div></div></div>
{/if}

{if sidebar_widget.is_user_action}
<div class="{sidebar_widget.class} card ui-theme-user-action-card ui-card ui-panel" data-ui-style-color="{sidebar_widget.color_style}"><div class="card-body ui-panel__body"><span class="ui-theme-user-action-icon"><i class="bi {sidebar_widget.icon}"></i></span><strong>{sidebar_widget.action_title}</strong><p>{sidebar_widget.action_text}</p><div><a class="btn btn-sm btn-primary" href="{sidebar_widget.primary_url}">{sidebar_widget.primary_label}</a><a class="btn btn-sm btn-light" href="{sidebar_widget.secondary_url}">{sidebar_widget.secondary_label}</a></div></div></div>
{/if}

{if sidebar_widget.is_sponsored_content}
<div class="{sidebar_widget.class} card ui-theme-sponsored-card ui-card ui-panel" data-ui-style-color="{sidebar_widget.color_style}">{if sidebar_widget.has_image}<img src="{sidebar_widget.image_url}" alt="{sidebar_widget.headline}" title="{sidebar_widget.headline}" width="600" height="315" loading="lazy" decoding="async">{/if}<div class="card-body ui-panel__body"><small>{sidebar_widget.sponsor_label}</small><strong>{sidebar_widget.headline}</strong><p>{sidebar_widget.description}</p><a href="{sidebar_widget.target_url}" rel="sponsored noopener">{sidebar_widget.button_label}</a></div></div>
{/if}

{if sidebar_widget.is_poll_cta}
<div class="{sidebar_widget.class} card poll-card ui-card ui-panel" data-ui-style-color="{sidebar_widget.color_style}"><div class="card-header pb-0 border-0 ui-panel__head"><h5 class="card-title mb-0">{sidebar_widget.title}</h5></div><div class="card-body ui-panel__body"><p class="fw-semibold mb-2">{sidebar_widget.question}</p>{loop sidebar_widget.options}<label><input type="radio" name="{sidebar_widget.id}_poll"> {item.label}</label>{/loop}<div class="poll-actions"><a class="btn btn-sm btn-primary" href="{sidebar_widget.button_url}">{sidebar_widget.button_label}</a></div></div></div>
{/if}

{if sidebar_widget.is_leaderboard}
<div class="{sidebar_widget.class} card ui-panel" data-ui-style-color="{sidebar_widget.color_style}"><div class="card-header pb-0 border-0 ui-panel__head"><h5 class="card-title mb-0">{sidebar_widget.title}</h5></div><div class="card-body ui-panel__body">{loop sidebar_widget.items}<a class="ui-theme-leader-mini" href="{item.url}"><span>{item.rank}</span><strong>{item.name}</strong><small>{item.score}</small></a>{/loop}</div></div>
{/if}

{if sidebar_widget.is_site_stats}
<div class="{sidebar_widget.class} card ui-panel" data-ui-style-color="{sidebar_widget.color_style}"><div class="card-header pb-0 border-0 ui-panel__head"><h5 class="card-title mb-0">{sidebar_widget.title}</h5></div><div class="card-body ui-panel__body"><div class="ui-theme-stat-mini-grid ui-grid">{loop sidebar_widget.items}<div><i class="bi {item.icon}"></i><strong>{item.value}</strong><small>{item.label}</small></div>{/loop}</div></div></div>
{/if}

{if sidebar_widget.is_custom_html}
<div class="{sidebar_widget.class} card ui-panel" data-ui-style-color="{sidebar_widget.color_style}"><div class="card-header pb-0 border-0 ui-panel__head"><h5 class="card-title mb-0">{sidebar_widget.title}</h5></div><div class="card-body ui-panel__body">{raw:sidebar_widget.html}</div></div>
{/if}
{/loop}
</div>
</div>
</nav>
</aside>
