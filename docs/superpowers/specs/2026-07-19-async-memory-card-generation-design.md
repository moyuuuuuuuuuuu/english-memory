# Async Memory Card Generation Design

## Goal

Move mnemonic-card generation out of the HTTP request path. An authenticated client creates a durable card and AI job immediately, while a Redis Streams consumer executes the published Coze workflow and persists a validated result in MySQL.

## Decisions

- MySQL is the source of truth for cards, jobs, state, ownership, and idempotency.
- Redis Streams with a Consumer Group transports only durable job identifiers.
- `POST /api/memory-cards` requires a client-provided `Idempotency-Key` header.
- AI provider failures are not retried automatically. The user explicitly calls the retry endpoint.
- The existing synchronous generation endpoint remains available only behind a development configuration flag until mobile migration is complete.
- A string-backed `BusinessCode` enum owns stable API error-code values. HTTP statuses and Chinese messages remain use-case decisions outside the enum.

## Public API

### Create a card

`POST /api/memory-cards`

Requirements:

- Valid Bearer access token.
- Non-empty `Idempotency-Key` header.
- JSON fields `text`, `content_type`, and `memory_style` with the same validation rules as the current synchronous endpoint.

On the first request, the backend creates one `memory_cards` row and one `ai_generation_jobs` row in a database transaction, then publishes the Job ID to Redis. It returns HTTP 202:

```json
{
  "success": true,
  "data": {
    "card_id": 101,
    "job_id": 202,
    "status": "queued"
  }
}
```

The unique key is scoped to the authenticated user. Repeating the same key returns the original Card ID and Job ID without another row or Stream message. A key used for a different request payload returns HTTP 409 with `IDEMPOTENCY_CONFLICT`.

### Read a card and generation state

`GET /api/memory-cards/{id}`

Returns only a card owned by the authenticated user, along with its latest Job state. While queued or running, `card_payload` can be empty and `image_url` can be null. Failed responses expose only stable application error information.

### Retry a failed job

`POST /api/memory-cards/{id}/retry`

Requirements:

- Valid Bearer access token.
- A new `Idempotency-Key` header.
- The latest Job for the owned card is `failed`.

The retry creates a new Job associated through `parent_job_id`. It does not modify or reuse the failed Job. Retrying an active or completed Job returns HTTP 409.

## Business Codes

Create `app/common/enums/BusinessCode.php` as a string-backed PHP enum. It initially includes all current stable codes and the new async-generation codes:

- `INVALID_INPUT`
- `IDENTITY_TAKEN`
- `INVALID_CREDENTIALS`
- `ACCOUNT_DISABLED`
- `UNAUTHENTICATED`
- `INVALID_REFRESH_TOKEN`
- `INVALID_RESET_TOKEN`
- `AI_PROVIDER_ERROR`
- `GENERATION_FAILED`
- `INVALID_AI_RESULT`
- `IDEMPOTENCY_KEY_REQUIRED`
- `IDEMPOTENCY_CONFLICT`
- `CARD_NOT_FOUND`
- `JOB_STATE_CONFLICT`
- `QUEUE_UNAVAILABLE`

Result entities serialize `$businessCode->value`. The enum does not contain HTTP status codes, translated messages, provider details, or response-building logic.

## Persistence

Add a new forward-only migration `0005_extend_ai_generation_jobs_for_async.sql`.

`ai_generation_jobs` gains:

- `idempotency_key VARCHAR(128) NOT NULL`
- `request_hash CHAR(64) NOT NULL`
- `failure_type VARCHAR(64) NULL`
- `parent_job_id BIGINT UNSIGNED NULL`
- `dispatched_at DATETIME NULL`
- a unique index on `(user_id, idempotency_key)`
- an index on `(status, created_at)` for compensation scans
- a self-referencing foreign key for `parent_job_id` with `ON DELETE SET NULL`

Existing rows receive deterministic legacy values during migration before the new columns become non-null. The migration must not modify files `0001` through `0004`.

Queued cards store the trimmed source text in both `source_text` and `normalized_text`, the requested content type/style, and `{}` in `card_payload`. Provider data replaces those placeholders only after validation.

## Status Machine

```text
queued
  -> generating_text
  -> generating_image
  -> completed

queued | generating_text | generating_image
  -> failed
```

The Coze workflow currently returns structured text and a temporary image URL in one response. The Worker sets `generating_text` before the provider call, validates the returned card, then sets `generating_image` while persisting the temporary image metadata. Stage 4 will turn `generating_image` into the application-owned image-import phase.

No failed Job automatically returns to `queued`.

## Components

### Businesses

- `CreateMemoryCardBusiness`: validation, ownership context, idempotent transaction, and post-commit queue publication.
- `GetMemoryCardBusiness`: owned card lookup and latest safe Job state.
- `RetryMemoryCardBusiness`: failed-state validation, child Job creation, and publication.

Controllers only extract HTTP values, call one Business, and map its result entity to the public response.

### Models and entities

- `MemoryCard` and `AiGenerationJob` contain persistence metadata and reusable query scopes only.
- Result entities define stable create/detail/retry response shapes.
- Provider `card_json` strings and `reasoning_content` never leave `CozeWorkflowService`.

### Queue service

`app/services/contracts/AiGenerationQueue.php` owns the application queue interface. `RedisStreamAiGenerationQueue` implements it with:

- one configured Stream key;
- one configured Consumer Group;
- explicit message acknowledgment;
- pending-message claiming for consumers that died;
- MySQL `dispatched_at` updates after each successful publish so compensation can bound duplicate messages.

The message payload contains only `job_id`. A consumer always reloads the Job from MySQL before making any decision.

### Worker

`app/processes/AiGenerationWorker.php`:

1. Receives a Stream message.
2. Loads and locks the Job.
3. ACKs immediately if the Job is already `completed` or `failed`.
4. Moves `queued` to `generating_text`.
5. Calls `MemoryCardGenerator` through the existing `CozeWorkflowService`.
6. Validates required application fields and rejects provider reasoning/debug content.
7. Persists the card and terminal Job state.
8. ACKs only after the database update commits.

Provider exceptions become `failed` with `AI_PROVIDER_ERROR`. Structurally invalid successful responses become `failed` with `INVALID_AI_RESULT`. Raw exceptions and provider payloads are not returned to clients.

### Compensation process

`AiGenerationCompensationProcess` periodically scans old `queued` Jobs whose `dispatched_at` is null or older than the configured compensation age and republishes them. It only repairs dispatch gaps; it never retries a `failed` provider call.

## Queue Failure Semantics

Card and Job creation commit before Redis publication. If publication fails:

- the API still has durable IDs;
- the Job remains `queued`;
- the response uses HTTP 202 and includes a dispatch-pending indicator rather than discarding the request;
- the compensation process republishes it later.

This prevents a temporary Redis failure from causing clients to create duplicate cards.

## Security and ownership

- All new routes require `Authenticate`.
- Every card/job query includes `user_id`; foreign IDs return `CARD_NOT_FOUND` rather than revealing existence.
- Idempotency uniqueness is scoped to a user.
- Logs contain Job IDs and stable Business Codes, never PATs, bearer tokens, provider reasoning, or raw card input unless explicitly redacted.
- The synchronous endpoint is registered only when `ENABLE_SYNC_GENERATION=true` and the application environment is not production.

## Testing

Tests proceed red-green-refactor in this order:

1. `BusinessCode` values and migration contract.
2. Create-card validation, ownership context, required idempotency key, replay, and conflict.
3. Redis Stream publish, consume, ACK, claim, and duplicate-publication behavior using an isolated Stream key.
4. Worker success, provider failure, invalid result, duplicate message, and terminal-state handling with a fake generator.
5. Card detail ownership and safe failure response.
6. Manual retry constraints and parent-job relationship.
7. Compensation publication without automatic provider retry.
8. Full PHPUnit, migration idempotency, PHP syntax, Composer, Nginx, and HTTP smoke tests.
9. One real asynchronous Coze smoke test after all deterministic tests pass.

## Acceptance criteria

- The create endpoint returns HTTP 202 without waiting for Coze.
- Concurrent requests with the same user/key produce one Card and one Job.
- Reusing a key with different content returns `IDEMPOTENCY_CONFLICT`.
- A restarted consumer can claim and finish pending messages.
- Failed AI calls are never automatically executed again.
- Users cannot read or retry another user's card.
- No API, database field, or log leaks PATs, raw provider exceptions, or `reasoning_content`.
- The original synchronous endpoint is unavailable unless the explicit development flag is enabled.
