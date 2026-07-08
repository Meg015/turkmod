<aside class="profile-sidebar">
    <div class="profile-sidebar-card profile-sidebar-card--hero ui-card">
        <div class="profile-sidebar-avatar profile-sidebar-avatar--hero">
            {if profile.has_avatar}
                <img src="{profile.avatar}" alt="{profile.username}" title="{profile.username}" width="56" height="56" loading="lazy" data-ui-avatar-img data-ui-avatar-fallback="{profile.avatar_fallback}">
            {else}
                <img src="{profile.avatar_fallback}" alt="{profile.username}" title="{profile.username}" width="56" height="56" loading="lazy" data-ui-avatar-img data-ui-avatar-fallback="{profile.avatar_fallback}">
            {/if}
        </div>

        <h3 class="profile-sidebar-name">{profile.username}</h3>

        <div class="profile-sidebar-role profile-sidebar-group profile-sidebar-rank">
            <span class="badge profile-group-badge profile-group-badge--{profile.group_slug}">{profile.group}</span>
        </div>

        {if profile.bio}<p class="profile-sidebar-bio">{profile.bio|strip_tags|escape|nl2br|raw}</p>{/if}

        <div class="profile-sidebar-meta">
            <div class="profile-sidebar-meta-item profile-sidebar-meta-item--joined">
                <i class="bi bi-calendar3" aria-hidden="true"></i>
                <span class="profile-sidebar-meta-label">Kayıt Tarihi</span>
                <strong>{profile.created_at}</strong>
            </div>
            {if profile.tenure}
                <div class="profile-sidebar-meta-item profile-sidebar-meta-item--tenure">
                    <i class="bi bi-hourglass-split" aria-hidden="true"></i>
                    <span class="profile-sidebar-meta-label">Üyelik Süresi</span>
                    <strong>{profile.tenure}</strong>
                </div>
            {/if}
            {if profile.has_location}
                <div class="profile-sidebar-meta-item profile-sidebar-meta-item--location">
                    <i class="bi bi-geo-alt" aria-hidden="true"></i>
                    <span class="profile-sidebar-meta-label">Konum</span>
                    <strong>{profile.location}</strong>
                </div>
            {/if}
        </div>

        {if profile.has_sidebar_actions}
            <div class="profile-sidebar-social">
                {loop profile.social_links}
                    <a href="{item.url}" class="profile-sidebar-social-link" target="_blank" rel="noopener" title="{item.title}" aria-label="{item.title}"><i class="bi {item.icon}" aria-hidden="true"></i></a>
                {/loop}
                {if profile.can_report}
                    <button type="button" class="profile-sidebar-social-link profile-sidebar-report-action" data-user-report-modal-open title="Kullanıcıyı Şikayet Et" aria-label="Kullanıcıyı Şikayet Et"><i class="bi bi-flag" aria-hidden="true"></i></button>
                {/if}
                {if profile.can_message}
                    {if profile.message_url}
                        <a href="{profile.message_url}" class="profile-sidebar-social-link profile-sidebar-message-action" title="Mesaj Gonder" aria-label="Mesaj Gonder"><i class="bi bi-chat-left-text" aria-hidden="true"></i></a>
                    {/if}
                {/if}
            </div>
        {/if}
    </div>

    <div class="profile-sidebar-card profile-sidebar-card--stats ui-card">
        <h4 class="profile-sidebar-card-title"><i class="bi bi-graph-up" aria-hidden="true"></i> İstatistikler</h4>
        <div class="profile-sidebar-stats">
            {loop profile.sidebar_stats}
                <div class="profile-sidebar-stat">
                    <div class="profile-sidebar-stat-icon {item.class}"><i class="bi {item.icon}" aria-hidden="true"></i></div>
                    <div class="profile-sidebar-stat-info">
                        <div class="profile-sidebar-stat-value">{item.value}</div>
                        <div class="profile-sidebar-stat-label">{item.label}</div>
                    </div>
                </div>
            {/loop}
        </div>
    </div>
</aside>

