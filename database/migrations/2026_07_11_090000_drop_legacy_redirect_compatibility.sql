-- Remove legacy redirect compatibility leftovers.

DROP TABLE IF EXISTS legacy_redirect_hits;
DROP TABLE IF EXISTS legacy_redirect_rules;
DROP TABLE IF EXISTS rate_limits;

DELETE FROM admin_settings
WHERE setting_key IN (
    'route_old_url_redirect',
    'route_alias_redirects',
    'route_topic_aliases',
    'route_category_aliases',
    'route_profile_aliases'
)
   OR setting_key = 'route_redirect_to_canonical'
   OR setting_key LIKE 'legacy_redirect_%';

DELETE FROM user_group_permissions
WHERE permission_key IN ('legacy_redirects.view', 'legacy_redirects.manage');

DELETE FROM user_group_permission_overrides
WHERE permission_key IN ('legacy_redirects.view', 'legacy_redirects.manage');
