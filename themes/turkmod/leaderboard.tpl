<div class="leaderboard-container ui-container ui-section">
    <div class="ui-panel">
        <div class="ui-panel__body">
            {if leaderboard_disabled}
            <section class="leaderboard-empty-state leaderboard-empty-state--disabled ui-empty" role="status" aria-labelledby="leaderboardDisabledTitle">
                <div class="leaderboard-empty-state__media" aria-hidden="true">
                    <span class="leaderboard-empty-state__halo"></span>
                    <span class="leaderboard-empty-state__icon"><i class="bi bi-pause-circle"></i></span>
                </div>
                <div class="leaderboard-empty-state__content">
                    <span class="leaderboard-empty-state__eyebrow">Liderlik sistemi</span>
                    <h1 id="leaderboardDisabledTitle">{page_title}</h1>
                    <p>{leaderboard_disabled_message}</p>
                    <ul class="leaderboard-empty-state__tips">
                        <li><i class="bi bi-check2-circle"></i> Sistem yeniden acildiginda siralamalar otomatik hesaplanir.</li>
                        <li><i class="bi bi-check2-circle"></i> Kategorileri gezerek aktif icerikleri takip edebilirsiniz.</li>
                    </ul>
                    <div class="leaderboard-empty-state__actions">
                        <a class="leaderboard-empty-state__btn is-primary" href="{leaderboard_disabled_categories_url}">
                            <i class="bi bi-grid"></i>
                            <span>Kategorilere Git</span>
                        </a>
                        <a class="leaderboard-empty-state__btn is-secondary" href="{leaderboard_disabled_contact_url}">
                            <i class="bi bi-envelope-paper"></i>
                            <span>Yonetimle Iletisim</span>
                        </a>
                    </div>
                </div>
            </section>
            {else}
            <header class="leaderboard-header">
                <div class="leaderboard-title">
                    <span class="leaderboard-eyebrow">Topluluk Sıralaması</span>
                    <h1 id="leaderboardTitle"><i class="bi bi-trophy" aria-hidden="true"></i> {page_title}</h1>
                    <p>{leaderboard_description}</p>
                </div>
                {if leaderboard_is_cached}
                <div class="ui-admin-alert ui-admin-alert-info ui-alert ui-alert--info ui-admin-alert-spaced" role="status">
                    <i class="bi bi-lightning-charge" aria-hidden="true"></i> Önbellekten hızlı yüklendi
                </div>
                {/if}
            </header>

                <div class="leaderboard-tabs">
                    {loop leaderboard_category_tabs}
                    <a href="{leaderboard_category_tab.url}" class="{leaderboard_category_tab.class}">
                        <i class="bi {leaderboard_category_tab.icon}" aria-hidden="true"></i>
                        <span>{leaderboard_category_tab.name}</span>
                    </a>
                    {/loop}
                </div>

                <div class="leaderboard-controls">
                    <div class="period-buttons">
                        {loop leaderboard_period_options}
                        <a class="{leaderboard_period_option.class}" href="{leaderboard_period_option.url}">{leaderboard_period_option.name}</a>
                        {/loop}
                    </div>
                    <div class="leaderboard-search">
                        <form method="get" action="{base_url}/leaderboard.php">
                            <input type="hidden" name="category" value="{leaderboard_category}">
                            <input type="hidden" name="period" value="{leaderboard_period}">
                            <div class="search-input-group">
                                <i class="bi bi-search" aria-hidden="true"></i>
                                <input type="text" name="search" placeholder="Kullanici ara..." value="{leaderboard_search}" class="search-input">
                                {if leaderboard_search}<a href="{leaderboard_search_clear_url}" class="search-clear" aria-label="Aramayi temizle"><i class="bi bi-x" aria-hidden="true"></i></a>{/if}
                            </div>
                        </form>
                    </div>
                </div>

                {if leaderboard_has_rows}
                <div class="leaderboard-table-container ui-table-wrap">
                    <table class="leaderboard-table">
                        <thead>
                            <tr>
                                <th class="col-rank">Sira</th>
                                <th class="col-user">Kullanici</th>
                                <th class="col-score">Sayi</th>
                                <th class="col-change">Degisim</th>
                                <th class="col-metadata">Detaylar</th>
                            </tr>
                        </thead>
                        <tbody id="leaderboard-tbody">
                            {loop leaderboard_rows}
                            <tr class="{leaderboard_row.row_class}">
                                <td class="col-rank"><span class="{leaderboard_row.rank_class}">{leaderboard_row.rank_label}</span></td>
                                <td class="col-user">
                                    <div class="user-cell">
                                        <a href="{leaderboard_row.profile_url}">
                                            <img src="{leaderboard_row.avatar_url}" alt="{leaderboard_row.username}" title="{leaderboard_row.username}" class="user-avatar" width="30" height="30" loading="lazy" data-ui-avatar-img data-ui-avatar-fallback="{leaderboard_avatar_fallback}">
                                        </a>
                                        <div class="user-info">
                                            <a href="{leaderboard_row.profile_url}" class="user-name">{leaderboard_row.username}</a>
                                            {if leaderboard_row.is_current_user}<span class="user-badge">Siz</span>{/if}
                                        </div>
                                    </div>
                                </td>
                                <td class="col-score"><strong>{leaderboard_row.score}</strong></td>
                                <td class="col-change">
                                    <span class="{leaderboard_row.change_class}">
                                        <i class="bi {leaderboard_row.change_icon}" aria-hidden="true"></i> {leaderboard_row.change_label}
                                    </span>
                                </td>
                                <td class="col-metadata">
                                    {if leaderboard_row.has_metadata}
                                    <div class="metadata-items">
                                        <span class="metadata-item" title="{leaderboard_row.metadata_label}">
                                            <i class="bi {leaderboard_row.metadata_icon}" aria-hidden="true"></i> {leaderboard_row.metadata_value}
                                        </span>
                                    </div>
                                    {/if}
                                </td>
                            </tr>
                            {/loop}
                        </tbody>
                    </table>
                </div>

                {if leaderboard_has_pagination}
                <nav class="pagination" aria-label="Lider tablosu sayfalama">
                    {if !leaderboard_prev_disabled}<a href="{leaderboard_prev_url}" aria-label="Onceki sayfa"><i class="bi bi-chevron-left" aria-hidden="true"></i></a>{/if}
                    {loop leaderboard_pagination_pages}
                    <a class="{leaderboard_pagination_page.class}" href="{leaderboard_pagination_page.url}">{leaderboard_pagination_page.label}</a>
                    {/loop}
                    {if !leaderboard_next_disabled}<a href="{leaderboard_next_url}" aria-label="Sonraki sayfa"><i class="bi bi-chevron-right" aria-hidden="true"></i></a>{/if}
                </nav>
                {/if}
                {else}
                <div class="leaderboard-empty-state ui-empty" role="status" aria-live="polite" aria-labelledby="leaderboardEmptyTitle">
                    <div class="leaderboard-empty-state__media" aria-hidden="true">
                        <span class="leaderboard-empty-state__halo"></span>
                        <span class="leaderboard-empty-state__icon"><i class="bi bi-graph-down-arrow"></i></span>
                    </div>
                    <div class="leaderboard-empty-state__content">
                        <span class="leaderboard-empty-state__eyebrow">Siralama boslugu</span>
                        <h3 id="leaderboardEmptyTitle">{leaderboard_empty_title}</h3>
                        <p>{leaderboard_empty_description}</p>
                        <div class="leaderboard-empty-state__context">
                            <span><i class="bi bi-collection"></i> {leaderboard_empty_category_label}</span>
                            <span><i class="bi bi-calendar3"></i> {leaderboard_empty_period_label}</span>
                        </div>
                        <ul class="leaderboard-empty-state__tips">
                            <li><i class="bi bi-check2-circle"></i> Farkli donem secerek karsilastirma yapabilirsiniz.</li>
                            <li><i class="bi bi-check2-circle"></i> Liste olustugunda ilk 3 uye burada rozetle gosterilir.</li>
                            <li><i class="bi bi-check2-circle"></i> Kategori gecisleri ve arama filtreleri korunur.</li>
                        </ul>
                        <div class="leaderboard-empty-state__actions">
                            <a class="leaderboard-empty-state__btn is-primary" href="{leaderboard_empty_primary_url}">
                                <i class="bi bi-magic"></i>
                                <span>{leaderboard_empty_primary_label}</span>
                            </a>
                            <a class="leaderboard-empty-state__btn is-secondary" href="{leaderboard_empty_secondary_url}">
                                <i class="bi bi-compass"></i>
                                <span>{leaderboard_empty_secondary_label}</span>
                            </a>
                        </div>
                    </div>
                </div>
                {/if}
            {/if}
            </div>
        </div>
</div>
