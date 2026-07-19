ALTER TABLE `users` ADD COLUMN `timezone` VARCHAR(64) NOT NULL DEFAULT 'Asia/Shanghai' AFTER `session_version`;

ALTER TABLE `memory_cards`
    ADD COLUMN `first_reviewed_at` DATETIME NULL AFTER `next_review_at`,
    ADD COLUMN `last_reviewed_at` DATETIME NULL AFTER `first_reviewed_at`,
    ADD COLUMN `review_count` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `last_reviewed_at`;

ALTER TABLE `review_events`
    ADD COLUMN `idempotency_key` VARCHAR(128) NULL AFTER `memory_card_id`,
    ADD COLUMN `request_hash` CHAR(64) NULL AFTER `idempotency_key`,
    ADD COLUMN `mode` VARCHAR(32) NULL AFTER `request_hash`,
    ADD COLUMN `answer_text` TEXT NULL AFTER `mode`,
    ADD COLUMN `normalized_answer` TEXT NULL AFTER `answer_text`,
    ADD COLUMN `is_correct` TINYINT(1) NOT NULL DEFAULT 0 AFTER `normalized_answer`,
    ADD COLUMN `difficulty` VARCHAR(16) NULL AFTER `is_correct`,
    ADD COLUMN `effective_rating` VARCHAR(16) NULL AFTER `difficulty`,
    ADD COLUMN `previous_due_at` DATETIME NULL AFTER `next_stage`,
    ADD COLUMN `next_due_at` DATETIME NULL AFTER `previous_due_at`,
    ADD COLUMN `response_ms` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `next_due_at`,
    ADD COLUMN `xp_awarded` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `response_ms`,
    ADD COLUMN `response_payload` JSON NULL AFTER `xp_awarded`;

UPDATE `review_events` SET
    `idempotency_key` = CONCAT('legacy-', `id`),
    `request_hash` = SHA2(CONCAT('legacy-review-', `id`), 256),
    `mode` = 'zh_to_en', `answer_text` = '', `normalized_answer` = '',
    `difficulty` = 'good', `effective_rating` = `rating`,
    `next_due_at` = `reviewed_at`, `response_payload` = JSON_OBJECT()
WHERE `idempotency_key` IS NULL;

ALTER TABLE `review_events`
    MODIFY `idempotency_key` VARCHAR(128) NOT NULL,
    MODIFY `request_hash` CHAR(64) NOT NULL,
    MODIFY `mode` VARCHAR(32) NOT NULL,
    MODIFY `answer_text` TEXT NOT NULL,
    MODIFY `normalized_answer` TEXT NOT NULL,
    MODIFY `difficulty` VARCHAR(16) NOT NULL,
    MODIFY `effective_rating` VARCHAR(16) NOT NULL,
    MODIFY `next_due_at` DATETIME NOT NULL,
    MODIFY `response_payload` JSON NOT NULL,
    ADD UNIQUE KEY `review_events_user_idempotency_unique` (`user_id`, `idempotency_key`);

ALTER TABLE `daily_learning_stats`
    ADD COLUMN `xp_earned` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `study_seconds`,
    ADD COLUMN `current_correct_streak` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `xp_earned`,
    ADD COLUMN `best_correct_streak` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `current_correct_streak`;
