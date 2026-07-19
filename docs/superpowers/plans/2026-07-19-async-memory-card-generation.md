# Async Memory Card Generation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Create authenticated mnemonic cards immediately, dispatch durable AI jobs through Redis Streams, process them asynchronously through Coze, and expose owned detail and manual-retry endpoints.

**Architecture:** MySQL owns cards, jobs, states, ownership, and user-scoped idempotency. Redis Streams only dispatches Job IDs to a Consumer Group; every consumer reloads and locks the MySQL Job before acting. Controllers call one Business, Businesses own application decisions, Services own Redis/Coze integration, Models only persist, and typed Entities own response shapes.

**Tech Stack:** PHP 8.2, Webman 2.2, Workerman 5, Illuminate Database 12, phpredis through webman/redis, Redis Streams, MySQL 8, PHPUnit 11, Guzzle 7, Coze workflow API.

## Global Constraints

- Follow `AGENTS.md` plural layer names and dependency direction.
- `POST /api/memory-cards` and retry require a non-empty client `Idempotency-Key` no longer than 128 bytes.
- MySQL is the source of truth; Stream messages contain only `job_id`.
- Provider failures are terminal and are never automatically retried.
- All new routes require `Authenticate` and every card/job query includes `user_id`.
- Business error values come from `app/common/enums/BusinessCode.php`; the enum does not own HTTP status or messages.
- No response, log, or database error field may contain PATs, bearer/refresh tokens, `reasoning_content`, or raw provider exceptions.
- The synchronous endpoint is available only when `APP_ENV` is not `production` and `ENABLE_SYNC_GENERATION=true`.
- Schema changes use new migration `0005`; migrations `0001` through `0004` remain unchanged.
- Execute every behavior through red-green-refactor and commit after each independently passing task.

---

### Task 1: Stable Business Code Enum

**Files:**
- Create: `app/common/enums/BusinessCode.php`
- Create: `tests/Unit/BusinessCodeTest.php`
- Modify: `app/entities/AuthResultEntity.php`
- Modify: `app/entities/MemoryCardGenerationEntity.php`
- Modify: existing Businesses that pass string error codes

**Interfaces:**
- Produces `BusinessCode: string` cases listed in the approved design.
- Result factories consume `BusinessCode` and serialize `$code->value`.

- [ ] **Step 1: Write the failing enum contract test** asserting all approved string values and asserting `AuthResultEntity::failure(401, BusinessCode::Unauthenticated, '请先登录。')` serializes `UNAUTHENTICATED`.
- [ ] **Step 2: Run** `docker exec -w /www/english-memory/.worktrees/stage-3-async-generation php82 ./vendor/bin/phpunit tests/Unit/BusinessCodeTest.php`; expect failure because `BusinessCode` does not exist.
- [ ] **Step 3: Implement** a string-backed enum with cases `InvalidInput`, `IdentityTaken`, `InvalidCredentials`, `AccountDisabled`, `Unauthenticated`, `InvalidRefreshToken`, `InvalidResetToken`, `AiProviderError`, `GenerationFailed`, `InvalidAiResult`, `IdempotencyKeyRequired`, `IdempotencyConflict`, `CardNotFound`, `JobStateConflict`, and `QueueUnavailable`.
- [ ] **Step 4: Change result factories** to accept `BusinessCode`, then replace every touched raw string call with the matching enum case. Do not put messages or HTTP status inside the enum.
- [ ] **Step 5: Run** the enum test and full PHPUnit suite; expect all green.
- [ ] **Step 6: Commit** with `git commit -m "refactor: centralize business response codes"`.

### Task 2: Async Job Migration, Status Enum, And Models

**Files:**
- Create: `database/migrations/0005_extend_ai_generation_jobs_for_async.sql`
- Create: `app/common/enums/AiGenerationStatus.php`
- Create: `app/models/MemoryCard.php`
- Create: `app/models/AiGenerationJob.php`
- Create: `tests/Unit/AsyncGenerationMigrationContractTest.php`
- Modify: `tests/Unit/DatabaseMigrationContractTest.php`

**Interfaces:**
- `AiGenerationStatus` produces `queued`, `generating_text`, `generating_image`, `completed`, and `failed`.
- `AiGenerationJob::latestForCard(int $userId, int $cardId): ?self` always scopes ownership.

- [ ] **Step 1: Write the failing migration/status test** for `idempotency_key`, `request_hash`, `failure_type`, `parent_job_id`, `dispatched_at`, the `(user_id,idempotency_key)` unique index, self-FK, and exact status values.
- [ ] **Step 2: Run the focused test** and confirm failure because migration `0005` and status enum are absent.
- [ ] **Step 3: Add migration `0005`** that first adds nullable columns including `dispatched_at`, backfills legacy rows with `legacy-<id>` and a SHA-256 request hash, then makes required columns non-null and adds indexes/FK.
- [ ] **Step 4: Implement models** with fillable persistence fields, JSON casts for request/card/provider payloads, hidden provider/error internals where appropriate, and ownership/latest scopes only.
- [ ] **Step 5: Run migration twice** with `php scripts/migrate.php`; first run applies `0005`, second run skips it. Run focused and full tests.
- [ ] **Step 6: Commit** with `git commit -m "feat: add async generation persistence"`.

### Task 3: Redis Streams Queue Boundary

**Files:**
- Create: `app/entities/QueueMessageEntity.php`
- Create: `app/services/contracts/AiGenerationQueue.php`
- Create: `app/services/RedisStreamAiGenerationQueue.php`
- Create: `config/ai_generation.php`
- Create: `tests/Feature/RedisStreamAiGenerationQueueTest.php`
- Modify: `.env.example`, `config/dependence.php`

**Interfaces:**
- `AiGenerationQueue::publish(int $jobId): void`
- `AiGenerationQueue::consume(string $consumer, int $blockMs = 5000): ?QueueMessageEntity`
- `AiGenerationQueue::ack(QueueMessageEntity $message): void`
- `AiGenerationQueue::claimStale(string $consumer, int $idleMs): ?QueueMessageEntity`
- `QueueMessageEntity` exposes `messageId(): string` and `jobId(): int`.

- [ ] **Step 1: Write a failing Redis integration test** using a unique Stream/Group that covers group creation, publish, consume, ACK, and stale-message claiming. Always delete the isolated Stream in `tearDown()`.
- [ ] **Step 2: Run the focused test** and confirm failure because the queue classes do not exist.
- [ ] **Step 3: Implement the queue** with `XGROUP CREATE ... MKSTREAM`, `XADD`, `XREADGROUP`, `XACK`, and `XAUTOCLAIM`; tolerate `BUSYGROUP` only when ensuring the group.
- [ ] **Step 4: Add configuration** for stream key, group, consumer prefix, block time, claim idle time, and compensation age; add empty-secret-safe values to `.env.example` and bind `AiGenerationQueue` in `config/dependence.php`.
- [ ] **Step 5: Run focused test, Redis `PING`, and full suite**; expect all green.
- [ ] **Step 6: Commit** with `git commit -m "feat: add Redis stream generation queue"`.

### Task 4: Idempotent Async Card Creation

**Files:**
- Create: `app/entities/AsyncMemoryCardResultEntity.php`
- Create: `app/businesses/CreateMemoryCardBusiness.php`
- Create: `app/controllers/CreateMemoryCardController.php`
- Create: `tests/Feature/CreateMemoryCardControllerTest.php`
- Modify: `config/route.php`, `tests/Feature/AuthRouteContractTest.php`

**Interfaces:**
- `CreateMemoryCardBusiness::create(int $userId, string $idempotencyKey, string $text, string $contentType, string $memoryStyle): AsyncMemoryCardResultEntity`
- Result success returns HTTP 202 with `card_id`, `job_id`, `status`, `replayed`, and `dispatch_pending`.

- [ ] **Step 1: Write failing tests** for missing/oversized key, blank text, invalid enum inputs, successful durable create, same-key replay, same-key/different-payload conflict, queue failure preservation, and other-user key reuse.
- [ ] **Step 2: Run the focused test** and confirm missing Business/Controller failures.
- [ ] **Step 3: Implement request hashing** as SHA-256 over canonical JSON `{text,content_type,memory_style}` and create Card/Job in one transaction. Catch the unique-key race by reloading the winner and comparing `request_hash`.
- [ ] **Step 4: Publish after commit**. Successful publication updates `dispatched_at`; queue failure leaves Job `queued`, sets `dispatch_pending=true`, and never deletes durable rows. Replay does not publish again.
- [ ] **Step 5: Add authenticated route** `POST /api/memory-cards`, reading `Idempotency-Key` in the Controller and applying `Authenticate`.
- [ ] **Step 6: Run focused tests, full tests, syntax, and diff checks**.
- [ ] **Step 7: Commit** with `git commit -m "feat: create idempotent async memory cards"`.

### Task 5: Owned Detail And Manual Retry

**Files:**
- Create: `app/businesses/GetMemoryCardBusiness.php`
- Create: `app/businesses/RetryMemoryCardBusiness.php`
- Create: `app/controllers/GetMemoryCardController.php`
- Create: `app/controllers/RetryMemoryCardController.php`
- Create: `tests/Feature/MemoryCardAsyncLifecycleTest.php`
- Modify: `app/entities/AsyncMemoryCardResultEntity.php`, `config/route.php`, `tests/Feature/AuthRouteContractTest.php`

**Interfaces:**
- `GetMemoryCardBusiness::get(int $userId, int $cardId): AsyncMemoryCardResultEntity`
- `RetryMemoryCardBusiness::retry(int $userId, int $cardId, string $idempotencyKey): AsyncMemoryCardResultEntity`
- Produces authenticated `GET /api/memory-cards/{id}` and `POST /api/memory-cards/{id}/retry`.

- [ ] **Step 1: Write failing tests** for owned queued/completed/failed detail, foreign/missing card hiding, retry of failed latest Job, parent linkage, fresh key requirement, replay, active/completed conflict, and queue failure preservation.
- [ ] **Step 2: Run the focused test** and confirm missing classes.
- [ ] **Step 3: Implement owned detail** returning only public card fields, latest safe Job state, stable Business Code, and Chinese error message; exclude `provider_payload` and raw exceptions.
- [ ] **Step 4: Implement retry** in a transaction. Require latest state `failed`, create a child Job with the original request payload/hash and new key, then publish after commit.
- [ ] **Step 5: Add both authenticated routes**, run focused/full tests, syntax, and diff checks.
- [ ] **Step 6: Commit** with `git commit -m "feat: add async card detail and retry"`.

### Task 6: AI Generation Worker State Machine

**Files:**
- Create: `app/businesses/ProcessAiGenerationJobBusiness.php`
- Create: `app/processes/AiGenerationWorker.php`
- Create: `tests/Unit/Businesses/ProcessAiGenerationJobBusinessTest.php`
- Create: `tests/Unit/AiGenerationWorkerTest.php`
- Modify: `config/dependence.php`

**Interfaces:**
- `ProcessAiGenerationJobBusiness::process(int $jobId): void`
- `AiGenerationWorker::runOnce(string $consumer): bool` consumes at most one message and returns whether a message was handled.

- [ ] **Step 1: Write failing Business tests** with a fake `MemoryCardGenerator` for success, provider-declared failure, thrown provider exception, missing required card fields, missing image URL, duplicate/terminal Job, and unknown Job.
- [ ] **Step 2: Run focused tests** and confirm missing processor failure.
- [ ] **Step 3: Implement state transitions** using row locks and short transactions: claim `queued`, increment attempts, set `generating_text`, call provider outside the transaction, validate required fields (`success`, normalized word/text, meanings, story or mnemonic, example, image URL), persist `generating_image`, then card payload/image URL and `completed`.
- [ ] **Step 4: Persist terminal failures** as only `BusinessCode::AiProviderError` or `BusinessCode::InvalidAiResult` plus a safe Chinese message. Never save raw exception messages or reasoning content.
- [ ] **Step 5: Write and run failing Worker tests** for ACK after completed/failed processing, no ACK when an unexpected database exception escapes, and stale-message claim before normal consume.
- [ ] **Step 6: Implement `runOnce()`**, bind processor dependencies, and run focused/full tests.
- [ ] **Step 7: Commit** with `git commit -m "feat: process AI generation jobs"`.

### Task 7: Consumer Process, Compensation, And Sync Endpoint Flag

**Files:**
- Create: `app/businesses/CompensateQueuedAiJobsBusiness.php`
- Create: `app/processes/AiGenerationConsumerProcess.php`
- Create: `app/processes/AiGenerationCompensationProcess.php`
- Create: `tests/Unit/Businesses/CompensateQueuedAiJobsBusinessTest.php`
- Create: `tests/Feature/SyncGenerationRouteFlagTest.php`
- Modify: `config/process.php`, `config/route.php`, `config/ai_generation.php`, `.env.example`

**Interfaces:**
- `CompensateQueuedAiJobsBusiness::dispatch(int $limit = 100): int` republishes old `queued` Jobs and returns the publish count.
- Consumer process repeatedly calls `AiGenerationWorker::runOnce()` with a stable unique consumer name.

- [ ] **Step 1: Write failing compensation tests** proving only old `queued` Jobs are published and `failed`, active, recent, and completed Jobs are untouched.
- [ ] **Step 2: Implement compensation Business** with bounded ordered scans over `queued` Jobs whose `dispatched_at` is null or stale. Successful publish updates `dispatched_at`; queue errors leave it unchanged for the next cycle and never call Coze.
- [ ] **Step 3: Write failing route-flag tests** for local enabled, local disabled, and production forced-disabled synchronous generation.
- [ ] **Step 4: Refactor route registration** into a condition using both `APP_ENV` and `ENABLE_SYNC_GENERATION`; add the flag to `.env.example`.
- [ ] **Step 5: Add consumer and compensation entries** to `config/process.php` with constructor dependencies provided through factories or explicit construction compatible with Webman's container.
- [ ] **Step 6: Run focused/full tests, restart Webman, and inspect worker status** for the new processes.
- [ ] **Step 7: Commit** with `git commit -m "feat: run and compensate async AI jobs"`.

### Task 8: Stage Verification And Handoff

**Files:**
- Modify: `PROJECT_PLAN.md`
- Modify: `docs/superpowers/plans/2026-07-19-async-memory-card-generation.md`

**Interfaces:**
- Produces the verified Stage 3 handoff and next entry point: application-owned image storage.

- [ ] **Step 1: Run migrations twice**, full PHPUnit, PHP syntax on changed files, Composer validation, Redis `PING`, Nginx validation, sensitive-value scan, and `git diff --check`.
- [ ] **Step 2: Restart Webman** and smoke-test missing idempotency key, unauthenticated create/detail/retry, and disabled synchronous route through `e.test`.
- [ ] **Step 3: Run one real async Coze smoke test** with a temporary authenticated test user and unique idempotency key; poll owned detail until terminal state, verify one Job/provider call, then delete the test user so FK cascades clean up records.
- [ ] **Step 4: Update `PROJECT_PLAN.md`** with Stage 3 completion, exact test baseline, migration `0005`, current handoff, and no stale branch/untracked warnings.
- [ ] **Step 5: Mark this plan complete**, commit docs, run final verification again, and use `finishing-a-development-branch` before integration.
