# Spaced Review And Game Data Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Deliver deterministic spaced reviews, three practice modes, idempotent answer submission, timezone-correct statistics, and game feedback without coupling scheduling to XP.

**Architecture:** Pure Businesses normalize answers and calculate schedules from explicit inputs. Use-case Businesses own queue queries and one-transaction answer commits, including the existing user sync allocator. Review events preserve audit and replay payloads, while daily statistics provide local-day aggregates.

**Tech Stack:** PHP 8.2, Webman 2.2, Workerman 5, MySQL 8, Redis 8, PHPUnit 11.

## Global Constraints

- Follow `AGENTS.md`: Route -> Controller -> Business -> Model/Entity/Common; Controllers contain no queries or rules.
- Run PHP, migrations, Composer, and tests only inside `php82`.
- Add only migration `0008_add_review_scheduling_and_game_data.sql`; never edit migrations `0001` through `0007`.
- Store database datetimes in UTC and calculate local dates using the authenticated user's persisted IANA timezone, default `Asia/Shanghai`.
- The fixed intervals are 10 minutes, 1, 3, 7, 14, 30, 60, and 120 days; XP and game state never enter scheduler inputs.
- Answer submissions require an `Idempotency-Key`; exact replay returns stored response JSON and conflicts never mutate state.
- Every unique review mutation locks user then card and allocates exactly one user sync version.
- Use strict RED -> GREEN -> refactor and end each task with a focused commit.

---

### Task 1: Review Persistence, Enums, And Models

**Files:**
- Create: `database/migrations/0008_add_review_scheduling_and_game_data.sql`
- Create: `app/common/enums/ReviewMode.php`
- Create: `app/common/enums/ReviewDifficulty.php`
- Create: `app/common/enums/ReviewRating.php`
- Create: `app/models/ReviewEvent.php`
- Create: `app/models/DailyLearningStat.php`
- Modify: `app/models/User.php`
- Modify: `app/models/MemoryCard.php`
- Modify: `app/common/enums/BusinessCode.php`
- Create: `tests/Unit/ReviewSchedulingMigrationContractTest.php`
- Modify: `tests/Unit/DatabaseMigrationContractTest.php`
- Modify: `tests/Unit/BusinessCodeTest.php`

**Interfaces:**
- Produces enum values `image_recall`, `listening_spelling`, `zh_to_en`; `hard`, `good`, `easy`; `again`, `hard`, `good`, `easy`.
- Produces `ReviewEvent::forUser(int $userId)` and `DailyLearningStat::forUser(int $userId)` scopes.

- [x] **Step 1: Write failing migration and enum tests** asserting migration `0008`, all specified columns/defaults/indexes, model casts, and business codes `REVIEW_NOT_AVAILABLE`, `INVALID_REVIEW_MODE`, `INVALID_TIMEZONE`.
- [x] **Step 2: Run RED** with `docker exec -w /www/english-memory/.worktrees/stage-6-reviews php82 ./vendor/bin/phpunit --filter 'ReviewSchedulingMigrationContractTest|DatabaseMigrationContractTest|BusinessCodeTest'`; expect missing migration/types.
- [x] **Step 3: Add migration and types.** Add nullable legacy-safe columns, backfill old events with `legacy-{id}` keys, hashes and empty safe payloads, then make required columns non-null and add unique `(user_id,idempotency_key)`. Add user/card/stat columns exactly from the spec.
- [x] **Step 4: Apply twice and run GREEN** using `php scripts/migrate.php` twice and the focused tests.
- [x] **Step 5: Run full tests and commit** with `git commit -m "feat: add review scheduling persistence"`.

---

### Task 2: User Timezone Contract

**Files:**
- Create: `app/entities/TimezoneResultEntity.php`
- Create: `app/businesses/UpdateTimezoneBusiness.php`
- Create: `app/controllers/UpdateTimezoneController.php`
- Modify: `app/entities/AuthenticatedUserEntity.php`
- Modify: `app/businesses/CurrentUserBusiness.php`
- Modify: `config/route.php`
- Create: `tests/Feature/UpdateTimezoneControllerTest.php`
- Modify: `tests/Feature/CurrentUserControllerTest.php`

**Interfaces:**
- Produces `UpdateTimezoneBusiness::update(int $userId, string $timezone): TimezoneResultEntity`.
- Authenticated user output gains `timezone`.

- [x] **Step 1: Write failing tests** for default timezone, valid `America/New_York`, invalid identifiers, authentication, persistence, and unchanged `session_version`.
- [x] **Step 2: Run RED** with the two feature test files; expect missing Business/route and timezone output.
- [x] **Step 3: Implement validation** using `in_array($timezone, DateTimeZone::listIdentifiers(), true)`, update only the owned user, and register authenticated `PATCH /api/auth/me/timezone`.
- [x] **Step 4: Run focused/full GREEN** and syntax-check changed PHP files.
- [x] **Step 5: Commit** with `git commit -m "feat: persist user review timezone"`.

---

### Task 3: Pure Answer Normalization And Scheduling

**Files:**
- Create: `app/entities/ReviewScheduleEntity.php`
- Create: `app/businesses/ReviewAnswerNormalizerBusiness.php`
- Create: `app/businesses/ReviewSchedulerBusiness.php`
- Create: `tests/Unit/Businesses/ReviewAnswerNormalizerBusinessTest.php`
- Create: `tests/Unit/Businesses/ReviewSchedulerBusinessTest.php`

**Interfaces:**
- `ReviewAnswerNormalizerBusiness::normalize(string $answer): string`.
- `ReviewSchedulerBusiness::schedule(bool $isNew, int $currentStage, ReviewRating $rating, DateTimeImmutable $reviewedAtUtc): ReviewScheduleEntity`.
- Entity exposes `previousStage(): int`, `nextStage(): int`, `nextDueAt(): DateTimeImmutable`.

- [x] **Step 1: Write normalizer RED tests** for case, Unicode whitespace, terminal ASCII/Chinese punctuation, apostrophes, hyphens, words, and sentences.
- [x] **Step 2: Write scheduler RED tests** for first review, every rating, all intervals, cap stage 7, overdue input independence, and UTC output.
- [x] **Step 3: Run RED**; expect missing classes.
- [x] **Step 4: Implement minimal pure logic.** Use stage seconds `[600,86400,259200,604800,1209600,2592000,5184000,10368000]`; never read globals or XP.
- [x] **Step 5: Run focused/full GREEN and commit** with `git commit -m "feat: add deterministic review scheduler"`.

---

### Task 4: Daily Review Queue And Mode Projection

**Files:**
- Create: `app/entities/TodayReviewsResultEntity.php`
- Create: `app/businesses/ReviewModeBusiness.php`
- Create: `app/businesses/GetTodayReviewsBusiness.php`
- Create: `app/controllers/GetTodayReviewsController.php`
- Modify: `config/route.php`
- Create: `tests/Unit/Businesses/ReviewModeBusinessTest.php`
- Create: `tests/Feature/GetTodayReviewsControllerTest.php`

**Interfaces:**
- `ReviewModeBusiness::project(MemoryCard $card): array{available_modes: array, recommended_mode: string, expected_answer_kind: string}`.
- `GetTodayReviewsBusiness::get(int $userId, DateTimeImmutable $nowUtc): TodayReviewsResultEntity`.

- [x] **Step 1: Write mode RED tests** for image eligibility, mandatory listening/translation modes, missing expected answer, and deterministic `review_count` rotation.
- [x] **Step 2: Write queue RED tests** for ownership, active/latest-completed eligibility, all overdue cards first, stable ordering, no duplicates, exactly 20 new cards, timezone metadata, and empty queue.
- [x] **Step 3: Run RED**; expect missing classes/routes.
- [x] **Step 4: Implement query and projection.** Use `whereNull(deleted_at)`, latest-job completed subquery, due ordering, new-card limit 20, and shared `MemoryCardViewBusiness`.
- [x] **Step 5: Register authenticated `GET /api/reviews/today`, run full GREEN, and commit** with `git commit -m "feat: build daily review queue"`.

---

### Task 5: Idempotent Review Answer Transaction

**Files:**
- Create: `app/entities/ReviewAnswerResultEntity.php`
- Create: `app/businesses/SubmitReviewAnswerBusiness.php`
- Create: `app/controllers/SubmitReviewAnswerController.php`
- Modify: `config/route.php`
- Create: `tests/Feature/SubmitReviewAnswerControllerTest.php`

**Interfaces:**
- `SubmitReviewAnswerBusiness::submit(int $userId, int $cardId, string $idempotencyKey, string $mode, string $answer, string $difficulty, int $responseMs, DateTimeImmutable $nowUtc): ReviewAnswerResultEntity`.

- [x] **Step 1: Write validation/ownership RED tests** for missing/long keys, modes, difficulties, answer bounds, response range, foreign/deleted/incomplete cards, and unavailable image mode.
- [x] **Step 2: Write scheduling RED tests** proving server correctness, incorrect forced `again`, correct difficulty preservation, exact stages/due time, one sync version, and audit fields.
- [x] **Step 3: Write idempotency RED tests** proving identical replay returns byte-equivalent stored data without counters changing, changed request conflicts, and a second key serially advances the card.
- [x] **Step 4: Run RED**; expect missing endpoint/Business.
- [x] **Step 5: Implement one transaction** locking user/card, checking latest completed job, normalizing/evaluating, scheduling, updating card `first_reviewed_at/last_reviewed_at/review_count/review_stage/next_review_at/sync_version`, inserting event, and upserting local-day stats. Store the final safe result in `response_payload` before commit.
- [x] **Step 6: Handle unique races** by catching `UniqueConstraintViolationException`, loading the winner, then replaying or conflicting by `request_hash`.
- [x] **Step 7: Register authenticated `POST /api/reviews/{id}/answer`, run focused/full GREEN, and commit** with `git commit -m "feat: submit idempotent review answers"`.

---

### Task 6: Statistics Overview And Game Feedback

**Files:**
- Create: `app/entities/StatsOverviewResultEntity.php`
- Create: `app/businesses/GetStatsOverviewBusiness.php`
- Create: `app/controllers/GetStatsOverviewController.php`
- Modify: `config/route.php`
- Create: `tests/Feature/GetStatsOverviewControllerTest.php`

**Interfaces:**
- `GetStatsOverviewBusiness::get(int $userId, DateTimeImmutable $nowUtc): StatsOverviewResultEntity`.

- [x] **Step 1: Write RED tests** for empty stats, local date boundaries, today/lifetime accuracy, XP values `2/8/10/12`, level `1 + floor(xp/500)`, current/best correct streak, distinct reviewed cards, due/new counts, and learning-day streak ending today or yesterday.
- [x] **Step 2: Run RED**; expect missing endpoint/Business.
- [x] **Step 3: Implement aggregate queries** from committed event/stat rows and reuse daily-queue eligibility predicates without feeding any statistic into scheduling.
- [x] **Step 4: Register authenticated `GET /api/stats/overview`, run focused/full GREEN, and commit** with `git commit -m "feat: expose review statistics"`.

---

### Task 7: Sync And Representation Integration

**Files:**
- Modify: `app/businesses/MemoryCardViewBusiness.php`
- Modify: `app/businesses/GetSyncChangesBusiness.php`
- Modify: `tests/Unit/Businesses/MemoryCardViewBusinessTest.php`
- Modify: `tests/Feature/GetSyncChangesControllerTest.php`

**Interfaces:**
- Active card views expose `first_reviewed_at`, `last_reviewed_at`, `review_count`, `review_stage`, and `next_review_at`.

- [x] **Step 1: Write RED tests** showing review scheduling fields in detail/list/sync and proving one answer appears as one later sync version.
- [x] **Step 2: Run RED**; expect missing fields.
- [x] **Step 3: Extend shared card representation** with safe UTC-formatted review fields; keep tombstones minimal.
- [x] **Step 4: Run focused/full GREEN and commit** with `git commit -m "feat: sync review scheduling state"`.

---

### Task 8: Runtime Verification And Stage Handoff

**Files:**
- Modify: `PROJECT_PLAN.md`
- Modify: `docs/superpowers/plans/2026-07-19-spaced-review-and-game-data.md`

**Interfaces:**
- Produces the verified Stage 6 handoff and Stage 7 Flutter entry point.

- [x] **Step 1: Run deterministic verification:** migration twice, `composer validate --no-check-publish`, full PHPUnit, changed-file `php -l`, `git diff --check`, Redis `PING`, `nginx -t`, and tracked secret/temp-URL scan.
- [x] **Step 2: Use `superpowers:finishing-a-development-branch`** to merge locally only after tests pass; restart Webman and require consumer, compensation, webman, and monitor exit status/count `0/0`.
- [x] **Step 3: Run controlled HTTP smoke without Coze:** create a temporary completed card, replace device session, fetch today queue, submit/replay one correct answer, submit one incorrect answer, verify sync and statistics, update timezone, and remove all fixtures.
- [x] **Step 4: Update handoff evidence** with migration `0008`, final test/assertion count, route list, scheduler intervals, timezone boundary result, replay evidence, statistics, Worker status, branch state, and Stage 7 next step.
- [x] **Step 5: Commit docs and run fresh final tests** with `git commit -m "docs: complete spaced review handoff"`.
