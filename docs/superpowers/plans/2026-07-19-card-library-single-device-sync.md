# Card Library And Single-Device Sync Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a single-active-device card library with editable cards, favorites, normalized tags, safe regeneration, permanent deletion tombstones, and user-level monotonic incremental sync.

**Architecture:** A locked `users.sync_version` serializes client-visible mutations, while each resource stores the assigned version. Controllers call one Business; Businesses own validation, ownership, transactions, and sync allocation; Models provide persistence scopes; Services remain limited to token storage, Coze, image, and other infrastructure boundaries.

**Tech Stack:** PHP 8.2, Webman 2.2, Workerman 5, MySQL 8, Redis 8, PHPUnit 11.

## Global Constraints

- Follow `AGENTS.md`; Controllers contain no queries or business rules.
- Run PHP, Composer, migrations, and PHPUnit only inside container `php82`.
- Add only migration `0007_add_card_library_and_sync.sql`; never edit migrations `0001` through `0006`.
- Users have exactly one active session version; replacement uses the existing public `UNAUTHENTICATED` error.
- All card/tag/relation mutations lock the owning user and allocate one monotonic sync version in the same transaction.
- Deleted card/tag/relation tombstones remain until account deletion.
- Never expose Coze temporary URLs, pending regeneration payloads, raw exceptions, credentials, or another user's resource existence.
- Regeneration preserves visible card content and image until text and replacement image are both ready.
- Behavior changes use strict RED → GREEN → refactor and each task ends in a focused commit.

---

### Task 1: Migration, Models, And Sync Version Foundation

**Files:**
- Create: `database/migrations/0007_add_card_library_and_sync.sql`
- Create: `app/models/Tag.php`
- Create: `app/models/MemoryCardTag.php`
- Create: `app/businesses/SyncVersionBusiness.php`
- Modify: `app/models/User.php`
- Modify: `app/models/RefreshToken.php`
- Modify: `app/models/MemoryCard.php`
- Modify: `app/models/AiGenerationJob.php`
- Modify: `app/businesses/CreateMemoryCardBusiness.php`
- Modify: `app/common/enums/BusinessCode.php`
- Create: `tests/Unit/CardLibraryMigrationContractTest.php`
- Modify: `tests/Unit/DatabaseMigrationContractTest.php`
- Modify: `tests/Unit/BusinessCodeTest.php`

**Interfaces:**
- Produces: `SyncVersionBusiness::nextLocked(User $user): int`.
- Produces: `Tag::forUser(int $userId)`, `MemoryCardTag::forUser(int $userId)` query scopes.
- Produces columns and model casts used by Tasks 2–9.
- Makes every newly submitted card visible to `cursor=0` through an allocated sync version.

- [ ] **Step 1: Write failing migration, model, enum, and allocator tests**

Assert `0007` adds `users.sync_version/session_version`, `refresh_tokens.session_version`, card versions/favorite/deletion, `tags`, `memory_card_tags`, and Job operation/pending payload. Assert new BusinessCode values:

```php
self::assertSame('CARD_VERSION_CONFLICT', BusinessCode::CardVersionConflict->value);
self::assertSame('CARD_DELETED', BusinessCode::CardDeleted->value);
self::assertSame('INVALID_CURSOR', BusinessCode::InvalidCursor->value);
self::assertSame('TAG_NOT_FOUND', BusinessCode::TagNotFound->value);
self::assertSame('TAG_NAME_CONFLICT', BusinessCode::TagNameConflict->value);
```

Test allocator inside a transaction:

```php
$user = User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();
$version = (new SyncVersionBusiness())->nextLocked($user);
self::assertSame(1, $version);
self::assertSame(1, (int) User::query()->findOrFail($userId)->sync_version);
```

- [ ] **Step 2: Run focused tests and confirm RED**

```bash
docker exec -w /www/english-memory/.worktrees/stage-5-card-library php82 \
  ./vendor/bin/phpunit tests/Unit/CardLibraryMigrationContractTest.php tests/Unit/BusinessCodeTest.php
```

Expected: missing migration/classes/enum cases.

- [ ] **Step 3: Add migration and models**

Migration requirements:

```sql
ALTER TABLE `users`
    ADD COLUMN `sync_version` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN `session_version` BIGINT UNSIGNED NOT NULL DEFAULT 0;

ALTER TABLE `refresh_tokens`
    ADD COLUMN `session_version` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    ADD KEY `refresh_tokens_user_session_index` (`user_id`, `session_version`, `revoked_at`);

ALTER TABLE `memory_cards`
    ADD COLUMN `sync_version` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN `content_version` BIGINT UNSIGNED NOT NULL DEFAULT 1,
    ADD COLUMN `is_favorite` TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN `deleted_at` DATETIME NULL;
```

Create normalized `tags` and `memory_card_tags` with cascading user/card/tag foreign keys, unique `(user_id, normalized_name)` and `(memory_card_id, tag_id)`, plus sync lookup indexes. Add Job `operation` and nullable `pending_card_payload`.

Backfill every existing card with a deterministic per-user version using MySQL 8 `ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY id)`, then update each `users.sync_version` to that user's maximum card version. No active pre-migration card may remain at version zero.

- [ ] **Step 4: Implement the locked allocator**

```php
public function nextLocked(User $user): int
{
    $next = (int) $user->sync_version + 1;
    $user->sync_version = $next;
    $user->save();
    return $next;
}
```

The caller must obtain the User with `lockForUpdate()` in its transaction.

Update `CreateMemoryCardBusiness` so its creation transaction locks the user, allocates a version, and writes it to the new card. Extend the existing create-controller tests to assert the card and user share the same nonzero version.

- [ ] **Step 5: Apply migration twice, run full tests, and commit**

```bash
docker exec -w /www/english-memory/.worktrees/stage-5-card-library php82 php scripts/migrate.php
docker exec -w /www/english-memory/.worktrees/stage-5-card-library php82 php scripts/migrate.php
docker exec -w /www/english-memory/.worktrees/stage-5-card-library php82 ./vendor/bin/phpunit
git add database/migrations/0007_add_card_library_and_sync.sql app/models app/businesses/SyncVersionBusiness.php app/businesses/CreateMemoryCardBusiness.php app/common/enums/BusinessCode.php tests
git commit -m "feat: add card library sync foundation"
```

---

### Task 2: Enforce One Active Device Session

**Files:**
- Create: `app/entities/AccessTokenIdentityEntity.php`
- Modify: `app/services/TokenService.php`
- Modify: `app/businesses/LoginBusiness.php`
- Modify: `app/businesses/RefreshSessionBusiness.php`
- Modify: `app/businesses/LogoutBusiness.php`
- Modify: `app/middleware/Authenticate.php`
- Modify: `tests/Unit/TokenServiceTest.php`
- Modify: `tests/Feature/LoginControllerTest.php`
- Modify: `tests/Feature/RefreshSessionControllerTest.php`
- Modify: `tests/Feature/LogoutControllerTest.php`
- Modify: `tests/Feature/AuthenticateTest.php`

**Interfaces:**
- `TokenService::issueAccessToken(int $userId, int $sessionVersion): array`.
- `TokenService::resolveAccessToken(string $token): ?AccessTokenIdentityEntity`.
- `AccessTokenIdentityEntity::userId(): int`, `sessionVersion(): int`.

- [ ] **Step 1: Write failing replacement-session tests**

Cover:

```php
$first = $this->login($identity, $password, 'phone-a');
$second = $this->login($identity, $password, 'phone-b');
self::assertSame(401, $this->me($firstAccess)->getStatusCode());
self::assertSame(401, $this->refresh($firstRefresh)->getStatusCode());
self::assertSame(200, $this->me($secondAccess)->getStatusCode());
```

Also assert refresh preserves the active version, logout increments it and invalidates all Access Tokens issued within that session, and stored Redis token values contain no plaintext bearer token.

- [ ] **Step 2: Run focused tests and confirm RED**

```bash
docker exec -w /www/english-memory/.worktrees/stage-5-card-library php82 \
  ./vendor/bin/phpunit --filter 'TokenServiceTest|LoginControllerTest|RefreshSessionControllerTest|LogoutControllerTest|AuthenticateTest'
```

Expected: first session remains valid.

- [ ] **Step 3: Bind access tokens to session versions**

Store JSON as the Redis value:

```php
json_encode(['user_id' => $userId, 'session_version' => $sessionVersion], JSON_THROW_ON_ERROR)
```

Return a typed `AccessTokenIdentityEntity` after strict JSON/type validation. Do not add Redis key scans.

- [ ] **Step 4: Serialize login, refresh, logout, and authentication**

Login transaction locks the user, increments `session_version`, revokes all active Refresh Tokens, creates the new Refresh Token with the committed version, and updates `last_login_at`. Refresh requires row version equality with the locked user. Logout locks the user, increments its version, and revokes every active Refresh Token. Middleware compares the Access identity version to the active user's version before setting request ownership.

- [ ] **Step 5: Run focused/full tests and commit**

```bash
docker exec -w /www/english-memory/.worktrees/stage-5-card-library php82 ./vendor/bin/phpunit --filter 'TokenServiceTest|LoginControllerTest|RefreshSessionControllerTest|LogoutControllerTest|AuthenticateTest'
docker exec -w /www/english-memory/.worktrees/stage-5-card-library php82 ./vendor/bin/phpunit
git add app/entities/AccessTokenIdentityEntity.php app/services/TokenService.php app/businesses/LoginBusiness.php app/businesses/RefreshSessionBusiness.php app/businesses/LogoutBusiness.php app/middleware/Authenticate.php tests
git commit -m "feat: enforce one active device session"
```

---

### Task 3: Shared Card Representation And Enriched Detail

**Files:**
- Create: `app/entities/MemoryCardViewEntity.php`
- Create: `app/businesses/MemoryCardViewBusiness.php`
- Modify: `app/businesses/GetMemoryCardBusiness.php`
- Modify: `app/entities/AsyncMemoryCardResultEntity.php`
- Modify: `tests/Feature/MemoryCardAsyncLifecycleTest.php`
- Create: `tests/Unit/Businesses/MemoryCardViewBusinessTest.php`

**Interfaces:**
- `MemoryCardViewBusiness::build(int $userId, MemoryCard $card, bool $includeLatestJob = true): MemoryCardViewEntity`.
- `MemoryCardViewEntity::toArray(): array` is used by detail, list, update conflict, and sync.

- [ ] **Step 1: Write failing view/detail tests**

Assert detail includes `is_favorite`, `content_version`, `sync_version`, active tags ordered by normalized name, and latest Job, while a deleted card returns `CARD_NOT_FOUND`.

- [ ] **Step 2: Run tests and confirm RED**

```bash
docker exec -w /www/english-memory/.worktrees/stage-5-card-library php82 ./vendor/bin/phpunit --filter 'MemoryCardViewBusinessTest|MemoryCardAsyncLifecycleTest'
```

- [ ] **Step 3: Implement the typed view and shared builder**

The Entity constructor exposes explicit card, tag, and optional Job arrays through `toArray()`. The Business queries only active owned relations and never includes `pending_card_payload`, provider payload, image storage key, or deleted content.

- [ ] **Step 4: Refactor detail to use the builder**

`GetMemoryCardBusiness` must query `whereNull('deleted_at')` and return the view without duplicating presentation logic.

- [ ] **Step 5: Run full tests and commit**

```bash
docker exec -w /www/english-memory/.worktrees/stage-5-card-library php82 ./vendor/bin/phpunit
git add app/entities/MemoryCardViewEntity.php app/businesses/MemoryCardViewBusiness.php app/businesses/GetMemoryCardBusiness.php app/entities/AsyncMemoryCardResultEntity.php tests
git commit -m "feat: expose versioned card details"
```

---

### Task 4: Card Library Listing, Search, Filters, And Keyset Pagination

**Files:**
- Create: `app/entities/CardLibraryResultEntity.php`
- Create: `app/businesses/ListMemoryCardsBusiness.php`
- Create: `app/controllers/ListMemoryCardsController.php`
- Modify: `config/route.php`
- Create: `tests/Feature/ListMemoryCardsControllerTest.php`

**Interfaces:**
- `ListMemoryCardsBusiness::list(int $userId, array $filters): CardLibraryResultEntity`.
- Opaque cursor encodes JSON `{"created_at":"Y-m-d H:i:s","id":123}` using URL-safe base64.

- [ ] **Step 1: Write failing route/business tests**

Cover default 20/max 100, stable descending keyset pagination, ownership, deleted exclusion, `q`, content type, favorite, tag, latest Job status, escaped `%`/`_`, invalid cursor, and response envelope.

- [ ] **Step 2: Run focused tests and confirm RED**

```bash
docker exec -w /www/english-memory/.worktrees/stage-5-card-library php82 ./vendor/bin/phpunit tests/Feature/ListMemoryCardsControllerTest.php
```

- [ ] **Step 3: Implement query and cursor validation**

Use the strict keyset predicate:

```php
$query->where(function ($query) use ($createdAt, $id): void {
    $query->where('created_at', '<', $createdAt)
        ->orWhere(function ($query) use ($createdAt, $id): void {
            $query->where('created_at', $createdAt)->where('id', '<', $id);
        });
});
```

Escape search metacharacters and use `LIKE ... ESCAPE '\\'`. Fetch `limit + 1`, build views, and return `next_cursor` only when another page exists.

- [ ] **Step 4: Add controller and explicit route**

Register authenticated `GET /api/memory-cards` before `/{id}`. Controller passes only request query scalars to one Business.

- [ ] **Step 5: Run full tests and commit**

```bash
docker exec -w /www/english-memory/.worktrees/stage-5-card-library php82 ./vendor/bin/phpunit
git add app/entities/CardLibraryResultEntity.php app/businesses/ListMemoryCardsBusiness.php app/controllers/ListMemoryCardsController.php config/route.php tests/Feature/ListMemoryCardsControllerTest.php
git commit -m "feat: list and filter memory cards"
```

---

### Task 5: Edit Cards, Favorite, And Reconcile Tags

**Files:**
- Create: `app/entities/MemoryCardMutationResultEntity.php`
- Create: `app/businesses/TagNameBusiness.php`
- Create: `app/businesses/UpdateMemoryCardBusiness.php`
- Create: `app/controllers/UpdateMemoryCardController.php`
- Modify: `config/route.php`
- Create: `tests/Unit/Businesses/TagNameBusinessTest.php`
- Create: `tests/Feature/UpdateMemoryCardControllerTest.php`

**Interfaces:**
- `TagNameBusiness::normalizeList(array $names): ?array` returns unique `[{name, normalized_name}]`; invalid input returns null for the calling mutation Business to map to `INVALID_INPUT`.
- `UpdateMemoryCardBusiness::update(int $userId, int $cardId, int $contentVersion, array $changes): MemoryCardMutationResultEntity`.

- [ ] **Step 1: Write failing validation, ownership, conflict, and reconciliation tests**

Cover editable whitelist, unknown fields, empty meanings, incomplete example, field size limits, favorite toggling, tag trim/collapse/case dedupe, create/restore/remove relations, one shared sync version, deleted cards, another user's card, and HTTP 409 conflict containing the latest server card.

- [ ] **Step 2: Run focused tests and confirm RED**

```bash
docker exec -w /www/english-memory/.worktrees/stage-5-card-library php82 ./vendor/bin/phpunit --filter 'TagNameBusinessTest|UpdateMemoryCardControllerTest'
```

- [ ] **Step 3: Implement tag normalization**

Use Unicode whitespace collapse and lowercase:

```php
$name = preg_replace('/\s+/u', ' ', trim($name));
$normalized = mb_strtolower($name, 'UTF-8');
```

Require 1–40 Unicode characters and at most 20 tags per card.

- [ ] **Step 4: Implement mutation transaction**

Lock user then owned active card, compare `content_version`, validate/merge only approved `card_payload` keys, allocate one sync version, update card, create/restore tags and relations, and soft-delete removed relations. Increment card `content_version` exactly once.

- [ ] **Step 5: Add controller/route, run full tests, and commit**

```bash
docker exec -w /www/english-memory/.worktrees/stage-5-card-library php82 ./vendor/bin/phpunit
git add app/entities/MemoryCardMutationResultEntity.php app/businesses/TagNameBusiness.php app/businesses/UpdateMemoryCardBusiness.php app/controllers/UpdateMemoryCardController.php config/route.php tests
git commit -m "feat: edit favorite and tag memory cards"
```

---

### Task 6: Tag Library Rename And Delete

**Files:**
- Create: `app/entities/TagResultEntity.php`
- Create: `app/businesses/ListTagsBusiness.php`
- Create: `app/businesses/RenameTagBusiness.php`
- Create: `app/businesses/DeleteTagBusiness.php`
- Create: `app/controllers/ListTagsController.php`
- Create: `app/controllers/RenameTagController.php`
- Create: `app/controllers/DeleteTagController.php`
- Modify: `config/route.php`
- Create: `tests/Feature/TagLibraryControllerTest.php`

**Interfaces:**
- `ListTagsBusiness::list(int $userId): TagResultEntity`.
- `RenameTagBusiness::rename(int $userId, int $tagId, int $syncVersion, string $name): TagResultEntity`.
- `DeleteTagBusiness::delete(int $userId, int $tagId, int $syncVersion): TagResultEntity`.

- [ ] **Step 1: Write failing list/rename/delete tests**

Cover ownership, active card counts, normalized conflict, stale tag version, restore semantics, deletion of all active relations with the same newly allocated sync version, idempotent delete, and explicit authenticated routes.

- [ ] **Step 2: Run focused tests and confirm RED**

```bash
docker exec -w /www/english-memory/.worktrees/stage-5-card-library php82 ./vendor/bin/phpunit tests/Feature/TagLibraryControllerTest.php
```

- [ ] **Step 3: Implement tag Businesses**

Each mutation locks user then owned tag, validates its current `sync_version`, allocates one version, and updates all affected rows inside one transaction. Return `TAG_NOT_FOUND` for missing/foreign/deleted tags and `TAG_NAME_CONFLICT` for an active normalized-name collision.

- [ ] **Step 4: Add thin controllers and routes**

Register `GET /api/tags`, `PATCH /api/tags/{id}`, and `DELETE /api/tags/{id}` behind `Authenticate`.

- [ ] **Step 5: Run full tests and commit**

```bash
docker exec -w /www/english-memory/.worktrees/stage-5-card-library php82 ./vendor/bin/phpunit
git add app/entities/TagResultEntity.php app/businesses/ListTagsBusiness.php app/businesses/RenameTagBusiness.php app/businesses/DeleteTagBusiness.php app/controllers config/route.php tests/Feature/TagLibraryControllerTest.php
git commit -m "feat: manage memory card tags"
```

---

### Task 7: Soft Delete Cards And Clean Owned Images

**Files:**
- Create: `app/businesses/DeleteMemoryCardBusiness.php`
- Create: `app/controllers/DeleteMemoryCardController.php`
- Modify: `app/businesses/DeleteStoredMemoryCardImagesBusiness.php`
- Modify: `config/route.php`
- Create: `tests/Feature/DeleteMemoryCardControllerTest.php`
- Modify: `tests/Unit/Businesses/DeleteStoredMemoryCardImagesBusinessTest.php`

**Interfaces:**
- `DeleteMemoryCardBusiness::delete(int $userId, int $cardId, int $contentVersion): MemoryCardMutationResultEntity`.
- `DeleteStoredMemoryCardImagesBusiness::deleteCard(int $userId, int $cardId): void` remains idempotent and throws before database tombstoning if storage cleanup fails.

- [ ] **Step 1: Write failing deletion and cleanup tests**

Cover owner/version validation, content sanitization, review event deletion, Job payload sanitization, relation tombstones, one sync version, physical image/metadata cleanup, storage failure leaving a deleted tombstone plus retained image metadata, resumed cleanup on repeated deletion, and exclusion from all existing card/retry endpoints.

- [ ] **Step 2: Run focused tests and confirm RED**

```bash
docker exec -w /www/english-memory/.worktrees/stage-5-card-library php82 ./vendor/bin/phpunit --filter 'DeleteMemoryCardControllerTest|DeleteStoredMemoryCardImagesBusinessTest'
```

- [ ] **Step 3: Implement deletion orchestration**

In one transaction lock user/card, validate the version, allocate the sync version, sanitize the card and associated Job JSON/error fields, delete review events, and soft-delete active relations. Commit the tombstone while retaining `memory_card_images` as the cleanup manifest, then call owned-image cleanup. An already deleted card skips version allocation and only resumes cleanup when metadata still exists.

- [ ] **Step 4: Add controller/route and deleted guards**

Register authenticated `DELETE /api/memory-cards/{id}`. Update create retry and regeneration eligibility queries to reject deleted cards; detail/list already exclude them.

- [ ] **Step 5: Run full tests and commit**

```bash
docker exec -w /www/english-memory/.worktrees/stage-5-card-library php82 ./vendor/bin/phpunit
git add app/businesses/DeleteMemoryCardBusiness.php app/controllers/DeleteMemoryCardController.php app/businesses/DeleteStoredMemoryCardImagesBusiness.php config/route.php tests
git commit -m "feat: soft delete memory cards"
```

---

### Task 8: Atomic Card Regeneration

**Files:**
- Create: `app/businesses/RegenerateMemoryCardBusiness.php`
- Create: `app/controllers/RegenerateMemoryCardController.php`
- Modify: `app/businesses/ProcessAiGenerationJobBusiness.php`
- Modify: `app/businesses/ImportMemoryCardImageBusiness.php`
- Modify: `app/entities/AiGenerationResultEntity.php` only if typed operation context is required
- Modify: `config/route.php`
- Modify: `config/dependence.php`
- Create: `tests/Feature/RegenerateMemoryCardControllerTest.php`
- Modify: `tests/Unit/Businesses/ProcessAiGenerationJobBusinessTest.php`
- Modify: `tests/Unit/Businesses/ImportMemoryCardImageBusinessTest.php`
- Modify: `tests/Unit/AiGenerationWorkerTest.php`

**Interfaces:**
- `RegenerateMemoryCardBusiness::regenerate(int $userId, int $cardId, string $idempotencyKey): AsyncMemoryCardResultEntity`.
- `ImportMemoryCardImageBusiness::import(..., ?array $replacementCardPayload = null): void`; non-null replacement means atomic regeneration swap.

- [ ] **Step 1: Write failing submission tests**

Assert terminal active cards can regenerate, nonterminal/deleted/foreign cards cannot, new idempotency key is required, replay is stable, operation is `regenerate`, parent Job is set, and favorite/tags/review fields are untouched.

- [ ] **Step 2: Write failing Worker/import tests**

Assert regeneration stores `pending_card_payload`, leaves the visible card/image unchanged during `generating_image`, atomically swaps payload/image/metadata on success, increments content/sync versions once, deletes old files after commit, and preserves the old visible card on every stable provider/image failure.

- [ ] **Step 3: Run focused tests and confirm RED**

```bash
docker exec -w /www/english-memory/.worktrees/stage-5-card-library php82 ./vendor/bin/phpunit --filter 'RegenerateMemoryCardControllerTest|ProcessAiGenerationJobBusinessTest|ImportMemoryCardImageBusinessTest|AiGenerationWorkerTest'
```

- [ ] **Step 4: Implement regeneration submission and operation-aware Worker**

For `regenerate`, persist validated text only to Job pending payload, set `generating_image`, and pass it to image import. For `create`/`retry`, retain Stage 4 text-first behavior and allocate a sync version whenever generated text becomes visible.

- [ ] **Step 5: Implement atomic import swap**

Inside the image metadata transaction, lock user/card/job, allocate a sync version, replace generated card fields and image metadata, increment content version for regeneration, clear pending payload, and complete the Job. Initial/retry image completion also allocates a version for the newly visible owned image without incrementing user-edit `content_version`. Delete old storage keys only after commit. On precommit failure remove only newly stored keys.

- [ ] **Step 6: Add controller/routes, run full tests, and commit**

```bash
docker exec -w /www/english-memory/.worktrees/stage-5-card-library php82 ./vendor/bin/phpunit
git add app/businesses app/controllers app/entities config tests
git commit -m "feat: regenerate cards atomically"
```

---

### Task 9: Monotonic Incremental Sync Feed

**Files:**
- Create: `app/entities/SyncChangesResultEntity.php`
- Create: `app/businesses/GetSyncChangesBusiness.php`
- Create: `app/controllers/GetSyncChangesController.php`
- Modify: `config/route.php`
- Create: `tests/Feature/GetSyncChangesControllerTest.php`

**Interfaces:**
- `GetSyncChangesBusiness::get(int $userId, int $cursor, int $limit = 200): SyncChangesResultEntity`.
- `SyncChangesResultEntity` returns `changes.cards/tags/card_tags`, `next_cursor`, and `has_more`.

- [ ] **Step 1: Write failing incremental sync tests**

Cover cursor zero, empty changes, ownership, invalid/negative/future cursor, active records, minimal tombstones, tag/relation changes, multiple records sharing a version, not splitting one version at the requested limit, stable upper bound, `has_more`, and retrying the same cursor.

- [ ] **Step 2: Run focused tests and confirm RED**

```bash
docker exec -w /www/english-memory/.worktrees/stage-5-card-library php82 ./vendor/bin/phpunit tests/Feature/GetSyncChangesControllerTest.php
```

- [ ] **Step 3: Implement version-window selection**

Read the user's current `sync_version` as the upper bound. Query candidate versions greater than cursor and at most upper bound across all three resource tables, choose complete versions up to the limit, then load each collection. When the first version alone exceeds the limit, return that complete version. Reject cursor greater than upper bound with `INVALID_CURSOR`.

- [ ] **Step 4: Build active representations and minimal tombstones**

Active cards use `MemoryCardViewBusiness`; deleted cards contain only `id`, `content_version`, `sync_version`, and `deleted_at`. Deleted tags/relations similarly omit names and content.

- [ ] **Step 5: Add controller/route, run full tests, and commit**

```bash
docker exec -w /www/english-memory/.worktrees/stage-5-card-library php82 ./vendor/bin/phpunit
git add app/entities/SyncChangesResultEntity.php app/businesses/GetSyncChangesBusiness.php app/controllers/GetSyncChangesController.php config/route.php tests/Feature/GetSyncChangesControllerTest.php
git commit -m "feat: add incremental card sync"
```

---

### Task 10: Runtime Verification And Stage Handoff

**Files:**
- Modify: `PROJECT_PLAN.md`
- Modify: `docs/superpowers/plans/2026-07-19-card-library-single-device-sync.md`

**Interfaces:**
- Produces verified Stage 5 handoff and the Stage 6 review-system entry point.

- [x] **Step 1: Run deterministic verification**

```bash
docker exec -w /www/english-memory/.worktrees/stage-5-card-library php82 php scripts/migrate.php
docker exec -w /www/english-memory/.worktrees/stage-5-card-library php82 php scripts/migrate.php
docker exec -w /www/english-memory/.worktrees/stage-5-card-library php82 composer validate --no-check-publish
docker exec -w /www/english-memory/.worktrees/stage-5-card-library php82 ./vendor/bin/phpunit
docker exec redis redis-cli PING
docker exec nginx nginx -t
git diff --check
```

Syntax-check every changed PHP file and scan tracked files for PATs, bearer values, Coze temporary URLs in persistence/API code, and local temporary paths in Entities/Controllers.

- [x] **Step 2: Merge locally and restart Webman**

Use `superpowers:finishing-a-development-branch`. After merge, restart from `/www/english-memory` and require consumer, compensation, webman, and monitor exit counts to remain zero.

- [x] **Step 3: Run controlled HTTP smoke without Coze**

Create one temporary user/card fixture through application Businesses, then use HTTP to:

1. log in on device A and prove device B login invalidates A;
2. list, edit generated fields, favorite, and attach normalized tags;
3. fetch sync from cursor zero and then from the returned cursor;
4. rename/delete a tag;
5. delete the card and receive its tombstone in sync;
6. verify image files/metadata and all temporary account data are cleaned.

Do not call Coze unless Task 8 changed provider request/response behavior beyond operation orchestration.

- [x] **Step 4: Update handoff with exact evidence**

Record migration `0007`, final test/assertion count, routes, session replacement result, pagination/sync cursor evidence, tombstone/image cleanup result, Worker status, branch state, and the next Stage 6 task.

- [x] **Step 5: Commit docs and run fresh final verification**

```bash
git add PROJECT_PLAN.md docs/superpowers/plans/2026-07-19-card-library-single-device-sync.md
git commit -m "docs: complete card library sync handoff"
docker exec -w /www/english-memory php82 ./vendor/bin/phpunit
git status --short --branch
```

### Verified handoff evidence

- Merged `codex/stage-5-card-library` into local `master`; the feature worktree and branch were removed after merged-result tests passed.
- Migration `0007_add_card_library_and_sync.sql` was applied previously and skipped cleanly on two consecutive verification runs.
- Final deterministic baseline before this documentation commit: 156 tests, 724 assertions; 64 changed PHP files passed `php -l`; `git diff --check` and tracked secret/temporary-URL scans were clean.
- Runtime checks: Composer configuration valid with the existing empty-prefix PSR-4 performance warning; Redis returned `PONG`; `nginx -t` succeeded.
- After a main-directory Webman restart, ai-generation-consumer, ai-generation-compensation, webman, and monitor all reported exit status/count `0/0`.
- Controlled HTTP smoke did not call Coze. Device replacement returned old/new session statuses `401/200`; list/edit/favorite/tag normalization, tag rename/delete, cursor-zero plus delta sync, card deletion tombstone, and owned image metadata cleanup all succeeded. The smoke account was removed.
- Stage 6 starts with the review queue and idempotent answer-submission contract, followed by scheduling state transitions and timezone boundary tests.
