    <div class="card card-body text-center ui-theme-empty-state ui-panel ui-panel__body">
        <i class="bi {empty_state.icon} ui-theme-empty-state__icon" aria-hidden="true"></i>
        <h2 class="h5 mt-2">{empty_state.title}</h2>
        <p class="mb-0 text-secondary">{empty_state.description}</p>
        {if empty_state.action_url}
        <a href="{empty_state.action_url}" class="btn btn-sm btn-primary-soft mt-2">{empty_state.action_label}</a>
        {/if}
    </div>
