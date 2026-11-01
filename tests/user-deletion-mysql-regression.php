<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/includes/autoloader.php';
require_once dirname(__DIR__) . '/includes/src/Engine/Users/Support/users-helpers.php';

use App\Engine\Users\UserDeletionService;

// Clones schema only. All mutations target a newly created, disposable database.
$source = new PDO(getenv('USER_DELETION_TEST_DSN') ?: 'mysql:host=127.0.0.1;dbname=turkmod;charset=utf8mb4', getenv('USER_DELETION_TEST_USER') ?: 'root', getenv('USER_DELETION_TEST_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$schema = [];
foreach ($source->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM) as $table) {
    $name = str_replace('`', '``', (string) $table[0]);
    $schema[] = $source->query('SHOW CREATE TABLE `' . $name . '`')->fetch(PDO::FETCH_NUM)[1];
}
$originalUsers = (int) $source->query('SELECT COUNT(*) FROM users')->fetchColumn();
$database = 'turkmod_user_deletion_test_' . bin2hex(random_bytes(8));
if (!preg_match('/^turkmod_user_deletion_test_[a-f0-9]{16}$/D', $database)) { throw new RuntimeException('Invalid test database name'); }
$source->exec('CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$checks = 0;
$failures = [];
function mysqlDeletionCheck(bool $condition, string $label): void
{
    $GLOBALS['checks']++;
    if (!$condition) { $GLOBALS['failures'][] = $label; }
}
try {
    // This is a separate connection so source always retains its original database.
    $dsn = preg_replace('/dbname=[^;]+/', 'dbname=' . $database, getenv('USER_DELETION_TEST_DSN') ?: 'mysql:host=127.0.0.1;dbname=turkmod;charset=utf8mb4');
    $db = new PDO($dsn, getenv('USER_DELETION_TEST_USER') ?: 'root', getenv('USER_DELETION_TEST_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
    $db->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach ($schema as $sql) { $db->exec($sql); }
    $db->exec('SET FOREIGN_KEY_CHECKS=1');
    $db->exec("INSERT INTO users (id,username,email,password) VALUES (1,'admin','admin@example.test','!'),(2,'target','target@example.test','!'),(3,'other','other@example.test','!')");
    $db->exec("INSERT INTO user_groups (id,name,slug,is_active) VALUES (1,'Admin','admin',1)");
    $db->exec('INSERT INTO user_group_members (user_id,group_id,is_primary) VALUES (1,1,1),(2,1,1)');
    $db->exec("INSERT INTO categories (id,name,slug) VALUES (1,'Fixture','fixture')");
    $db->exec("INSERT INTO topics (id,category_id,author_id,title,slug,status,comment_count) VALUES (10,1,2,'Removed','removed','published',2),(20,1,3,'Retained','retained','published',2)");
    $db->exec("INSERT INTO comments (id,topic_id,user_id,parent_id,body) VALUES (100,10,2,NULL,'own'),(101,10,3,100,'other'),(200,20,2,NULL,'own'),(201,20,3,200,'reply')");
    $db->exec("INSERT INTO comment_media (comment_id,user_id,file_path,file_type,file_size) VALUES (200,2,'uploads/fixture.webp','image/webp',1)");
    $db->exec("INSERT INTO comment_edit_history (comment_id,user_id,old_body,new_body) VALUES (200,2,'old','new')");
    $db->exec("INSERT INTO message_threads (id,thread_key) VALUES (1,'2:3')");
    $db->exec('INSERT INTO message_thread_participants (thread_id,user_id) VALUES (1,2),(1,3)');
    $db->exec("INSERT INTO message_messages (id,thread_id,sender_user_id,body) VALUES (1,1,3,'surviving message'),(2,1,2,'removed message')");
    $db->exec('UPDATE message_threads SET last_message_id=2 WHERE id=1');
    $db->beginTransaction();
    $db->exec("INSERT INTO users (id,username,email,password) VALUES (4,'bulk-target','bulk@example.test','!')");
    $db->exec("INSERT INTO user_group_permission_overrides (user_id, permission_key, permission_value) VALUES (1,'users.delete',0)");
    mysqlDeletionCheck(userHasPermission($db, 1, 'users.edit') && !userHasPermission($db, 1, 'users.delete'), 'Fixture actor must have edit permission without delete permission');
    $bulkError = usersApplyBulkAction($db, 'delete', [4], 1, [], [1]);
    mysqlDeletionCheck($bulkError !== '' && (int) $db->query('SELECT COUNT(*) FROM users WHERE id=4')->fetchColumn() === 1, 'Legacy bulk deletion must not bypass individual confirmation and permissions');
    $denied = false;
    try { (new UserDeletionService())->delete($db, 4, 1, 'preserve', 'bulk-target', 'Denied deletion'); } catch (DomainException) { $denied = true; }
    mysqlDeletionCheck($denied && (int) $db->query('SELECT COUNT(*) FROM users WHERE id=4')->fetchColumn() === 1, 'Real permission overrides must reject deletion without modifying account');
    $db->rollBack();
    foreach (['preserve', 'purge'] as $mode) {
        $db->beginTransaction();
        $result = (new UserDeletionService())->delete($db, 2, 1, $mode, 'target', 'MySQL fixture test');
        mysqlDeletionCheck((int) $db->query('SELECT COUNT(*) FROM users WHERE id=2')->fetchColumn() === 0, "$mode: account removed");
        mysqlDeletionCheck($db->inTransaction(), "$mode: caller transaction preserved");
        mysqlDeletionCheck((int) $db->query('SELECT COUNT(*) FROM user_group_members WHERE user_id=2')->fetchColumn() === 0, "$mode: unconstrained memberships removed");
        mysqlDeletionCheck((int) $db->query('SELECT last_message_id FROM message_threads WHERE id=1')->fetchColumn() === 1, "$mode: message pointer repaired");
        mysqlDeletionCheck((int) $db->query('SELECT COUNT(*) FROM activity_logs')->fetchColumn() === 1, "$mode: deletion logged");
        if ($mode === 'preserve') {
            mysqlDeletionCheck((int) $db->query('SELECT author_id FROM topics WHERE id=10')->fetchColumn() === $result['replacement_user_id'], 'Preserve: topic FK transferred');
            mysqlDeletionCheck((int) $db->query('SELECT COUNT(*) FROM comments')->fetchColumn() === 4 && (int) $db->query('SELECT COUNT(*) FROM comment_media')->fetchColumn() === 1 && (int) $db->query('SELECT COUNT(*) FROM comment_edit_history')->fetchColumn() === 1, 'Preserve: comments, attachments and edits survive FK cascades');
        } else {
            mysqlDeletionCheck((int) $db->query('SELECT COUNT(*) FROM topics')->fetchColumn() === 1 && (int) $db->query('SELECT COUNT(*) FROM comments')->fetchColumn() === 1, 'Purge: topic cascade removes only intended content');
            mysqlDeletionCheck($db->query('SELECT parent_id FROM comments WHERE id=201')->fetchColumn() === null && (int) $db->query('SELECT comment_count FROM topics WHERE id=20')->fetchColumn() === 1, 'Purge: surviving reply and topic counter correct');
        }
        mysqlDeletionCheck(usersGetById($db, $result['replacement_user_id']) === null, "$mode: system identity must be unavailable to account editing");
        $second = (new UserDeletionService())->delete($db, 3, 1, 'preserve', 'other', 'Replacement reuse test');
        mysqlDeletionCheck($second['replacement_user_id'] === $result['replacement_user_id'], "$mode: a second deletion must safely reuse the system identity");
        $db->rollBack();
        mysqlDeletionCheck((int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn() === 3 && (int) $db->query('SELECT COUNT(*) FROM comments')->fetchColumn() === 4, "$mode: rollback restores everything");
    }
    $db->exec("CREATE TRIGGER reject_user_delete BEFORE DELETE ON users FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='forced deletion failure'");
    $rejected = false;
    try { (new UserDeletionService())->delete($db, 2, 1, 'purge', 'target', 'Rollback test'); } catch (Throwable) { $rejected = true; }
    mysqlDeletionCheck($rejected && !$db->inTransaction() && (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn() === 3 && (int) $db->query('SELECT COUNT(*) FROM comments')->fetchColumn() === 4, 'Owned MySQL transaction restores all content after storage failure');
    $db->exec('DROP TRIGGER reject_user_delete');
    (new UserDeletionService())->delete($db, 2, 1, 'purge', 'target', 'Commit test');
    mysqlDeletionCheck(!$db->inTransaction() && (int) $db->query('SELECT COUNT(*) FROM comments')->fetchColumn() === 1, 'Owned MySQL transaction commits complete deletion');
    mysqlDeletionCheck((int) $source->query('SELECT COUNT(*) FROM users')->fetchColumn() === $originalUsers, 'Source users are untouched');
} finally {
    if (isset($db) && $db->inTransaction()) { $db->rollBack(); }
    $source->exec('DROP DATABASE `' . $database . '`');
}
foreach ($failures as $failure) { fwrite(STDERR, "FAIL: $failure\n"); }
echo sprintf("MySQL user deletion: %d checks, %d failures\n", $checks, count($failures));
exit($failures === [] ? 0 : 1);
