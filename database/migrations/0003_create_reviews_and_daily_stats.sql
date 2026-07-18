CREATE TABLE IF NOT EXISTS `review_events` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `memory_card_id` BIGINT UNSIGNED NOT NULL,
    `rating` VARCHAR(16) NOT NULL,
    `previous_stage` SMALLINT UNSIGNED NOT NULL,
    `next_stage` SMALLINT UNSIGNED NOT NULL,
    `reviewed_at` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `review_events_user_reviewed_index` (`user_id`, `reviewed_at`),
    KEY `review_events_card_reviewed_index` (`memory_card_id`, `reviewed_at`),
    CONSTRAINT `review_events_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `review_events_card_fk` FOREIGN KEY (`memory_card_id`) REFERENCES `memory_cards` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `daily_learning_stats` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `stat_date` DATE NOT NULL,
    `cards_created` INT UNSIGNED NOT NULL DEFAULT 0,
    `reviews_completed` INT UNSIGNED NOT NULL DEFAULT 0,
    `correct_reviews` INT UNSIGNED NOT NULL DEFAULT 0,
    `study_seconds` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `daily_stats_user_date_unique` (`user_id`, `stat_date`),
    CONSTRAINT `daily_stats_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
