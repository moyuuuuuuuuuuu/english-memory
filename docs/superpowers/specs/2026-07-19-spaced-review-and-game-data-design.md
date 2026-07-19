# Spaced Review And Game Data Design

**Date:** 2026-07-19

**Status:** Approved for implementation planning

## Goal

Build the Stage 6 backend contract for deterministic spaced repetition, three review modes, idempotent answer submission, timezone-correct daily statistics, and game-style feedback that never changes scheduling decisions.

## Scope

This stage includes:

- a due-first daily review queue plus at most 20 new cards per user-local day;
- image recall, listening/spelling, and Chinese-to-English review modes;
- server-side answer normalization and correctness evaluation;
- four scheduling outcomes: `again`, `hard`, `good`, and `easy`;
- idempotent answer submission and conflict detection;
- daily and lifetime review statistics, XP, consecutive-correct feedback, level, and learning-day streak;
- a persisted IANA timezone with `Asia/Shanghai` as the default.

This stage does not include mobile screens, offline queue implementation, adaptive FSRS parameters, user-configurable daily limits, leaderboards, achievements, social features, or AI-generated alternative answers.

## Architectural Boundaries

Routes call one thin Controller. Each Controller parses HTTP values and calls one Business. Businesses own ownership checks, validation, transactions, scheduling, idempotency, and statistics. Models remain persistence records and reusable scopes. Typed Entities carry review questions, schedule decisions, submissions, and response payloads. Enums live under `app/common/enums`.

The scheduler is deterministic application logic in `app/businesses`; it has no HTTP, database, timezone-global, XP, or provider dependencies. A caller passes explicit current time and previous scheduling state, which keeps boundary tests reproducible.

No third-party service is required in this stage. System pronunciation remains a Stage 8 mobile responsibility; the backend supplies the English prompt needed by the device speech engine.

## Persistence Changes

Add one forward-only migration, `0008_add_review_scheduling_and_game_data.sql`.

### Users

Add `timezone VARCHAR(64) NOT NULL DEFAULT 'Asia/Shanghai'`. Only canonical IANA identifiers accepted by PHP `DateTimeZone` may be persisted. Timezone changes affect future local-day grouping and never rewrite historical `stat_date` values.

### Memory cards

Keep `review_stage` and `next_review_at`. Add `first_reviewed_at`, `last_reviewed_at`, and `review_count` so new-card detection and lifetime summaries do not depend on scanning all events. A completed, active card with `first_reviewed_at IS NULL` is new. A card with `next_review_at <= now_utc` is due.

The existing user-level sync sequence remains authoritative. A successful unique review submission locks the user and card, allocates one sync version, and writes the card scheduling mutation with that version. Review event and statistics rows are not separate sync resources; clients receive updated card scheduling through the existing sync feed.

### Review events

Extend `review_events` with:

- `idempotency_key VARCHAR(128) NOT NULL`;
- `request_hash CHAR(64) NOT NULL`;
- `mode VARCHAR(32) NOT NULL`;
- `answer_text TEXT NOT NULL`;
- `normalized_answer TEXT NOT NULL`;
- `is_correct TINYINT(1) NOT NULL`;
- `difficulty VARCHAR(16) NOT NULL`;
- `effective_rating VARCHAR(16) NOT NULL`;
- `previous_due_at DATETIME NULL`;
- `next_due_at DATETIME NOT NULL`;
- `response_ms INT UNSIGNED NOT NULL DEFAULT 0`;
- `xp_awarded INT UNSIGNED NOT NULL DEFAULT 0`.
- `response_payload JSON NOT NULL` containing the original safe submission result for exact idempotent replay.

Add a unique key on `(user_id, idempotency_key)`. Preserve existing `rating` as the effective scheduling outcome for compatibility; `effective_rating` and `rating` contain the same value for new rows. `previous_stage` and `next_stage` remain the auditable schedule transition.

### Daily learning statistics

Extend `daily_learning_stats` with `xp_earned`, `current_correct_streak`, and `best_correct_streak`. The existing unique `(user_id, stat_date)` key remains the local-day aggregate boundary. `reviews_completed` and `correct_reviews` count only unique committed review events.

## Scheduling Algorithm

Use this fixed stage table:

| Stage | Interval |
|---:|---:|
| 0 | 10 minutes |
| 1 | 1 day |
| 2 | 3 days |
| 3 | 7 days |
| 4 | 14 days |
| 5 | 30 days |
| 6 | 60 days |
| 7 | 120 days |

The first committed review starts from a conceptual stage of `-1` even though the stored pre-review `review_stage` is `0`.

- `again`: next stage `0`;
- `hard`: next stage `0` for a new card, otherwise keep the previous stage;
- `good`: advance one stage, with a new card entering stage `1`;
- `easy`: advance two stages, with a new card entering stage `2`;
- stage values are capped at `7`.

`next_review_at` is calculated from the server's actual submission instant in UTC, not from the old due time. This prevents an overdue card from remaining immediately overdue. Scheduling has no dependency on XP, level, correct streak, response time, review mode, or daily goal progress.

## Daily Review Queue

`GET /api/reviews/today` uses the authenticated user's persisted timezone to calculate the local date and returns:

1. all completed active cards whose `next_review_at` is due, ordered by `next_review_at`, then ID;
2. up to 20 completed active cards with `first_reviewed_at IS NULL`, ordered by creation time, then ID;
3. no duplicate card if inconsistent legacy data makes a card match both groups.

Completed means the latest AI generation job is `completed`. Deleted cards and cards with queued, text-generating, image-generating, or failed latest jobs are excluded.

Each item contains the safe shared card view plus:

- `available_modes`: `image_recall` when an application-owned image exists, and always `listening_spelling` plus `zh_to_en` when the card has a usable expected English answer;
- `recommended_mode`: a deterministic rotation based on `review_count % available_mode_count`;
- `expected_answer_kind`: `word` or `sentence`, without returning a separate hidden answer field.

The existing card payload already contains visible learning content, so this is a practice API rather than an examination-security boundary.

## Answer Evaluation

`POST /api/reviews/{cardId}/answer` requires an `Idempotency-Key` header and body fields:

- `mode`: one of the three allowed modes and available for this card;
- `answer`: non-empty UTF-8 text with a bounded length;
- `difficulty`: `hard`, `good`, or `easy`;
- `response_ms`: integer from `0` through `3600000`.

The server derives the expected English answer from `normalized_text`, falling back to `word` in `card_payload`. Both submitted and expected answers are normalized with Unicode trim, whitespace collapse, lowercase conversion, and removal of common ASCII and Unicode terminal punctuation. Internal apostrophes and hyphens remain meaningful. Exact normalized equality determines correctness.

An incorrect answer always produces effective rating `again`. A correct answer preserves the submitted `hard`, `good`, or `easy`. The response returns correctness, expected answer for feedback, effective rating, previous and next stages, next due time, XP awarded, daily progress, current correct streak, and updated card sync/content metadata.

## Idempotency And Transactions

The request hash includes card ID, mode, normalized answer, difficulty, and response time. Reusing an idempotency key with the same hash returns the event's stored safe `response_payload` without mutating the card or statistics. Reusing it with different data returns `IDEMPOTENCY_CONFLICT`.

For a new submission, one transaction:

1. locks the user, then the owned active card;
2. verifies the card is generated and reviewable;
3. rechecks the idempotency key under the unique constraint;
4. evaluates the answer and calculates the schedule using one captured UTC instant;
5. allocates one user sync version and updates the card;
6. inserts the review event;
7. upserts the user's local-date daily statistic;
8. commits one response snapshot sufficient for stable replay.

Concurrent submissions for the same card serialize on the card row. Different idempotency keys are distinct reviews and both are applied in commit order.

## XP, Level, And Streak Feedback

XP is presentation feedback only:

- incorrect/`again`: 2 XP;
- correct `hard`: 8 XP;
- correct `good`: 10 XP;
- correct `easy`: 12 XP.

Level is `1 + floor(lifetime_xp / 500)`. Current consecutive-correct count resets to zero on an incorrect review and increments on a correct review. Best consecutive-correct count is the maximum stored daily value and lifetime query result.

Learning-day streak counts consecutive user-local dates, ending today when today has at least one review, otherwise ending yesterday. A day qualifies when `reviews_completed > 0`. Timezone changes do not regroup prior daily rows.

## Statistics Overview

`GET /api/stats/overview` returns:

- persisted timezone and local date;
- today's completed reviews, correct reviews, accuracy, XP, current/best correct streak;
- daily goal `20` and capped goal progress;
- lifetime reviews, correct reviews, accuracy, XP, level, and distinct cards reviewed;
- current learning-day streak;
- counts of due and new cards using the same eligibility rules as the daily queue.

## Timezone Update

`PATCH /api/auth/me/timezone` accepts `timezone`. It validates the IANA identifier and updates the user without replacing the authentication session. The response returns the current authenticated user representation including timezone. Invalid identifiers return `INVALID_TIMEZONE`.

## Errors

Add stable business codes:

- `REVIEW_NOT_AVAILABLE` for an owned card that cannot currently be reviewed;
- `INVALID_REVIEW_MODE` for unsupported or unavailable modes;
- `INVALID_TIMEZONE` for invalid IANA identifiers.

Continue using `CARD_NOT_FOUND` for missing, foreign, or deleted cards; `IDEMPOTENCY_KEY_REQUIRED`, `IDEMPOTENCY_CONFLICT`, and `INVALID_INPUT` retain their existing meanings. Public responses never expose another user's resource existence or raw exceptions.

## Testing Strategy

Migration contract tests cover all new columns, indexes, and defaults without modifying migrations `0001` through `0007`.

Pure scheduler tests cover first review, all four outcomes, stage caps, overdue cards, and exact UTC intervals. Answer-normalization tests cover case, Unicode whitespace, punctuation, apostrophes, hyphens, words, and sentences.

Feature tests cover ownership, deleted and incomplete cards, due-first ordering, 20-new-card limit, three-mode eligibility and rotation, timezone day boundaries, answer correctness, forced `again`, idempotent replay, idempotency conflict, concurrent-state serialization, card sync allocation, event audit fields, statistics consistency, XP isolation, and streak calculations.

Final verification runs migration twice, PHP syntax checks, Composer validation, full PHPUnit, Redis ping, Nginx validation, Webman process status, and a controlled HTTP smoke that creates and removes one temporary account without invoking Coze.

## Delivery Order

1. Migration, enums, models, timezone, and pure scheduling primitives.
2. Daily review queue and review-mode projection.
3. Idempotent answer submission, scheduling transaction, and card sync mutation.
4. Daily/lifetime statistics and game feedback.
5. Runtime verification, documentation handoff, local merge, and cleanup.

Stage 7 may rely on these stable HTTP contracts. Stage 8 implements the three review screens, system pronunciation, life UI, XP animation, and offline idempotent submission queue.
