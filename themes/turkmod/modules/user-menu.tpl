    <div class="user-dropdown-container ui-theme-profile-dropdown">
        <button class="ui-theme-profile-toggle user-toggle" type="button" id="profileDropdownBtn" aria-expanded="false" aria-haspopup="menu" aria-controls="profileDropdownMenu">
            <span class="avatar avatar-xs"><span class="avatar-img rounded-circle avatar-fallback default-avatar"><img src="{user_avatar_url}" alt="{user_name}" width="40" height="40" loading="lazy" data-ui-avatar-img data-ui-avatar-fallback="{user_avatar_fallback}"></span></span><span class="ui-theme-profile-toggle__name">{user_name}</span><i class="bi bi-chevron-down" aria-hidden="true"></i>
        </button>
        <div class="ui-theme-profile-menu user-dropdown" id="profileDropdownMenu" role="menu" aria-labelledby="profileDropdownBtn" hidden>
            <div class="ui-theme-profile-menu__head ui-panel__head">
                <span class="avatar avatar-sm"><span class="avatar-img rounded-circle avatar-fallback default-avatar"><img src="{user_avatar_url}" alt="{user_name}" width="40" height="40" loading="lazy" data-ui-avatar-img data-ui-avatar-fallback="{user_avatar_fallback}"></span></span>
                <span><strong>{user_name}</strong><small>Hesap menusu</small></span>
            </div>
            <a class="ui-theme-profile-menu__item" role="menuitem" href="{base_url}/profile.php"><i class="bi bi-person-circle" aria-hidden="true"></i><span>Profilim</span></a>
            <a class="ui-theme-profile-menu__item" role="menuitem" href="{notifications_url}"><i class="bi bi-bell" aria-hidden="true"></i><span>Bildirimler</span></a>
            <a class="ui-theme-profile-menu__item" role="menuitem" href="{base_url}/upload-topic.php"><i class="bi bi-cloud-arrow-up" aria-hidden="true"></i><span>Mod Yükle</span></a>
            {if user_is_admin}<a class="ui-theme-profile-menu__item is-admin" role="menuitem" href="{base_url}/admin/index.php"><i class="bi bi-speedometer2" aria-hidden="true"></i><span>Admin Paneli</span></a>{/if}
            <span class="ui-theme-profile-menu__divider" aria-hidden="true"></span>
            <a class="ui-theme-profile-menu__item is-danger" role="menuitem" href="{base_url}/logout.php"><i class="bi bi-box-arrow-right" aria-hidden="true"></i><span>Çıkış</span></a>
        </div>
    </div>
