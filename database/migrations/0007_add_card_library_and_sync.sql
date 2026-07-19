ALTER TABLE `users`
    ADD COLUMN `sync_version` BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER `last_login_at`,
    ADD COLUMN `session_version` BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER `sync_version`;

ALTER TABLE `refresh_tokens`
    ADD COLUMN `session_version` BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER `device_name`,
    ADD KEY `refresh_tokens_user_session_index` (`user_id`, `session_version`, `revoked_at`);

ALTER TABLE `memory_cards`
    ADD COLUMN `sync_version` BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER `user_id`,
    ADD COLUMN `content_version` BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER `sync_version`,
    ADD COLUMN `is_favorite` TINYINT(1) NOT NULL DEFAULT 0 AFTER `memory_style`,
    ADD COLUMN `deleted_at` DATETIME NULL AFTER `review_stage`,
    ADD KEY `memory_cards_user_library_index` (`user_id`, `deleted_at`, `created_at`, `id`),
    ADD KEY `memory_cards_user_favorite_index` (`user_id`, `deleted_at`, `is_favorite`, `created_at`, `id`),
    ADD KEY `memory_cards_user_sync_index` (`user_id`, `sync_version`);

ALTER TABLE `ai_generation_jobs`
    ADD COLUMN `operation` VARCHAR(32) NOT NULL DEFAULT 'create' AFTER `request_hash`,
    ADD COLUMN `pending_card_payload` JSON NULL AFTER `provider_payload`;

CREATE TABLE IF NOT EXISTS `tags` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(160) NOT NULL,
    `normalized_name` VARCHAR(160) NOT NULL,
    `sync_version` BIGINT UNSIGNED NOT NULL,
    `deleted_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `tags_user_normalized_unique` (`user_id`, `normalized_name`),
    KEY `tags_user_active_index` (`user_id`, `deleted_at`, `normalized_name`),
    KEY `tags_user_sync_index` (`user_id`, `sync_version`),
    CONSTRAINT `tags_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `memory_card_tags` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `memory_card_id` BIGINT UNSIGNED NOT NULL,
    `tag_id` BIGINT UNSIGNED NOT NULL,
    `sync_version` BIGINT UNSIGNED NOT NULL,
    `deleted_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `memory_card_tags_card_tag_unique` (`memory_card_id`, `tag_id`),
    KEY `memory_card_tags_user_card_index` (`user_id`, `memory_card_id`, `deleted_at`),
    KEY `memory_card_tags_user_sync_index` (`user_id`, `sync_version`),
    CONSTRAINT `memory_card_tags_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `memory_card_tags_card_fk` FOREIGN KEY (`memory_card_id`) REFERENCES `memory_cards` (`id`) ON DELETE CASCADE,
    CONSTRAINT `memory_card_tags_tag_fk` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE `memory_cards` AS `card`
JOIN (
    SELECT
        `id`,
        ROW_NUMBER() OVER (PARTITION BY `user_id` ORDER BY `id`) AS `backfill_version`
    FROM `memory_cards`
) AS `ranked` ON `ranked`.`id` = `card`.`id`
SET `card`.`sync_version` = `ranked`.`backfill_version`;

UPDATE `users` AS `user`
LEFT JOIN (
    SELECT `user_id`, MAX(`sync_version`) AS `max_sync_version`
    FROM `memory_cards`
    GROUP BY `user_id`
) AS `versions` ON `versions`.`user_id` = `user`.`id`
SET `user`.`sync_version` = COALESCE(`versions`.`max_sync_version`, 0);
