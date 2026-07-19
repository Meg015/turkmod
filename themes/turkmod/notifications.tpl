<main class="{notifications_shell_class}" data-ui-style-number="--notification-message-lines:{notifications_message_lines}" data-notifications-page data-notifications-csrf="{notifications_csrf_token}" data-notifications-read-endpoint="{notifications_read_endpoint}" data-notifications-delete-endpoint="{notifications_delete_endpoint}" data-notifications-read-more="{notifications_read_more_js}" data-notifications-auto-mark="{notifications_auto_mark_js}">
    <section class="notifications-hero" aria-labelledby="notifications-title">
        <div>
            <span class="notifications-kicker"><i class="bi bi-bell" aria-hidden="true"></i> Hesap Merkezi</span>
            <h1 id="notifications-title">Bildirimleriniz</h1>
            <p>Platform duyurularini, hesabiniza ozel guncellemeleri ve okunmamis bildirimleri tek bir duzenli ekrandan takip edin.</p>
        </div>
        <div class="notifications-hero-metrics" aria-label="Bildirim ozeti">
            <div class="notifications-metric"><strong data-notif-total>{notifications_total}</strong><span>Toplam bildirim</span></div>
            <div class="notifications-metric"><strong data-notif-unread>{notifications_unread}</strong><span>Okunmamis</span></div>
            <div class="notifications-metric"><strong data-notif-read>{notifications_read}</strong><span>Okunmus</span></div>
        </div>
    </section>

    {if notifications_success}<div class="notifications-alert is-success" role="status"><i class="bi bi-check-circle-fill" aria-hidden="true"></i><span>{notifications_success}</span></div>{/if}
    {if notifications_error}<div class="notifications-alert is-error" role="alert"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i><span>{notifications_error}</span></div>{/if}

    <div class="notifications-workspace">
        <aside class="notifications-rail" aria-label="Bildirim menusu">
            <div class="notifications-rail-head">
                <strong>Bildirim Merkezi</strong>
                <span>Gelen kutusu ve tercihler</span>
            </div>
            <nav class="notifications-nav">
                <a href="{notifications_list_url}" class="notifications-nav-link {if notifications_tab_list}is-active{/if}">
                    <span><i class="bi bi-inbox" aria-hidden="true"></i> Gelen Kutusu</span>
                    {if notifications_has_unread}<span class="notifications-pill" data-sidebar-unread>{notifications_unread_badge}</span>{/if}
                </a>
                <a href="{notifications_settings_url}" class="notifications-nav-link {if notifications_tab_settings}is-active{/if}">
                    <span><i class="bi bi-sliders" aria-hidden="true"></i> Tercihler</span>
                    <i class="bi bi-chevron-right" aria-hidden="true"></i>
                </a>
            </nav>
            <div class="notifications-rail-summary" aria-label="Kisa ozet">
                <div class="rail-summary-row"><span>Okunmamis orani</span><strong>{notifications_unread_ratio}%</strong></div>
                <div class="rail-summary-row"><span>Sayfa basina</span><strong>{notifications_per_page}</strong></div>
            </div>
        </aside>

        <section class="notifications-main" aria-label="Bildirim icerigi">
            {if notifications_tab_list}
            <div class="notifications-panel-head">
                <div class="notifications-panel-title">
                    <h2>Gelen Kutusu</h2>
                    <p>Okunmamislari hizlica yakalayin, eski duyurulara donun veya ilgili sayfaya tek tikla gecin.</p>
                </div>
                {if notifications_has_unread}
                <button type="button" class="notifications-action" data-mark-all-read>
                    <i class="bi bi-check2-all" aria-hidden="true"></i>
                    <span>Tumunu okundu yap</span>
                </button>
                {/if}
            </div>

            <div class="notifications-toolbar">
                <div class="notifications-filters" aria-label="Bildirim filtreleri">
                    {loop notifications_filters}
                    <a class="{notifications_filter.class}" href="{notifications_filter.url}" data-filter-kind="{notifications_filter.label}">
                        <span>{notifications_filter.label}</span>
                        <strong>{notifications_filter.count}</strong>
                    </a>
                    {/loop}
                </div>
                <span class="notifications-count">{notifications_result_count} sonuc</span>
                {if notifications_has_items}
                <div class="notifications-toolbar-actions">
                    <label class="notifications-select-all">
                        <input type="checkbox" data-notif-select-all aria-label="Görünen bildirimlerin hepsini seç">
                        <span>Tümünü seç</span>
                    </label>
                    <button type="button" class="notifications-action notifications-delete-action" data-notif-delete-selected disabled>
                        <i class="bi bi-trash" aria-hidden="true"></i>
                        <span>Seçilenleri sil</span>
                    </button>
                </div>
                {/if}
            </div>

            {if notifications_has_items}
            <div class="notifications-feed" data-notif-feed>
                {loop notifications_items}
                <article class="{notifications_item.class}" id="notif-{notifications_item.id}" data-notif-item data-id="{notifications_item.id}">
                    <div class="notification-meta-actions">
                        <time class="notification-time" datetime="{notifications_item.datetime}" title="{notifications_item.date_title}">
                            <i class="bi bi-clock" aria-hidden="true"></i>
                            {notifications_item.date_short}
                        </time>
                        <label class="notification-select" title="Bildirimi seç">
                            <input type="checkbox" data-notif-select value="{notifications_item.id}" aria-label="Bildirimi seç">
                        </label>
                    </div>
                    <span class="{notifications_item.icon_class}" aria-hidden="true"><i class="bi {notifications_item.icon}"></i></span>
                    <div class="notification-body ui-panel__body">
                        <div class="notification-topline">
                            <div class="notification-title-group">
                                <h3 class="notification-title">{notifications_item.title}</h3>
                                <span class="{notifications_item.status_class}" data-type-label="{notifications_item.type_label}">
                                    <i class="bi {notifications_item.status_icon}" aria-hidden="true"></i>
                                    {notifications_item.status_label}
                                </span>
                                <span class="{notifications_item.type_chip_class}" title="{notifications_item.type_summary}">
                                    <i class="bi {notifications_item.icon}" aria-hidden="true"></i>
                                    {notifications_item.type_summary}
                                </span>
                            </div>
                        </div>

                        <p class="notification-message" data-notif-message>{notifications_item.message}</p>

                        <div class="notification-footer ui-panel__foot">
                            {if notifications_item.has_link}
                            <a href="{notifications_item.link}" class="notification-link" data-notif-open data-id="{notifications_item.id}">
                                <span>Goruntule</span>
                                <i class="bi bi-arrow-right-short" aria-hidden="true"></i>
                            </a>
                            {/if}
                            <button type="button" class="notification-read-more" data-notification-message-toggle hidden>
                                <span>Daha fazla goster</span>
                                <i class="bi bi-chevron-down" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                </article>
                {/loop}
            </div>

            {if notifications_has_pagination}
            <nav class="notifications-pagination" aria-label="Bildirim sayfalari">
                {if notifications_has_prev}<a href="{notifications_prev_url}"><i class="bi bi-chevron-left" aria-hidden="true"></i> Onceki</a>{/if}
                <span>{notifications_page_label} / {notifications_total_pages_label}</span>
                {if notifications_has_next}<a href="{notifications_next_url}">Sonraki <i class="bi bi-chevron-right" aria-hidden="true"></i></a>{/if}
            </nav>
            {/if}
            {else}
            <div class="notifications-empty">
                <div>
                    <span class="notifications-empty-icon"><i class="bi bi-envelope-open" aria-hidden="true"></i></span>
                    <h3>Burada gosterilecek bildirim yok</h3>
                    {if notifications_empty_tips_enabled}<p>{notifications_empty_message}</p>{/if}
                    <div class="notifications-empty-actions">
                        <a href="{notifications_settings_url}"><i class="bi bi-sliders" aria-hidden="true"></i> Tercihleri Duzenle</a>
                        <a href="{base_url}/index.php"><i class="bi bi-grid ui-grid" aria-hidden="true"></i> Iceriklere Git</a>
                    </div>
                </div>
            </div>
            {/if}
            {/if}

            {if notifications_tab_settings}
            <form method="POST" action="{notifications_settings_url}" class="notification-settings">
                <input type="hidden" name="_token" value="{notifications_csrf_token}">
                <input type="hidden" name="action" value="save_settings">

                <div class="settings-intro">
                    <div>
                        <h2>Bildirim Tercihleri</h2>
                        <p>Site ici ve e-posta bildirimlerini birbirinden bagimsiz yonetin. Kritik hesap ve guvenlik bildirimleri gerektiginde yine gosterilebilir.</p>
                    </div>
                    <i class="bi bi-shield-check" aria-hidden="true"></i>
                </div>

                <div class="settings-tabs" role="tablist" aria-label="Bildirim tercih bolumleri">
                    {loop notification_preference_tab_items}
                    <button type="button" class="settings-tab {if notification_preference_tab_item.is_active}is-active{/if}" role="tab" aria-selected="{if notification_preference_tab_item.is_active}true{else}false{/if}" data-notification-settings-tab="{notification_preference_tab_item.key}">
                        <i class="bi {notification_preference_tab_item.icon}" aria-hidden="true"></i>
                        <span>{notification_preference_tab_item.label}</span>
                    </button>
                    {/loop}
                </div>

                <div class="settings-groups-stack">
                {loop notification_preference_groups}
                <section class="settings-group-panel ui-panel {if notification_preference_group.is_active_tab}is-active{/if}" data-notification-preference-group data-notification-settings-panel="{notification_preference_group.tab}" {if !notification_preference_group.is_active_tab}hidden{/if}>
                    <div class="settings-group-head ui-panel__head">
                        <span class="settings-group-icon" aria-hidden="true"><i class="bi {notification_preference_group.icon}"></i></span>
                        <div class="settings-group-copy">
                            <h3>{notification_preference_group.title}</h3>
                            <p>{notification_preference_group.description}</p>
                        </div>
                        {if notification_preference_group.has_key}
                        <label class="settings-group-switch" for="{notification_preference_group.input_id}">
                            <span class="settings-group-switch-text">Grubu aktif tut</span>
                            <span class="notification-switch">
                                <input id="{notification_preference_group.input_id}" type="checkbox" name="{notification_preference_group.key}" value="1" data-notification-group-toggle {notification_preference_group.checked}>
                                <span class="notification-slider"></span>
                            </span>
                        </label>
                        {/if}
                    </div>
                    <div class="settings-grid ui-grid">
                        {loop notification_preference_group.items}
                        <label class="setting-row" for="{item.input_id}" data-notification-effect-row data-effect-on="{item.enabled_effect}" data-effect-off="{item.disabled_effect}">
                            <span class="setting-row-icon"><i class="bi {item.icon}" aria-hidden="true"></i></span>
                            <span class="setting-row-copy">
                                <strong>{item.title}</strong>
                                <span>{item.description}</span>
                                <small class="setting-effect{item.effect_disabled_class}" data-notification-effect>{item.current_effect}</small>
                            </span>
                            <span class="notification-switch">
                                <input id="{item.input_id}" type="checkbox" name="{item.key}" value="1" data-notification-group-item {item.checked}>
                                <span class="notification-slider"></span>
                            </span>
                        </label>
                        {/loop}
                    </div>
                </section>
                {/loop}
                </div>

                <div class="settings-actions">
                    <button type="submit" class="settings-save-btn">
                        <i class="bi bi-check2-circle" aria-hidden="true"></i>
                        <span>Ayarlari Kaydet</span>
                    </button>
                    <button type="submit" class="settings-reset-btn" name="preset" value="recommended">
                        <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
                        <span>Onerilen Ayarlara Don</span>
                    </button>
                </div>
            </form>
            {/if}
        </section>
    </div>
</main>
