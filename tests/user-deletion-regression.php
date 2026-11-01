<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/includes/autoloader.php';

use App\Engine\Users\UserDeletionService;
use App\Engine\Users\UserDeletionFiles;

$checks = 0;
$failures = [];
$GLOBALS['deletion_permissions'] = ['users.delete', 'topics.delete', 'comments.delete'];

function userHasPermission(?PDO $pdo, int $userId, string $permission): bool
{
    return $userId === 1 && in_array($permission, $GLOBALS['deletion_permissions'], true);
}
function deletionCheck(bool $condition, string $label): void
{
    $GLOBALS['checks']++;
    if (!$condition) { $GLOBALS['failures'][] = $label; }
}
function deletionRejects(callable $action): bool
{
    try { $action(); } catch (Throwable) { return true; }
    return false;
}
function deletionFixture(): PDO
{
    $db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->exec('PRAGMA foreign_keys = ON');
    $schema = [
        "CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT UNIQUE NOT NULL, email TEXT UNIQUE NOT NULL, password TEXT NOT NULL DEFAULT 'hash', status TEXT DEFAULT 'active', is_banned INTEGER DEFAULT 0, deleted_at TEXT, avatar TEXT, public_profile INTEGER DEFAULT 1, public_show_topics INTEGER DEFAULT 1, public_show_comments INTEGER DEFAULT 1, public_show_socials INTEGER DEFAULT 1, total_topics INTEGER DEFAULT 0, total_comments INTEGER DEFAULT 0, total_downloads INTEGER DEFAULT 0, helpful_count INTEGER DEFAULT 0, created_at TEXT, updated_at TEXT)",
        "CREATE TABLE topics (id INTEGER PRIMARY KEY, author_id INTEGER NOT NULL REFERENCES users(id), comment_count INTEGER DEFAULT 0, download_count INTEGER DEFAULT 0, status TEXT DEFAULT 'published', deleted_at TEXT)",
        "CREATE TABLE comments (id INTEGER PRIMARY KEY, topic_id INTEGER REFERENCES topics(id) ON DELETE CASCADE, user_id INTEGER REFERENCES users(id) ON DELETE SET NULL, parent_id INTEGER REFERENCES comments(id) ON DELETE CASCADE, body TEXT, status TEXT DEFAULT 'approved', deleted_at TEXT, reaction_count INTEGER DEFAULT 0, mention_count INTEGER DEFAULT 0)",
        'CREATE TABLE comment_media (id INTEGER PRIMARY KEY, comment_id INTEGER REFERENCES comments(id) ON DELETE CASCADE, user_id INTEGER REFERENCES users(id) ON DELETE CASCADE, file_path TEXT)',
        'CREATE TABLE comment_edit_history (id INTEGER PRIMARY KEY, comment_id INTEGER REFERENCES comments(id) ON DELETE CASCADE, user_id INTEGER REFERENCES users(id) ON DELETE CASCADE)',
        'CREATE TABLE comment_reactions (id INTEGER PRIMARY KEY, comment_id INTEGER REFERENCES comments(id) ON DELETE CASCADE, user_id INTEGER REFERENCES users(id) ON DELETE CASCADE, reaction_type TEXT)',
        'CREATE TABLE comment_mentions (id INTEGER PRIMARY KEY, comment_id INTEGER REFERENCES comments(id) ON DELETE CASCADE, mentioned_user_id INTEGER REFERENCES users(id) ON DELETE CASCADE, mentioner_user_id INTEGER REFERENCES users(id) ON DELETE CASCADE)',
        'CREATE TABLE media_files (id INTEGER PRIMARY KEY, topic_id INTEGER REFERENCES topics(id) ON DELETE CASCADE, user_id INTEGER REFERENCES users(id) ON DELETE SET NULL, path TEXT)',
        'CREATE TABLE static_pages (id INTEGER PRIMARY KEY, body_html TEXT, og_image TEXT)',
        'CREATE TABLE user_group_members (user_id INTEGER, group_id INTEGER, assigned_by INTEGER)',
        'CREATE TABLE user_group_logs (id INTEGER PRIMARY KEY, user_id INTEGER, changed_by INTEGER)',
        'CREATE TABLE ban_appeals (id INTEGER PRIMARY KEY, user_id INTEGER, reviewed_by INTEGER)',
        'CREATE TABLE ban_appeal_messages (id INTEGER PRIMARY KEY, appeal_id INTEGER REFERENCES ban_appeals(id) ON DELETE CASCADE, sender_user_id INTEGER REFERENCES users(id) ON DELETE SET NULL)',
        'CREATE TABLE notifications (id INTEGER PRIMARY KEY, user_id INTEGER REFERENCES users(id) ON DELETE CASCADE, actor_user_id INTEGER)',
        'CREATE TABLE security_events (id INTEGER PRIMARY KEY, user_id INTEGER)',
        'CREATE TABLE email_logs (id INTEGER PRIMARY KEY, user_id INTEGER)',
        'CREATE TABLE events_email_queue (id INTEGER PRIMARY KEY, user_id INTEGER REFERENCES users(id) ON DELETE SET NULL)',
        'CREATE TABLE bulk_email_recipients (id INTEGER PRIMARY KEY, user_id INTEGER REFERENCES users(id) ON DELETE SET NULL)',
        'CREATE TABLE user_reports (id INTEGER PRIMARY KEY, reported_user_id INTEGER REFERENCES users(id) ON DELETE CASCADE, reporter_user_id INTEGER REFERENCES users(id) ON DELETE SET NULL)',
        'CREATE TABLE user_report_events (id INTEGER PRIMARY KEY, report_id INTEGER REFERENCES user_reports(id) ON DELETE CASCADE, actor_id INTEGER REFERENCES users(id) ON DELETE SET NULL)',
        'CREATE TABLE bot_sites (id INTEGER PRIMARY KEY, total_imports INTEGER)',
        'CREATE TABLE bot_imports (id INTEGER PRIMARY KEY, topic_id INTEGER REFERENCES topics(id) ON DELETE SET NULL, bot_site_id INTEGER, status TEXT, updated_at TEXT)',
        'CREATE TABLE leaderboard_cache (id INTEGER PRIMARY KEY, user_id INTEGER REFERENCES users(id) ON DELETE CASCADE)',
        'CREATE TABLE activity_logs (id INTEGER PRIMARY KEY, actor_id INTEGER REFERENCES users(id) ON DELETE SET NULL, action TEXT, subject_type TEXT, subject_id INTEGER, properties TEXT, created_at TEXT, updated_at TEXT)',
        'CREATE TABLE admin_action_log (id INTEGER PRIMARY KEY, actor_id INTEGER REFERENCES users(id) ON DELETE SET NULL, action_type TEXT, target_type TEXT, target_id INTEGER, reason TEXT, old_value TEXT, new_value TEXT, is_reversible INTEGER, ip_address TEXT, created_at TEXT)',
    ];
    foreach ($schema as $sql) { $db->exec($sql); }
    $db->exec("INSERT INTO users (id,username,email,avatar) VALUES (1,'admin','admin@example.test',NULL),(2,'target','target@example.test','uploads/profil/avatar.webp'),(3,'other','other@example.test',NULL),(4,'next','next@example.test',NULL)");
    $db->exec("INSERT INTO topics (id,author_id,comment_count) VALUES (10,2,2),(20,3,3)");
    $db->exec("INSERT INTO comments (id,topic_id,user_id,parent_id,body) VALUES (100,10,2,NULL,'target topic'),(101,10,3,100,'other on target topic'),(200,20,2,NULL,'target comment'),(201,20,3,200,'other reply'),(202,20,2,201,'nested target'),(203,20,NULL,202,'guest reply')");
    $db->exec("UPDATE comments SET status='pending' WHERE id=202");
    $db->exec("INSERT INTO comment_media VALUES (1,100,2,'uploads/comments/removed.webp'),(2,200,2,'uploads/comments/kept.webp'),(3,201,2,'uploads/comments/shared.webp')");
    $db->exec('INSERT INTO comment_edit_history VALUES (1,100,2),(2,200,2),(3,201,2)');
    $db->exec("INSERT INTO media_files VALUES (1,10,2,'uploads/konu/deleted.webp'),(2,20,2,'uploads/konu/retained.webp')");
    $db->exec("INSERT INTO comment_reactions VALUES (1,201,2,'helpful'),(2,201,1,'like'),(3,101,1,'helpful')");
    $db->exec('UPDATE comments SET reaction_count=2 WHERE id=201');
    $db->exec('INSERT INTO user_group_members VALUES (1,1,NULL),(2,2,1),(3,2,2)');
    $db->exec('INSERT INTO user_group_logs VALUES (1,2,1),(2,3,2)');
    $db->exec('INSERT INTO ban_appeals VALUES (1,2,NULL),(2,3,2)');
    $db->exec('INSERT INTO ban_appeal_messages VALUES (1,1,2),(2,2,2)');
    $db->exec('INSERT INTO notifications VALUES (1,2,1),(2,3,2)');
    $db->exec('INSERT INTO security_events VALUES (1,2)');
    $db->exec('INSERT INTO email_logs VALUES (1,2)');
    $db->exec('INSERT INTO events_email_queue VALUES (1,2)');
    $db->exec('INSERT INTO bulk_email_recipients VALUES (1,2)');
    $db->exec('INSERT INTO user_reports VALUES (1,2,3),(2,3,2)');
    $db->exec('INSERT INTO user_report_events VALUES (1,1,3),(2,2,2)');
    $db->exec('INSERT INTO bot_sites VALUES (1,1)');
    $db->exec("INSERT INTO bot_imports VALUES (1,10,1,'imported',NULL)");
    $db->exec('INSERT INTO leaderboard_cache VALUES (1,2),(2,3)');
    return $db;
}
function deleteFixtureUser(PDO $db, string $mode = 'preserve', int $id = 2, string $name = 'target'): array
{
    return (new UserDeletionService())->delete($db, $id, 1, $mode, $name, 'Test gerekçesi');
}

deletionCheck(class_exists(UserDeletionService::class), 'UserDeletionService must exist');
if (!class_exists(UserDeletionService::class)) {
    fwrite(STDERR, "FAIL: UserDeletionService must exist\n"); exit(1);
}

$db = deletionFixture();
$result = deleteFixtureUser($db);
$deletedId = (int) $result['replacement_user_id'];
deletionCheck((int) $db->query('SELECT COUNT(*) FROM users WHERE id=2')->fetchColumn() === 0, 'Preserve must physically remove account');
deletionCheck($deletedId > 4, 'Preserve must create separate replacement identity');
$system = $db->query('SELECT * FROM users WHERE id=' . $deletedId)->fetch(PDO::FETCH_ASSOC);
deletionCheck($system['username'] === 'Silinmiş Kullanıcı' && $system['status'] === 'inactive' && $system['deleted_at'] !== null && $system['password'] === '!', 'Replacement must be named and unable to authenticate');
deletionCheck((int) $system['public_profile'] === 0, 'Replacement profile must be private');
deletionCheck((int) $db->query('SELECT author_id FROM topics WHERE id=10')->fetchColumn() === $deletedId, 'Topic owner must transfer');
deletionCheck((int) $db->query('SELECT COUNT(*) FROM comments WHERE user_id=' . $deletedId)->fetchColumn() === 3, 'All own comments must transfer');
deletionCheck((int) $db->query('SELECT COUNT(*) FROM comment_media')->fetchColumn() === 3 && (int) $db->query('SELECT COUNT(*) FROM comment_edit_history')->fetchColumn() === 3, 'Retained comment attachments and edit history must survive');
deletionCheck((int) $db->query('SELECT user_id FROM media_files WHERE id=2')->fetchColumn() === $deletedId, 'Media on another author topic must remain available');
deletionCheck((int) $db->query('SELECT COUNT(*) FROM comments')->fetchColumn() === 6, 'Preserve must keep everyone comments');
deletionCheck((int) $db->query('SELECT parent_id FROM comments WHERE id=201')->fetchColumn() === 200, 'Preserve must keep reply structure');
deletionCheck($result['topics'] === 1 && $result['comments'] === 3, 'Preserve must return accurate counts');
deletionCheck($result['file_paths'] === ['uploads/profil/avatar.webp'], 'Preserve must only schedule avatar for cleanup');
deletionCheck((int) $db->query('SELECT COUNT(*) FROM user_group_members WHERE user_id=2')->fetchColumn() === 0, 'Membership without FK must be removed');
deletionCheck($db->query('SELECT assigned_by FROM user_group_members WHERE user_id=3')->fetchColumn() === null, 'Non-FK assignment actor must detach');
deletionCheck((int) $db->query('SELECT COUNT(*) FROM user_group_logs WHERE user_id=2')->fetchColumn() === 0 && $db->query('SELECT changed_by FROM user_group_logs WHERE id=2')->fetchColumn() === null, 'Group history must have no stale user references');
deletionCheck((int) $db->query('SELECT COUNT(*) FROM ban_appeals WHERE user_id=2')->fetchColumn() === 0 && (int) $db->query('SELECT COUNT(*) FROM ban_appeal_messages WHERE appeal_id=1')->fetchColumn() === 0, 'Appeals without FK must be removed with their messages');
deletionCheck($db->query('SELECT reviewed_by FROM ban_appeals WHERE id=2')->fetchColumn() === null, 'Appeal reviewer must detach');
deletionCheck($db->query('SELECT actor_user_id FROM notifications WHERE id=2')->fetchColumn() === null, 'Notification actor without FK must detach');
deletionCheck($db->query('SELECT user_id FROM security_events WHERE id=1')->fetchColumn() === null && $db->query('SELECT user_id FROM email_logs WHERE id=1')->fetchColumn() === null, 'Logs without FK must detach');
deletionCheck((int) $db->query('SELECT COUNT(*) FROM events_email_queue')->fetchColumn() === 0 && (int) $db->query('SELECT COUNT(*) FROM bulk_email_recipients')->fetchColumn() === 0, 'Queued emails for removed account must be removed');
deletionCheck((int) $db->query('SELECT reaction_count FROM comments WHERE id=201')->fetchColumn() === 1, 'Remaining reaction counts must refresh');
deletionCheck((int) $db->query('SELECT COUNT(*) FROM user_reports WHERE reported_user_id=2')->fetchColumn() === 0, 'Target reports must cascade');
deletionCheck((int) $db->query('SELECT COUNT(*) FROM activity_logs')->fetchColumn() === 1 && (int) $db->query('SELECT COUNT(*) FROM admin_action_log WHERE is_reversible=0')->fetchColumn() === 1, 'Deletion must be audited without reversible flag');
deletionCheck(!$db->inTransaction(), 'Owned transaction must finish');
deletionCheck(deletionRejects(fn () => deleteFixtureUser($db)), 'Already removed account must be rejected');
deletionCheck(deletionRejects(fn () => deleteFixtureUser($db, 'purge', $deletedId, 'Silinmiş Kullanıcı')), 'Replacement identity must be protected');
$second = deleteFixtureUser($db, 'preserve', 4, 'next');
deletionCheck($second['replacement_user_id'] === $deletedId, 'Replacement identity must be reused');

foreach (['preserve', 'purge'] as $mode) {
    $db = deletionFixture();
    $db->exec('INSERT INTO comment_mentions VALUES (1,201,2,3),(2,201,4,3),(3,200,3,2)');
    $db->exec('UPDATE comments SET mention_count=2 WHERE id=201');
    deleteFixtureUser($db, $mode);
    deletionCheck((int) $db->query('SELECT mention_count FROM comments WHERE id=201')->fetchColumn() === 1, "$mode: mention counts must refresh after user cascade");
}

$db = deletionFixture();
$result = deleteFixtureUser($db, 'purge');
deletionCheck((int) $db->query('SELECT COUNT(*) FROM users WHERE id=2')->fetchColumn() === 0 && (int) $db->query('SELECT COUNT(*) FROM topics WHERE author_id=2')->fetchColumn() === 0, 'Purge must remove account and authored topics');
deletionCheck((int) $db->query('SELECT COUNT(*) FROM comments WHERE user_id=2 OR topic_id=10')->fetchColumn() === 0, 'Purge must include other comments on removed topics');
deletionCheck((int) $db->query('SELECT COUNT(*) FROM comments WHERE id IN (201,203)')->fetchColumn() === 2, 'Replies on retained topics must survive');
deletionCheck($db->query('SELECT parent_id FROM comments WHERE id=201')->fetchColumn() === null && $db->query('SELECT parent_id FROM comments WHERE id=203')->fetchColumn() === null, 'Orphaned replies must become top-level comments');
deletionCheck((int) $db->query('SELECT comment_count FROM topics WHERE id=20')->fetchColumn() === 2, 'Remaining topic count must reflect visible comments');
deletionCheck((int) $db->query('SELECT total_comments FROM users WHERE id=3')->fetchColumn() === 1, 'Other author comment count must be corrected');
deletionCheck((int) $db->query('SELECT COUNT(*) FROM comment_media')->fetchColumn() === 1 && (int) $db->query('SELECT COUNT(*) FROM media_files')->fetchColumn() === 1, 'Purge must retain attachments associated with surviving content');
deletionCheck(in_array('uploads/konu/deleted.webp', $result['file_paths'], true) && !in_array('uploads/konu/retained.webp', $result['file_paths'], true) && !in_array('uploads/comments/shared.webp', $result['file_paths'], true), 'Purge must schedule only removed content files');
deletionCheck($db->query('SELECT status FROM bot_imports WHERE id=1')->fetchColumn() === 'preview' && $db->query('SELECT topic_id FROM bot_imports WHERE id=1')->fetchColumn() === null && (int) $db->query('SELECT total_imports FROM bot_sites WHERE id=1')->fetchColumn() === 0, 'Purge must reset bot import state and count');
deletionCheck($result['topics'] === 1 && $result['comments'] === 4, 'Purge summary must include all removed comments');

foreach ([['invalid', 2, 'target'], ['preserve', 1, 'admin'], ['preserve', 999, 'missing'], ['preserve', 2, 'wrong']] as [$mode, $id, $name]) {
    $db = deletionFixture();
    deletionCheck(deletionRejects(fn () => deleteFixtureUser($db, $mode, $id, $name)), 'Invalid request must fail: ' . $mode . '/' . $id . '/' . $name);
    deletionCheck((int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn() === 4 && (int) $db->query('SELECT COUNT(*) FROM comments')->fetchColumn() === 6, 'Rejected request must not modify data');
}
foreach ([[], ['users.edit'], ['users.delete', 'comments.delete'], ['users.delete', 'topics.delete']] as $permissions) {
    $GLOBALS['deletion_permissions'] = $permissions;
    $db = deletionFixture();
    deletionCheck(deletionRejects(fn () => deleteFixtureUser($db, 'purge')), 'Insufficient permissions must fail');
    deletionCheck((int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn() === 4, 'Permission failure must not create replacement');
}
$GLOBALS['deletion_permissions'] = ['users.delete'];
deletionCheck(!deletionRejects(fn () => deleteFixtureUser(deletionFixture())), 'Preserve must work with users.delete alone');
$GLOBALS['deletion_permissions'] = ['users.delete', 'topics.delete', 'comments.delete'];

foreach (['preserve', 'purge'] as $mode) {
    $db = deletionFixture();
    $db->exec("CREATE TRIGGER prevent_user_delete BEFORE DELETE ON users WHEN OLD.id=2 BEGIN SELECT RAISE(ABORT, 'forced failure'); END");
    deletionCheck(deletionRejects(fn () => deleteFixtureUser($db, $mode)), "$mode: storage failure must be reported");
    deletionCheck(!$db->inTransaction() && (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn() === 4 && (int) $db->query('SELECT author_id FROM topics WHERE id=10')->fetchColumn() === 2 && (int) $db->query('SELECT COUNT(*) FROM comments')->fetchColumn() === 6 && (int) $db->query('SELECT COUNT(*) FROM comment_media')->fetchColumn() === 3, "$mode: failure must roll back everything");
    $db->beginTransaction();
    $db->exec("UPDATE users SET username='caller-change' WHERE id=3");
    deletionCheck(deletionRejects(fn () => deleteFixtureUser($db, $mode)), "$mode: nested failure must be reported");
    deletionCheck($db->inTransaction() && $db->query('SELECT username FROM users WHERE id=3')->fetchColumn() === 'caller-change' && (int) $db->query('SELECT COUNT(*) FROM comments')->fetchColumn() === 6, "$mode: savepoint must preserve caller work");
    $db->rollBack();
    $db->exec('DROP TRIGGER prevent_user_delete');
    $db->beginTransaction();
    deleteFixtureUser($db, $mode);
    deletionCheck($db->inTransaction(), "$mode: service must not commit caller transaction");
    $db->rollBack();
    deletionCheck((int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn() === 4 && (int) $db->query('SELECT COUNT(*) FROM comments')->fetchColumn() === 6, "$mode: caller rollback must restore data");
}

foreach (['', str_repeat('x', 1001)] as $reason) {
    $db = deletionFixture();
    deletionCheck(deletionRejects(fn () => (new UserDeletionService())->delete($db, 2, 1, 'preserve', 'target', $reason)), 'Invalid deletion reason must fail');
}
$db = deletionFixture();
$db->exec("UPDATE users SET status='inactive' WHERE id=1");
deletionCheck(deletionRejects(fn () => deleteFixtureUser($db)), 'Inactive actor must fail');
$db = deletionFixture();
$db->exec("UPDATE users SET email='deleted-user@system.invalid' WHERE id=4");
deletionCheck(deletionRejects(fn () => deleteFixtureUser($db)), 'Existing account must not be reused as replacement');
deletionCheck((int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn() === 4, 'Replacement collision must not modify accounts');
$db = deletionFixture();
$db->exec("CREATE TRIGGER prevent_audit BEFORE INSERT ON activity_logs BEGIN SELECT RAISE(ABORT, 'audit failure'); END");
deletionCheck(deletionRejects(fn () => deleteFixtureUser($db, 'purge')), 'Audit failure must abort deletion');
deletionCheck((int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn() === 4 && (int) $db->query('SELECT COUNT(*) FROM comments')->fetchColumn() === 6, 'Audit failure must restore account and content');
$db = deletionFixture();
$db->exec("CREATE TRIGGER ignore_user_delete BEFORE DELETE ON users WHEN OLD.id=2 BEGIN SELECT RAISE(IGNORE); END");
deletionCheck(deletionRejects(fn () => deleteFixtureUser($db, 'purge')), 'A deletion ignored by storage must fail');
deletionCheck((int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn() === 4 && (int) $db->query('SELECT COUNT(*) FROM comments')->fetchColumn() === 6, 'An ignored account deletion must restore content');

$db = deletionFixture();
$db->exec('PRAGMA foreign_keys = OFF');
foreach (['comment_media', 'comment_edit_history', 'comment_reactions', 'media_files', 'user_group_members', 'user_group_logs', 'ban_appeals', 'ban_appeal_messages', 'notifications', 'security_events', 'email_logs', 'events_email_queue', 'bulk_email_recipients', 'user_reports', 'user_report_events', 'bot_sites', 'bot_imports', 'leaderboard_cache', 'admin_action_log'] as $table) { $db->exec('DROP TABLE ' . $table); }
$db->exec('PRAGMA foreign_keys = ON');
deletionCheck(!deletionRejects(fn () => deleteFixtureUser($db, 'purge')), 'Absent optional modules must not prevent deletion');

$db = deletionFixture();
$db->exec("UPDATE topics SET deleted_at='2026-01-01', status='draft' WHERE id=10");
$db->exec("UPDATE comments SET deleted_at='2026-01-01' WHERE id=200");
$result = deleteFixtureUser($db, 'purge');
deletionCheck($result['topics'] === 1 && $result['comments'] === 4 && (int) $db->query('SELECT COUNT(*) FROM topics WHERE id=10')->fetchColumn() === 0, 'Purge must include drafts and previously soft-deleted content');

// Only newly created fixture files are touched; production media is never used.
$testDirectory = dirname(__DIR__) . '/uploads/.user-deletion-test-' . bin2hex(random_bytes(6));
mkdir($testDirectory . '/thumbs', 0777, true);
$relative = 'uploads/' . basename($testDirectory);
$outside = __DIR__ . '/.user-deletion-outside-' . bin2hex(random_bytes(6));
file_put_contents($outside, 'fixture outside uploads');
$fixtureFiles = ['removed.webp', 'shared_file.webp', 'inline.webp', 'page-inline.webp', 'og-image.webp', 'root-query.webp', 'windows.webp', 'query.webp', 'traversal.webp', 'remote.webp', 'prefix.webp', 'transaction.webp'];
try {
    foreach ($fixtureFiles as $name) { file_put_contents($testDirectory . '/' . $name, 'fixture'); }
    file_put_contents($testDirectory . '/thumbs/removed.webp', 'thumbnail');
    file_put_contents($testDirectory . '/thumbs/prefix.webp', 'shared thumbnail');
    $db = deletionFixture();
    $insert = $db->prepare('INSERT INTO media_files VALUES (3,20,3,?)');
    $insert->execute(['/turkmod/' . $relative . '/shared_file.webp']);
    $db->prepare('INSERT INTO media_files VALUES (4,20,3,?)')->execute([$relative . '/thumbs/prefix.webp']);
    $db->prepare('INSERT INTO media_files VALUES (5,20,3,?)')->execute([str_replace('/', '\\', dirname(__DIR__) . '/' . $relative . '/windows.webp')]);
    $db->prepare('INSERT INTO media_files VALUES (6,20,3,?)')->execute(['https://example.test/turkmod/' . $relative . '/query.webp?cache=1']);
    $db->prepare('INSERT INTO media_files VALUES (7,20,3,?)')->execute([$relative . '/root-query.webp?cache=1']);
    $db->prepare('UPDATE comments SET body=? WHERE id=201')->execute(['<p><img src="/turkmod/' . $relative . '/inline.webp"></p>']);
    $db->prepare('INSERT INTO static_pages VALUES (1,?,?)')->execute(['<img src="/turkmod/' . $relative . '/page-inline.webp">', $relative . '/og-image.webp']);
    UserDeletionFiles::cleanup($db, [$relative . '/removed.webp', $relative . '/shared_file.webp', $relative . '/inline.webp', $relative . '/page-inline.webp', $relative . '/og-image.webp', $relative . '/root-query.webp', $relative . '/windows.webp', $relative . '/query.webp', $outside, $relative . '/../' . basename($testDirectory) . '/traversal.webp', 'https://external.example/' . $relative . '/remote.webp', '/turkmod/' . $relative . '/prefix.webp'], '/turkmod');
    deletionCheck(!is_file($testDirectory . '/removed.webp') && !is_file($testDirectory . '/thumbs/removed.webp'), 'Unreferenced local media and thumbnails must be removed');
    deletionCheck(is_file($testDirectory . '/shared_file.webp'), 'Shared media referenced under base URI must be retained');
    deletionCheck(is_file($testDirectory . '/windows.webp') && is_file($testDirectory . '/query.webp'), 'Shared media referenced by legacy Windows paths or query URLs must remain');
    deletionCheck(is_file($testDirectory . '/inline.webp'), 'Media embedded in surviving comment HTML must remain');
    deletionCheck(is_file($testDirectory . '/page-inline.webp') && is_file($testDirectory . '/og-image.webp'), 'Static page body and social preview images must remain');
    deletionCheck(is_file($testDirectory . '/root-query.webp'), 'Relative media references with query strings must remain');
    deletionCheck(is_file($outside) && is_file($testDirectory . '/traversal.webp') && is_file($testDirectory . '/remote.webp'), 'Absolute, traversal and remote paths must be rejected');
    deletionCheck(!is_file($testDirectory . '/prefix.webp'), 'Local base URI prefix must resolve correctly');
    deletionCheck(is_file($testDirectory . '/thumbs/prefix.webp'), 'A thumbnail referenced by surviving content must remain');
    $db->beginTransaction();
    deletionCheck(deletionRejects(fn () => UserDeletionFiles::cleanup($db, [$relative . '/transaction.webp'])), 'File cleanup must refuse an uncommitted transaction');
    deletionCheck(is_file($testDirectory . '/transaction.webp'), 'Uncommitted cleanup must not remove files');
    $db->rollBack();
} finally {
    foreach ($fixtureFiles as $name) { if (is_file($testDirectory . '/' . $name)) { unlink($testDirectory . '/' . $name); } }
    if (is_file($testDirectory . '/thumbs/removed.webp')) { unlink($testDirectory . '/thumbs/removed.webp'); }
    if (is_file($testDirectory . '/thumbs/prefix.webp')) { unlink($testDirectory . '/thumbs/prefix.webp'); }
    rmdir($testDirectory . '/thumbs');
    rmdir($testDirectory);
    if (is_file($outside)) { unlink($outside); }
}

foreach ($failures as $failure) { fwrite(STDERR, "FAIL: $failure\n"); }
echo sprintf("User deletion: %d checks, %d failures\n", $checks, count($failures));
exit($failures === [] ? 0 : 1);
