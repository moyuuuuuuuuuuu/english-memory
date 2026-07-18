# English Memory Platform Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver a secure Webman backend and Flutter mobile app that turn editable English input or cropped-photo OCR into AI mnemonic cards and game-like spaced-repetition reviews.

**Architecture:** Flutter performs capture, crop, local OCR, editable confirmation, audio playback, offline cache, and review UI. Webman owns accounts, authorization, durable cards, AI jobs, image persistence, review scheduling, and sync. Coze generates structured text and a temporary image URL; Webman validates and stores both under application control.

**Tech Stack:** PHP 8.2, Webman 2.2, Workerman 5, MySQL 8, Redis 8, PHPUnit 11, Guzzle 7, Nginx, Flutter, Coze workflow API.

## Global Constraints

- Repository root remains the Webman backend; Flutter lives in `mobile/`.
- API responses follow the `success/data` and `success/error` envelopes defined in `AGENTS.md`.
- Coze secrets stay in backend `.env`; Flutter never receives provider credentials.
- Default memory style is `auto`; weak phonetic associations fall back to semantic scenes.
- Coze image URLs are downloaded and replaced with application-owned URLs.
- AI generation becomes asynchronous before mobile integration.
- Every behavior change follows red-green-refactor and ends with full test verification.

---

## Current Baseline

- [x] Initialize Webman and PHP 8.2 dependencies.
- [x] Load `.env` through `vlucas/phpdotenv` and provide `.env.example`.
- [x] Publish and validate Coze workflow `7663771858193448987`.
- [x] Implement `CozeWorkflowClient` and `POST /api/memory-cards/generate`.
- [x] Add Nginx reverse proxy for `e.test`.
- [x] Verify real generation of `ambition -> 俺必胜` with an image URL.

---

### Task 1: Backend persistence foundation

**Files:**
- Modify: `composer.json`, `composer.lock`, `.env.example`
- Create: `config/database.php`, `config/redis.php`
- Create: `database/migrations/0001_create_users_and_tokens.sql`
- Create: `database/migrations/0002_create_memory_cards_and_ai_jobs.sql`
- Create: `database/migrations/0003_create_reviews_and_daily_stats.sql`
- Test: `tests/Feature/DatabaseConnectionTest.php`

**Interfaces:**
- Produces tables `users`, `refresh_tokens`, `memory_cards`, `ai_generation_jobs`, `review_events`, and `daily_learning_stats`.
- Produces configured MySQL and Redis clients for later tasks.

- [ ] Write connection tests that fail when MySQL or Redis is unavailable.
- [ ] Install the Webman database and Redis integrations through Composer.
- [ ] Add configuration using only environment variables.
- [ ] Add versioned migrations with foreign keys, ownership indexes, unique email/username constraints, and JSON columns for AI card payloads.
- [ ] Run migrations against the `english_memory` database.
- [ ] Run the full PHPUnit suite and verify all connections.

### Task 2: Account, session, and device authentication

**Files:**
- Create: `app/controllers/AuthController.php`
- Create: `app/services/AuthService.php`, `app/services/TokenService.php`
- Create: `app/middleware/Authenticate.php`
- Create: `app/models/User.php`, `app/models/RefreshToken.php`
- Modify: `config/route.php`, `config/middleware.php`
- Test: `tests/Feature/AuthControllerTest.php`, `tests/Unit/TokenServiceTest.php`

**Interfaces:**
- Produces `POST /api/auth/register`, `/login`, `/refresh`, `/logout`, `/forgot-password`, and `/reset-password`.
- Produces authenticated user context for protected card and review routes.

- [ ] Write failing tests for username registration, email registration, duplicate identity rejection, password login, refresh rotation, logout revocation, and protected-route denial.
- [ ] Store passwords with `password_hash()` and refresh tokens as hashes with device metadata and expiry.
- [ ] Implement short-lived access tokens and rotating refresh tokens.
- [ ] Add email reset-token persistence and a mail-provider boundary; keep provider implementation replaceable.
- [ ] Add authentication middleware and ownership helpers.
- [ ] Run the full suite and verify no token or password appears in logs or API responses.

### Task 3: Asynchronous mnemonic-card generation

**Files:**
- Refactor: `app/controllers/GenerateMemoryCardController.php`
- Modify: `app/services/CozeWorkflowClient.php`
- Create: `app/services/MemoryCardService.php`, `app/services/AiGenerationJobService.php`
- Create: `app/processes/AiGenerationWorker.php`
- Create: `app/models/MemoryCard.php`, `app/models/AiGenerationJob.php`
- Modify: `config/process.php`, `config/route.php`
- Test: `tests/Feature/MemoryCardGenerationTest.php`, `tests/Unit/AiGenerationJobServiceTest.php`

**Interfaces:**
- Produces `POST /api/memory-cards`, returning card/job IDs immediately.
- Produces `GET /api/memory-cards/{id}` and `POST /api/memory-cards/{id}/retry`.
- Consumes Redis jobs and writes validated Coze results to MySQL.

- [ ] Write failing tests for input validation, ownership, duplicate idempotency keys, queued state, successful completion, provider failure, and retry.
- [ ] Move synchronous Coze execution out of the HTTP request into a Redis-backed worker.
- [ ] Persist job states `queued`, `generating_text`, `generating_image`, `completed`, and `failed`.
- [ ] Validate required card fields before marking a job complete.
- [ ] Keep the existing synchronous route temporarily behind a development-only flag, then remove it after mobile migration.
- [ ] Run worker and API tests, then perform one real Coze smoke test.

### Task 4: Application-owned image storage

**Files:**
- Create: `app/services/contracts/ImageStorage.php`
- Create: `app/services/LocalImageStorage.php`, `app/services/RemoteImageImporter.php`
- Create: `config/storage.php`
- Create: `public/storage/.gitignore`
- Modify: `app/processes/AiGenerationWorker.php`, `.env.example`
- Test: `tests/Unit/RemoteImageImporterTest.php`, `tests/Feature/MemoryCardImageTest.php`

**Interfaces:**
- Consumes a temporary HTTPS Coze URL.
- Produces an application URL and stored metadata: MIME type, byte size, width, height, and checksum.

- [ ] Write failing tests for allowed HTTPS hosts, MIME validation, size limits, redirects, timeouts, and cleanup.
- [ ] Download images immediately after successful generation.
- [ ] Accept only configured image MIME types and maximum byte/dimension limits.
- [ ] Generate 1024px and 512px derivatives for mobile use.
- [ ] Persist only application-owned URLs and delete files when a card or account is deleted.
- [ ] Verify URL serving through Nginx and run the full suite.

### Task 5: Card library and cross-device sync

**Files:**
- Create: `app/controllers/MemoryCardController.php`, `app/controllers/SyncController.php`
- Create: `app/services/CardSyncService.php`
- Modify: `app/models/MemoryCard.php`, `config/route.php`
- Test: `tests/Feature/MemoryCardCrudTest.php`, `tests/Feature/CardSyncTest.php`

**Interfaces:**
- Produces list, detail, edit, favorite, tag, regenerate, and soft-delete endpoints.
- Produces cursor-based sync using `updated_at`, tombstones, and optimistic version numbers.

- [ ] Write failing ownership and CRUD tests.
- [ ] Add cursor pagination, search, tags, favorites, and generation-status filters.
- [ ] Add optimistic concurrency through a monotonically increasing `version` field.
- [ ] Return conflicts with both server and client metadata; never silently overwrite concurrent edits.
- [ ] Add tombstones so deletions sync across devices.
- [ ] Run the full suite with two-device conflict scenarios.

### Task 6: Spaced repetition and game progression

**Files:**
- Create: `app/services/ReviewScheduler.php`, `app/services/ReviewSessionService.php`
- Create: `app/controllers/ReviewController.php`
- Create: `app/models/ReviewEvent.php`, `app/models/DailyLearningStat.php`
- Modify: `config/route.php`
- Test: `tests/Unit/ReviewSchedulerTest.php`, `tests/Feature/ReviewSessionTest.php`

**Interfaces:**
- Produces `GET /api/reviews/today`, `POST /api/reviews/{cardId}/answer`, and `GET /api/stats/overview`.
- Produces question types `image_recall`, `audio_spelling`, and `zh_to_en`.

- [ ] Write deterministic scheduler tests for first review, correct answer, wrong answer, overdue cards, and timezone boundaries.
- [ ] Implement interval scheduling independently from XP and streak calculations.
- [ ] Prevent duplicate submissions through answer idempotency keys.
- [ ] Compute daily goal progress only from valid unique reviews.
- [ ] Add XP, consecutive-correct count, streak days, and level summaries as presentation metadata.
- [ ] Run scheduler property cases and full feature tests.

### Task 7: Flutter mobile foundation and local OCR

**Files:**
- Create: `mobile/pubspec.yaml`, `mobile/lib/main.dart`
- Create: `mobile/lib/core/`, `mobile/lib/features/auth/`, `mobile/lib/features/capture/`, `mobile/lib/features/cards/`, `mobile/lib/features/review/`
- Create: `mobile/test/`

**Interfaces:**
- Consumes the authenticated Webman API.
- Produces Android APK and an iOS project ready for later macOS signing.

- [ ] Initialize Flutter with Android and iOS targets.
- [ ] Implement the warm-white champagne design tokens and five-tab navigation.
- [ ] Implement login, token refresh, secure credential storage, and logout.
- [ ] Implement camera capture, crop selection, on-device English OCR, editable confirmation, and word/sentence choice.
- [ ] Implement local cache and a durable outbound sync queue.
- [ ] Add widget and integration tests for capture-to-submit without requiring a real camera.

### Task 8: Mobile cards, review game, and offline behavior

**Files:**
- Modify: `mobile/lib/features/cards/`, `mobile/lib/features/review/`
- Create: `mobile/lib/features/audio/`, `mobile/lib/features/sync/`
- Test: `mobile/test/features/cards/`, `mobile/test/features/review/`

**Interfaces:**
- Displays AI text before image completion and refreshes job status in the background.
- Submits review answers and syncs offline events idempotently.

- [ ] Implement card-generation progress, retry, edit, favorite, tag, and delete states.
- [ ] Implement mnemonic cards with phonetic and semantic-scene layouts.
- [ ] Implement system pronunciation playback and cached audio state.
- [ ] Implement the three review question types with lives, XP, and streak feedback.
- [ ] Add reduced-motion support and restrained particle effects for success moments.
- [ ] Test airplane-mode creation, queued sync, conflict display, and recovery after reconnect.

### Task 9: Hardening, deployment, and release artifacts

**Files:**
- Modify: `Dockerfile`, `docker-compose.yml`, Nginx deployment configuration
- Create: `docs/api.md`, `docs/deployment.md`, `docs/privacy-and-deletion.md`
- Create: CI configuration for backend and Flutter tests

**Interfaces:**
- Produces a reproducible backend deployment, Android APK, and documented iOS build steps.

- [ ] Add rate limits for auth and AI generation, request IDs, structured logs, and sensitive-field redaction.
- [ ] Add health/readiness endpoints that test dependencies without leaking credentials.
- [ ] Make Webman start automatically with the deployment rather than relying on a manually started daemon.
- [ ] Add MySQL backup/restore and image-retention procedures.
- [ ] Run backend tests, Flutter tests, static analysis, Nginx validation, and end-to-end staging checks.
- [ ] Build and smoke-test the Android APK; document the macOS/Xcode steps for iOS signing.

## Recommended execution order

1. Tasks 1-4 establish a secure, durable backend and remove the current synchronous-provider limitation.
2. Tasks 5-6 complete the backend product loop.
3. Tasks 7-8 build the mobile experience against stable APIs.
4. Task 9 prepares repeatable deployment and distributable builds.

Do not begin cloud sync UI or review-game polish before Tasks 1-6 have stable contracts and passing tests.
