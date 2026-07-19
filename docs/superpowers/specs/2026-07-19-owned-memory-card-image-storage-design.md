# Application-Owned Memory Card Image Storage Design

## Goal

Import every temporary Coze illustration into application-owned storage before an AI generation Job becomes `completed`. Keep the generated text when image import fails, expose only application-owned image URLs, and provide replaceable storage and cleanup boundaries for later card and account deletion flows.

## Scope

This stage includes secure remote download, image validation, GD processing, local storage, image metadata persistence, Worker integration, cleanup Businesses, configuration, deterministic tests, and one controlled real import smoke test.

This stage does not add card deletion or account deletion HTTP endpoints. Stage 5 and Stage 9 must call the cleanup Businesses defined here instead of relying on database cascade to remove files. Object storage is not implemented; it is enabled later by the storage contract.

## Architecture

The implementation follows the repository layers:

```text
ProcessAiGenerationJobBusiness
  -> ImportMemoryCardImageBusiness
       -> RemoteImageDownloader
       -> ImageProcessor
       -> ImageStorage
       -> MemoryCardImage model
```

Third-party and infrastructure behavior stays under `app/services/`. Application orchestration and transactions stay under `app/businesses/`. Models contain persistence metadata and reusable query scopes only.

### Service contracts

`RemoteImageDownloader` downloads a validated source into a temporary file and returns a typed download entity containing the local temporary path, detected content type, byte count, width, and height.

`ImageProcessor` accepts the validated temporary source and produces three typed artifacts:

- the unchanged original file;
- a WebP whose longest edge is at most 1024 pixels;
- a WebP whose longest edge is at most 512 pixels.

`ImageStorage` atomically stores artifacts under application-controlled keys, returns public URLs, deletes individual keys idempotently, and can delete a card directory idempotently.

### Concrete services

- `SecureHttpImageDownloader` uses Guzzle's cURL handler, manual redirects, streamed temporary files, DNS validation, and `CURLOPT_RESOLVE` address pinning.
- `GdImageProcessor` uses the installed GD and fileinfo extensions and WebP quality 82.
- `LocalImageStorage` writes beneath `public/storage/memory-cards/{user_id}/{card_id}/` and builds URLs from `STORAGE_PUBLIC_URL`.

## Persistence

Add forward migration `0006_create_memory_card_images.sql`. Do not modify migrations `0001` through `0005`.

Create one `memory_card_images` row per card with:

- `id`;
- `user_id`;
- `memory_card_id` with a unique index;
- `storage_driver`;
- `original_key`, `large_key`, and `small_key`;
- `original_sha256`, `large_sha256`, and `small_sha256`;
- `original_mime`;
- `original_width`, `original_height`, and `original_bytes`;
- timestamps.

Foreign keys reference `users` and `memory_cards` with `ON DELETE CASCADE`. Cascade removes metadata only; application deletion flows remain responsible for removing files.

After import succeeds, `memory_cards.image_storage_key` stores the 1024px key and `memory_cards.image_url` stores its application-owned public URL. No Coze temporary URL is written to `memory_cards` or `memory_card_images`.

## Generation Data Flow

1. The Worker claims a durable `queued` Job and calls Coze outside a database transaction.
2. The existing structural card validation runs.
3. A short transaction writes `normalized_text` and `card_payload` to the card and moves the Job to `generating_image`.
4. The Coze image URL remains only in process memory and is passed to `ImportMemoryCardImageBusiness`.
5. The import Business downloads, validates, transforms, and stages all three files.
6. A transaction creates or replaces `memory_card_images`, updates the card with the 1024px application URL/key, and moves the Job to `completed`.
7. After a successful replacement commit, old keys are deleted idempotently.
8. Any expected image failure removes all newly staged files, leaves the text fields intact, and moves the Job to `failed`.

Image failures do not automatically return to `queued`. The existing user retry endpoint creates a new child Job and reruns the full Coze workflow.

## Secure Download Policy

The downloader applies all of the following to the original URL and every redirect target:

- scheme must be exactly `https`;
- host must exactly match one entry in comma-separated `IMAGE_SOURCE_HOSTS`;
- default allowed host is `s.coze.cn`;
- URL user info and non-443 ports are rejected;
- all resolved A and AAAA addresses must be public;
- loopback, private, link-local, multicast, documentation, reserved, and unspecified addresses are rejected;
- one verified public address is pinned with cURL `CURLOPT_RESOLVE` for the request;
- redirects are handled manually and limited to two;
- no application Authorization, Cookie, or bearer header is sent;
- connection timeout is 5 seconds and total timeout is 20 seconds;
- response is streamed into `runtime/image-imports/`;
- declared or observed content above 10 MiB aborts the request.

Temporary download files use random names and are removed in `finally` blocks.

## Image Validation And Processing

The downloader and processor jointly enforce:

- detected MIME and successful GD decode must agree;
- accepted source types are JPEG, PNG, and WebP;
- width and height must each be between 256 and 8192 pixels;
- total pixels must not exceed 40,000,000;
- animation is not preserved; the decoded first frame becomes the processed image;
- aspect ratio is preserved;
- images are never enlarged;
- transparency is preserved for PNG and WebP sources when GD supports it;
- the original bytes remain unchanged;
- the 1024 and 512 variants are encoded as WebP at quality 82;
- SHA-256 is calculated from the exact stored bytes of every artifact.

## Local Storage Semantics

Keys never accept caller-provided path fragments. The storage service derives paths only from integer user/card identifiers, the artifact role, a random import identifier, and a fixed safe extension.

Each file is written to a random temporary filename in the destination directory, closed successfully, and atomically renamed to its final key. Public URLs are built by joining the configured `STORAGE_PUBLIC_URL` with the encoded storage key.

If any artifact fails, every artifact from that import identifier is deleted. Existing image files are retained until the replacement database transaction commits. Missing files during deletion are treated as success.

## Cleanup Lifecycle

`DeleteStoredMemoryCardImagesBusiness` provides:

```php
public function deleteCard(int $userId, int $cardId): void;
public function deleteUser(int $userId): void;
```

Both methods scope metadata by user ownership, delete all recorded keys idempotently, and then delete metadata rows. `deleteUser` processes rows in bounded batches. Stage 5 card deletion must call `deleteCard`; Stage 9 account deletion must call `deleteUser` before deleting the user record.

## Failure Contract

Add stable `BusinessCode` enum values:

- `IMAGE_SOURCE_NOT_ALLOWED`;
- `IMAGE_DOWNLOAD_FAILED`;
- `INVALID_IMAGE`;
- `IMAGE_PROCESSING_FAILED`;
- `IMAGE_STORAGE_FAILED`.

Expected image exceptions carry one stable code and an internal failure category. The Worker persists only the stable code, a safe Chinese message, and one of `image_download`, `image_validation`, `image_processing`, or `image_storage`. It does not persist DNS results, local paths, source URLs, response bodies, or raw exception messages.

The generated text remains in `memory_cards.card_payload` when image import fails. `image_url` and `image_storage_key` remain null or keep the previous application-owned image during a failed replacement.

## Configuration

Add empty-secret-safe settings:

```dotenv
IMAGE_SOURCE_HOSTS=s.coze.cn
IMAGE_CONNECT_TIMEOUT=5
IMAGE_TOTAL_TIMEOUT=20
IMAGE_MAX_REDIRECTS=2
IMAGE_MAX_BYTES=10485760
IMAGE_MIN_DIMENSION=256
IMAGE_MAX_DIMENSION=8192
IMAGE_MAX_PIXELS=40000000
IMAGE_WEBP_QUALITY=82
STORAGE_DRIVER=local
STORAGE_PUBLIC_URL=http://e.test/storage
STORAGE_LOCAL_ROOT=public/storage
```

Production can extend the exact host allowlist without a code release. Wildcard host entries are not supported in this stage.

## Testing

### Downloader tests

Use injected DNS resolution and an HTTP test boundary so tests never depend on public DNS. Cover non-HTTPS URLs, unlisted hosts, user info, custom ports, private or mixed DNS results, redirect limit, redirect outside the allowlist, timeout mapping, declared and streamed size limits, and successful pinned download.

### Image processor tests

Generate small fixtures during tests and cover valid JPEG/PNG/WebP, MIME mismatch, corrupt bytes, dimensions below/above bounds, pixel limit, preserved ratio, no enlargement, WebP output dimensions, transparency, and reproducible checksums.

### Storage tests

Use a unique temporary local root. Cover deterministic path isolation, atomic final files, public URL generation, idempotent deletion, partial-write rollback, and replacement cleanup.

### Business and Worker tests

Cover metadata creation, replacement, transaction failure cleanup, ownership-scoped deletion, retained text on image failure, completed state only after import, stable failure codes, and absence of temporary Coze URLs in persisted or public payloads.

### Final verification

Run migrations twice, full PHPUnit, changed-file syntax, Composer validation, Redis PING, Nginx validation, sensitive-value scan, and diff checks. Restart Webman and verify worker exit counts remain zero. Run one controlled real import, request both application-owned WebP URLs through `e.test`, verify MIME and dimensions, then delete the temporary user and stored files.

## Handoff

After this stage, Stage 5 can consume the stored 1024/512 variants in card library responses and wire `deleteCard`. Object storage can replace `LocalImageStorage` without changing Worker or Business code.
