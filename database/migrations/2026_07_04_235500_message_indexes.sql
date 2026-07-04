-- Up
CREATE INDEX IF NOT EXISTS message_messages_thread_id_index ON message_messages (thread_id, id);
CREATE INDEX IF NOT EXISTS message_messages_thread_created_index ON message_messages (thread_id, created_at);
CREATE INDEX IF NOT EXISTS message_messages_sender_created_index ON message_messages (sender_user_id, created_at);
CREATE INDEX IF NOT EXISTS message_participants_user_read_index ON message_thread_participants (user_id, last_read_at);
CREATE INDEX IF NOT EXISTS message_participants_thread_read_index ON message_thread_participants (thread_id, last_read_message_id);

-- Down
DROP INDEX IF EXISTS message_messages_thread_id_index ON message_messages;
DROP INDEX IF EXISTS message_messages_thread_created_index ON message_messages;
DROP INDEX IF EXISTS message_messages_sender_created_index ON message_messages;
DROP INDEX IF EXISTS message_participants_user_read_index ON message_thread_participants;
DROP INDEX IF EXISTS message_participants_thread_read_index ON message_thread_participants;
