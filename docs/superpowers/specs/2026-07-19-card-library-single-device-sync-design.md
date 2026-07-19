# Card Library And Single-Device Sync Design

**Date:** 2026-07-19
**Status:** Approved recommended direction
**Stage:** 5 — card library and incremental sync

## Goal

Deliver a complete card library for one active device per account: list, search, filter, edit approved generated fields, favorite, tag, regenerate, soft-delete, and reliably restore incremental changes after offline use or reinstall.

The server remains authoritative. A user-level monotonic sync sequence orders every client-visible mutation. Deleted records remain as permanent tombstones until account deletion.

## Scope

Stage 5 includes:

- one active device session per account;
- card list, detail, editable generated fields, favorite, tags, regeneration, and soft deletion;
- keyset pagination for the normal library;
- normalized tags and card-tag relations;
- a user-level monotonic sync cursor and incremental change feed;
- stale-write detection for offline screens;
- immediate owned-image cleanup when a card is deleted;
- preservation of favorite, tags, and review scheduling during regeneration.

Stage 5 does not include:

- multiple simultaneously active devices;
- field-level automatic merge;
- review scheduling algorithms or game statistics;
- account privacy deletion wiring;
- AI rate limiting;
- object storage.

## Chosen Architecture

Use resource tables plus a user-level monotonic sequence. This is preferred over a timestamp cursor, which can miss equal-time writes and deletions, and over a full append-only event log, which adds event compaction and replay complexity not needed for a single active device.

Every client-visible mutation locks the owning `users` row, increments `users.sync_version`, and writes that exact value to all records changed by the transaction. The client sends a numeric cursor and receives records whose `sync_version` is greater than the cursor.

## Database Changes

Add forward-only migration `0007_add_card_library_and_sync.sql`.

### `users`

- `sync_version BIGINT UNSIGNED NOT NULL DEFAULT 0`
- `session_version BIGINT UNSIGNED NOT NULL DEFAULT 0`

The user row is the serialization point for sync mutations and active-session replacement.

### `refresh_tokens`

- `session_version BIGINT UNSIGNED NOT NULL DEFAULT 0`
- index on `(user_id, session_version, revoked_at)`

### `memory_cards`

- `sync_version BIGINT UNSIGNED NOT NULL DEFAULT 0`
- `content_version BIGINT UNSIGNED NOT NULL DEFAULT 1`
- `is_favorite TINYINT(1) NOT NULL DEFAULT 0`
- `deleted_at DATETIME NULL`
- indexes for user/library ordering, favorite filtering, and sync lookup

`content_version` protects a single device from submitting an obsolete offline screen after the same card changed later. It is not a multi-device merge mechanism.

### `tags`

- `id`, `user_id`, `name`, `normalized_name`
- `sync_version`, `deleted_at`, timestamps
- unique `(user_id, normalized_name)`

Reusing a previously deleted normalized name restores the existing tag row instead of creating another identity. Names are trimmed, collapse internal whitespace, are 1–40 Unicode characters, and compare case-insensitively through `normalized_name`.

### `memory_card_tags`

- `id`, `user_id`, `memory_card_id`, `tag_id`
- `sync_version`, `deleted_at`, timestamps
- unique `(memory_card_id, tag_id)`
- ownership lookup and sync indexes

Removing a tag from a card soft-deletes the relation. Re-adding it restores the same relation row.

### `ai_generation_jobs`

- `operation VARCHAR(32) NOT NULL DEFAULT 'create'`
- `pending_card_payload JSON NULL`

Allowed operations are `create`, `retry`, and `regenerate`. A regeneration stores pending generated text on its Job and does not overwrite the existing completed card until the replacement image has also been imported successfully.

## Single-Device Session Contract

Access token storage contains both `user_id` and `session_version`. Authentication loads the active user and accepts the token only when its stored version equals `users.session_version`.

On successful password login, one database transaction:

1. locks the user;
2. increments `session_version`;
3. revokes every unrevoked Refresh Token for that user;
4. creates one Refresh Token bound to the new version;
5. updates `last_login_at`.

The new Access Token is issued with the committed version. All earlier Access Tokens fail immediately on their next authenticated request, and all earlier Refresh Tokens are already revoked.

Refresh rotation requires the token row version to equal the current user version and creates the replacement Refresh Token with the same version. Logout increments the current user session version and revokes all Refresh Tokens, invalidating every Access Token from that session.

Concurrent successful logins serialize on the user row; the later committed login is the only active session.

## Editable Card Contract

Clients may edit only:

- `meanings`;
- `mnemonic_sentence`;
- `story`;
- `example.en` and `example.zh`;
- `is_favorite`;
- tag names.

The original input, normalized word/text, word spelling, phonetic transcription, content type, memory style, image, ownership, review fields, and system fields are read-only. A different generated word, phonetic form, story image, or immutable input requires regeneration.

An edit request includes `content_version`. A mismatch returns HTTP 409 with code `CARD_VERSION_CONFLICT` and the latest server card. No client content is silently applied. A successful edit increments both `content_version` and the user sync sequence.

Payload validation preserves provider-independent card structure and rejects empty meanings, incomplete examples, invalid tag names, unknown fields, and oversized text.

## Regeneration Contract

`POST /api/memory-cards/{id}/regenerate` accepts a new `Idempotency-Key` and creates an asynchronous child Job with operation `regenerate`. It may run only for a non-deleted card whose latest Job is terminal.

Favorite state, tag relations, `next_review_at`, and `review_stage` remain unchanged. AI-generated content and all image variants are replaced as one logical result.

For regeneration:

1. the Worker stores validated generated text in `ai_generation_jobs.pending_card_payload`;
2. the Job enters `generating_image` without changing the visible card;
3. image download, processing, and storage finish;
4. one transaction swaps card payload, normalized text, image URL/key, image metadata, increments `content_version`, allocates a user sync version, and completes the Job;
5. old image files are deleted after commit.

If provider, validation, download, processing, or storage fails, the existing visible card and image remain unchanged and the Job becomes `failed`. A user may submit another regeneration with a new idempotency key.

Initial creation and failed initial-generation retry retain the existing Stage 4 behavior where generated text can be visible before the image completes.

## Deletion Contract

`DELETE /api/memory-cards/{id}` requires the current `content_version`.

Deletion:

- marks `memory_cards.deleted_at`;
- increments `content_version` and assigns a new sync version;
- soft-deletes active card-tag relations with the same sync version;
- removes image metadata and all three owned image files;
- deletes card-specific review events and sanitizes associated AI Job request, provider, pending result, and error payloads while retaining operational status/version metadata;
- prevents the card from normal list, detail, edit, favorite, tag, review, retry, and regeneration endpoints.

The card row remains permanently as a minimal deletion tombstone until account deletion. Its generated text payload is replaced with an empty JSON object, source and normalized text are replaced with empty strings, image fields are cleared, and review scheduling fields are cleared. This reduces retained content while preserving card ID, ownership, deletion time, content version, and sync version.

Deletion is idempotent. The database tombstone commits first while image metadata remains available as a cleanup manifest; the Business then immediately deletes the files and metadata. If storage cleanup fails, the card remains deleted, the endpoint returns a stable storage error, and repeating the same DELETE resumes cleanup from the retained metadata without allocating another sync version.

## Tags

Card edit accepts the complete desired list of tag names. The Business normalizes and deduplicates names, creates or restores owned tags, restores desired relations, and soft-deletes removed relations in the same mutation transaction.

Additional tag endpoints support library UI:

- `GET /api/tags`
- `PATCH /api/tags/{id}` with name and current tag sync version
- `DELETE /api/tags/{id}`

Renaming increments the user sequence. Deleting a tag soft-deletes the tag and all active relations with the same sequence. It does not delete cards.

## HTTP API

### Library

- `GET /api/memory-cards`
  - filters: `q`, `content_type`, `is_favorite`, `tag_id`, `status`
  - opaque keyset `cursor`, default limit 20, maximum 100
  - order: `created_at DESC, id DESC`
  - excludes deleted cards
- `GET /api/memory-cards/{id}`
  - returns tags, favorite, content version, sync version, and latest Job
- `PATCH /api/memory-cards/{id}`
  - edits the approved payload fields, favorite, and complete tag-name set
- `DELETE /api/memory-cards/{id}`
- `POST /api/memory-cards/{id}/regenerate`
- existing `POST /api/memory-cards/{id}/retry` remains only for failed initial creation/retry Jobs

`status` filters against the latest Job status. Search uses escaped SQL `LIKE` over `source_text` and `normalized_text` for the first release; full-text indexing is deferred until measured necessary.

### Incremental Sync

- `GET /api/sync/changes?cursor=<non-negative integer>&limit=<1..500>`

The response contains:

```json
{
  "success": true,
  "data": {
    "changes": {
      "cards": [],
      "tags": [],
      "card_tags": []
    },
    "next_cursor": 42,
    "has_more": false
  }
}
```

Each collection includes active records and tombstones. Tombstones expose only stable IDs, `deleted_at`, and version metadata. Active cards expose the same client-facing representation as the library detail.

The endpoint reads a stable upper bound from `users.sync_version`. It returns changes greater than the supplied cursor and at most that upper bound. If the combined result exceeds the limit, it returns all records belonging to the last included sync version so one logical transaction is never split. `next_cursor` is the highest fully returned version; when there are no changes it equals the supplied cursor.

The client acknowledges nothing. Permanent tombstones make any cursor restart safe, including `cursor=0` after reinstall.

## Layering

Controllers only extract HTTP values and call one Business each.

New Businesses:

- `ListMemoryCardsBusiness`
- `UpdateMemoryCardBusiness`
- `DeleteMemoryCardBusiness`
- `RegenerateMemoryCardBusiness`
- `ListTagsBusiness`
- `RenameTagBusiness`
- `DeleteTagBusiness`
- `GetSyncChangesBusiness`
- `StartSingleDeviceSessionBusiness` only if session orchestration cannot remain focused inside login/logout Businesses

Shared sync sequence allocation belongs in a focused `SyncVersionBusiness` because it is in-system transaction logic. It is not placed in a Service or Model.

Models contain fillable/cast definitions and ownership/query scopes only. New response types are typed Entities rather than unstructured controller arrays.

## Error Codes

Add stable BusinessCode cases:

- `CARD_VERSION_CONFLICT`
- `CARD_DELETED`
- `INVALID_CURSOR`
- `TAG_NOT_FOUND`
- `TAG_NAME_CONFLICT`
- `SESSION_REPLACED` is not exposed separately; replaced sessions use the existing `UNAUTHENTICATED` response to avoid leaking session state.

Validation uses `INVALID_INPUT`; missing ownership continues to use `CARD_NOT_FOUND` so callers cannot enumerate another user's data.

## Consistency And Failure Handling

- Ownership is included in every card, tag, Job, and relation query.
- User sync sequence allocation and resource mutations occur in the same database transaction.
- Existing cards are backfilled with per-user versions during migration, and every new card/generation state visible to clients receives a sequence; `cursor=0` therefore returns pre-migration and newly created cards.
- Idempotency keys remain scoped by user.
- Regeneration never exposes provider temporary URLs or pending payloads.
- Unexpected persistence exceptions escape Worker processing and are not ACKed.
- Stable provider/image failures are persisted and ACKed.
- Image cleanup remains application-owned and path-safe.
- Normal card reads never return deleted content.

## Testing Strategy

Use strict red-green-refactor for every behavior group.

Migration/model tests cover columns, unique keys, indexes, foreign keys, fills, casts, and migration checksum immutability.

Session tests cover:

- a second login invalidates old Access and Refresh Tokens immediately;
- refresh preserves the active session version;
- logout invalidates all Access and Refresh Tokens from the session;
- concurrent login serialization behavior at the Business level.

Library feature/business tests cover ownership, pagination stability, filters, escaped search, editable-field whitelist, validation, favorite, normalized tag reconciliation, tag rename/delete, soft-delete tombstones, image cleanup, deleted-resource exclusion, and idempotent deletion.

Regeneration tests cover preservation of favorite/tags/review state, invisible pending content, atomic successful replacement, old-image cleanup after commit, stable failure preservation, new idempotency keys, and initial retry separation.

Sync tests cover cursor zero, incremental changes, multiple mutations sharing one sequence, permanent tombstones, no split transaction version, ownership isolation, invalid cursor, empty pages, and stable upper-bound behavior.

Final verification includes two migration runs, full PHPUnit, Composer validation, Nginx validation, Redis ping, PHP syntax checks, tracked secret scans, and a controlled HTTP smoke that edits, tags, favorites, syncs, and deletes a temporary card without calling Coze unless regeneration provider behavior changed.

## Delivery Sequence

1. Migration/model/sync version foundation.
2. Single-device session replacement and tests.
3. Card list/detail representation and keyset pagination.
4. Card editing, favorite, and normalized tags.
5. Soft deletion with owned-image cleanup.
6. Atomic regeneration replacement.
7. Incremental sync feed.
8. Runtime verification and Stage 6 handoff.
