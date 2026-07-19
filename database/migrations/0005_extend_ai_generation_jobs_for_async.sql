ALTER TABLE `ai_generation_jobs`
    ADD COLUMN `idempotency_key` VARCHAR(128) NULL AFTER `memory_card_id`,
    ADD COLUMN `request_hash` CHAR(64) NULL AFTER `idempotency_key`,
    ADD COLUMN `failure_type` VARCHAR(64) NULL AFTER `error_message`,
    ADD COLUMN `parent_job_id` BIGINT UNSIGNED NULL AFTER `failure_type`,
    ADD COLUMN `dispatched_at` DATETIME NULL AFTER `attempts`;

UPDATE `ai_generation_jobs`
SET
    `idempotency_key` = CONCAT('legacy-', `id`),
    `request_hash` = SHA2(CAST(`request_payload` AS CHAR), 256)
WHERE `idempotency_key` IS NULL OR `request_hash` IS NULL;

ALTER TABLE `ai_generation_jobs`
    MODIFY COLUMN `idempotency_key` VARCHAR(128) NOT NULL,
    MODIFY COLUMN `request_hash` CHAR(64) NOT NULL,
    ADD UNIQUE KEY `ai_jobs_user_idempotency_unique` (`user_id`, `idempotency_key`),
    ADD KEY `ai_jobs_parent_index` (`parent_job_id`),
    ADD CONSTRAINT `ai_jobs_parent_fk`
        FOREIGN KEY (`parent_job_id`) REFERENCES `ai_generation_jobs` (`id`) ON DELETE SET NULL;
