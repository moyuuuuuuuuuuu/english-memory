# English Memory Project Instructions

## Product scope

Build a mobile-first English learning product for Chinese learners. The core loop is: capture or enter English, generate a strong mnemonic card through the published Coze workflow, save it, and review it through game-like spaced repetition.

The repository root is the Webman backend. Put the Flutter Android/iOS client in `mobile/` when mobile work starts. Do not add an extra `server/` wrapper directory.

## Current stack

- Backend: PHP 8.2, Webman 2.2, Workerman 5.
- Data: MySQL 8 for durable records; Redis 8 for sessions, rate limits, queues, and short-lived cache.
- AI: Coze China workflow `7663771858193448987`; its PAT must remain server-side.
- HTTP: Nginx host `e.test` proxies to `php82:8787` with 240-second AI timeouts.
- Mobile: Flutter for Android APK and iOS source; iOS signing is outside this repository's Linux build environment.

## Development environment

Run PHP and Composer inside the existing `php82` container because the host shell has no configured PHP runtime.

```bash
docker exec -w /www/english-memory php82 composer install
docker exec -w /www/english-memory php82 vendor/bin/phpunit
docker exec -w /www/english-memory php82 php start.php start -d
docker exec -w /www/english-memory php82 php start.php stop
```

Never print `.env`, `COZE_ACCESS_TOKEN`, database passwords, mail credentials, or bearer headers. It is acceptable to report only whether required keys are `SET` or `MISSING`.

## Backend directory structure

New backend code must follow this structure:

```text
app/
├── common/
│   ├── base/          # Shared base classes with real cross-domain value
│   ├── enums/         # PHP enums and stable value sets
│   ├── traits/        # Small reusable language-level behavior
│   ├── exceptions/    # Shared domain and application exceptions
│   └── helpers/       # Focused stateless helpers; never a miscellaneous dump
├── controllers/       # HTTP entry points; business invocation only
├── businesses/        # All application orchestration and concrete system logic
├── services/          # Third-party and infrastructure integrations
│   └── contracts/     # Replaceable service interfaces
├── entities/          # Typed domain/data-transfer entities
├── models/            # Database persistence models and query scopes
├── middleware/        # Authentication and HTTP cross-cutting concerns
└── processes/         # Long-running workers and queue consumers
```

Use plural directory names for all new layers: `controllers`, `businesses`, `services`, `entities`, `models`, `processes`. Existing Webman scaffold directories and the current singular `app/controller`, `app/service`, and `app/contract` paths are legacy. Migrate touched files into the plural structure before adding more features; do not maintain parallel singular and plural implementations.

## Layer responsibilities

### Controllers

- A controller only handles HTTP input/output and the call relationship to one business class.
- Controllers may read request fields, pass them to a business, and map the business result to the public response envelope.
- Controllers must not contain business rules, database queries, transaction handling, provider calls, retry logic, image processing, or review scheduling.
- Controllers must not call services or models directly.

### Businesses

- Put all concrete in-system logic and use-case orchestration in `app/businesses/`.
- A business validates use-case input, enforces authorization and ownership, opens transactions, coordinates models and services, builds entities, and decides application-level errors.
- A business may call multiple services and models, but expose one clear public method per use case where practical.
- Businesses must not depend on HTTP request or response classes. Accept scalars, arrays, enums, or entities and return entities or documented result objects.

### Services

- Put every third-party or infrastructure integration in `app/services/`: Coze, mail, object storage, SMS, external dictionaries, OCR providers, payment providers, and remote HTTP APIs.
- Services translate provider-specific requests and responses into application-owned types. Provider payload shapes must not leak into controllers or businesses.
- Keep replaceable service interfaces in `app/services/contracts/` and inject those interfaces into businesses.
- Services must not contain product decisions or call controllers/businesses.
- Coze nested JSON decoding belongs in `CozeWorkflowService`; no upper layer should know about `card_json` strings or `reasoning_content`.

### Entities

- Put typed domain and transfer objects in `app/entities/`.
- Entities represent stable application concepts such as `MemoryCardEntity`, `AiGenerationResultEntity`, `ReviewAnswerEntity`, and `AuthenticatedUserEntity`.
- Prefer explicit typed properties, constructor validation, named constructors such as `fromArray()`, and serialization methods such as `toArray()`.
- Entities must not query databases, call providers, read global configuration, or depend on Webman HTTP classes.
- Do not use entities as generic untyped bags. If the data has no stable domain meaning, keep it local to the business method.

### Models

- Models only represent persistence and reusable query scopes.
- Models must not call services, send mail, invoke Coze, or contain multi-step use-case orchestration.
- Use migrations for every schema change. Never make undocumented manual production schema edits.

### Database migrations

- Every SQL schema change must be delivered as a new versioned migration file under `database/migrations/`.
- Use ascending filenames such as `0004_add_due_index_to_memory_cards.sql`; never edit an already applied migration.
- Creating or altering tables, columns, indexes, constraints, views, triggers, or seed reference structures directly in a controller, business, service, model, startup hook, or production console is forbidden.
- Keep migrations forward-only and safe to run once. When practical, use explicit `IF EXISTS` or `IF NOT EXISTS` guards without hiding incompatible schema drift.
- Execute migrations with `php scripts/migrate.php`. The runner records each filename and SHA-256 checksum in the `migrations` table and must reject modified applied files.
- Data backfills that accompany a schema change belong in a separately named migration; large online backfills require a resumable process instead of blocking application startup.

### Common

- `app/common/` contains code genuinely shared across multiple domains.
- Put base classes in `common/base`, enums in `common/enums`, reusable traits in `common/traits`, and shared exceptions in `common/exceptions`.
- Do not move domain-specific logic into `common` merely because two files call it. Prefer the owning business or service until reuse is proven.
- Common code must not depend on controllers, businesses, services, or models.

## Allowed dependency direction

```text
Route -> Controller -> Business -> Service / Model
                              -> Entity
                              -> Common
Service -> Contract / Entity / Common
Model   -> Entity / Common
```

Reverse dependencies are forbidden. In particular:

- Business code never imports controllers.
- Services never call businesses or controllers.
- Models never call businesses or services.
- Entities and common code never depend on Webman HTTP, providers, or persistence.

Use explicit routes in `config/route.php`; do not enable broad automatic controller routing.

## API conventions

Success responses use:

```json
{"success":true,"data":{}}
```

Failure responses use:

```json
{"success":false,"error":{"code":"STABLE_CODE","message":"Chinese user-facing message"}}
```

Validate and trim all client input before calling Coze. Allowed content types are `word` and `sentence`; allowed memory styles are `auto`, `phonetic_story`, and `semantic_scene`.

Do not return Coze `reasoning_content`, PATs, provider debug URLs, or raw exception messages to clients. An `execute_id` may be returned for support correlation.

## AI and image rules

- Default `memory_style` is `auto`.
- Preserve both mnemonic modes: natural phonetic stories and semantic scenes. Do not force weak Chinese homophones.
- Coze-hosted image URLs are temporary. The backend must download a successful image promptly, validate MIME type and size, store it under application-controlled storage, and persist only the application URL.
- AI generation is slow. The target architecture creates a card/job immediately and finishes text/image generation asynchronously; do not make every future API depend on a one-minute synchronous request.
- Add rate limiting and per-user idempotency before exposing AI generation to untrusted clients.

## Testing and verification

- Use red-green-refactor for behavior changes: write the failing test, run it and confirm the expected failure, then implement the minimum change.
- Unit-test provider request/response parsing with Guzzle mock handlers.
- Feature-test validation, response envelopes, authorization, ownership, and idempotency.
- Do not claim completion without fresh evidence.

Minimum backend verification:

```bash
docker exec -w /www/english-memory php82 vendor/bin/phpunit
docker exec -w /www/english-memory php82 composer validate --no-check-publish
docker exec nginx nginx -t
```

Run a real Coze smoke test only when provider behavior changed or an end-to-end check is explicitly useful; it consumes tokens and an image-generation call.

## Security and repository hygiene

- Keep `.env` ignored and keep `.env.example` current with empty secret values.
- Do not commit `vendor/`, `runtime/`, IDE workspace files, downloaded AI images, or temporary screenshots.
- Preserve unrelated user changes. Stage explicit paths when committing from a mixed worktree.
- Passwords use `password_hash()` and refresh tokens are stored hashed, revocable, and device-scoped.
- Users may read, edit, and delete only their own cards, images, review history, and account data.

## Product design

- Visual direction: warm white champagne, soft gold light, storybook illustration, restrained particles and gradients.
- Core mobile navigation: Home, Review, Capture, Library, Profile.
- OCR happens locally after the user crops a photo; recognized text remains editable before submission.
- Review modes include image recall, listening/spelling, and Chinese-to-English recall. XP and streaks must not alter the spaced-repetition scheduling algorithm.

## Source of truth

Open `PROJECT_PLAN.md` first. It is the single project-status, execution-order, and device-handoff entry point. Follow `AGENTS.md` for architecture and engineering constraints. Update `PROJECT_PLAN.md` whenever a stage is completed, the test baseline changes, or scope/architecture changes materially. The older detailed plan under `docs/superpowers/plans/` is retained as historical design context only.
