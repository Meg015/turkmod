# Registration Role Lookup Compatibility Design

## Problem

The current database schema has neither a `roles` table nor a `users.role_id` column. The full-page registration handler nevertheless queries `roles` unconditionally, causing registration to fail with SQLSTATE 42S02. The popup registration handler catches the same lookup failure, but it still performs an unnecessary query.

## Decision

Registration must treat legacy role assignment as optional schema compatibility. Both registration entry points will first inspect whether `users.role_id` exists. They will query `roles` only when that column and the `roles` table both exist. If the optional lookup cannot resolve the member role, the existing legacy fallback ID of `3` will remain available for installations that still have `users.role_id`.

The current group-based registration behavior remains unchanged: after the user row is created, the configured default user group is synchronized through `usersDefaultGroupId()` and `usersSyncUserGroups()`.

## Components

- `includes/src/Engine/Auth/Http/register-page-content.php`: prevent the unconditional legacy role query and reuse the schema result when building the insert.
- `api/auth-popup.php`: apply the same conditional behavior so both registration paths have parity.
- Existing `usersTableExists()` and `usersColumnExists()` helpers will be used; no new database table or migration will be introduced.

## Data Flow

1. Validate registration input and uniqueness as currently implemented.
2. Inspect whether `users.role_id` exists.
3. Only for a legacy schema with `users.role_id`, inspect whether `roles` exists and resolve the `member` role when possible.
4. Insert the user using only columns present in the active schema.
5. Synchronize the default user group and continue existing notification, activity, and authentication behavior.

## Error Handling

An absent optional legacy table must not abort registration. Genuine failures from the user insert, group synchronization, or other required registration operations will retain their existing error handling and logging behavior.

## Verification

- Run PHP syntax checks on both modified registration handlers.
- Confirm static inspection shows no unconditional `roles` query in either registration path.
- Exercise the local registration endpoint against the current schema and confirm the missing-table exception no longer appears.
- Confirm the created user receives the existing default group assignment.

## Scope

This change fixes registration compatibility only. It does not recreate the removed role system, modify authorization rules, or alter the current user-group model.
