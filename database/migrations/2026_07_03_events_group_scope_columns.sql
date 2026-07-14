-- ============================================================================
-- Events group-scope columns for task/activity rules (2026-07-03)
-- ============================================================================
-- Ensures Events task readiness checks can pass when runtime schema updates are
-- Runtime schema mutation is disabled; this migration is the only owner.
--
-- Required by:
-- - events_tasks.group_id
-- - events_activity_rules.required_group_id
-- ============================================================================

ALTER TABLE `events_tasks`
    ADD COLUMN `group_id` BIGINT UNSIGNED DEFAULT NULL AFTER `min_user_points`;

ALTER TABLE `events_tasks`
    ADD INDEX `events_tasks_group_index` (`group_id`);

ALTER TABLE `events_activity_rules`
    ADD COLUMN `required_group_id` BIGINT UNSIGNED DEFAULT NULL AFTER `min_account_age_days`;

ALTER TABLE `events_activity_rules`
    ADD INDEX `events_activity_rules_group_index` (`required_group_id`);
