# Application-Owned Memory Card Image Storage Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Securely import Coze temporary illustrations into application-owned local storage with original, 1024px WebP, and 512px WebP variants before an AI generation Job becomes completed.

**Architecture:** A secure downloader, GD processor, and replaceable storage contract live under Services. Import and cleanup orchestration live under Businesses, image metadata lives in a one-to-one model, and the existing AI Worker persists text before calling the image import Business.

**Tech Stack:** PHP 8.2, Webman 2.2, Workerman 5, MySQL 8, Guzzle 7 cURL handler, GD, fileinfo, PHPUnit 11.

## Global Constraints

- Follow `AGENTS.md`: Controllers call one Business; application logic belongs in Businesses; all third-party and infrastructure behavior belongs in Services.
- Add only forward migration `0006`; never edit migrations `0001` through `0005`.
- Only exact hosts from `IMAGE_SOURCE_HOSTS` are allowed; default `s.coze.cn`; no wildcard entries.
- Accept only HTTPS on port 443 without URL user info; validate every redirect and pin verified DNS with `CURLOPT_RESOLVE`.
- Download limits are connect 5 seconds, total 20 seconds, 2 redirects, and 10 MiB.
- Image limits are JPEG/PNG/WebP, dimensions 256–8192, and at most 40,000,000 pixels.
- Preserve the unchanged original and generate non-enlarged 1024px and 512px WebP variants at quality 82.
- Never persist or return a Coze temporary image URL, raw DNS result, local temporary path, response body, or raw exception message.
- Image failure leaves generated text stored, marks the Job failed, and requires the existing user-initiated full retry.
- Run PHP and Composer only inside the `php82` container.

---

### Task 1: Stable Image Failure Contract And Configuration

**Files:**
- Modify: `app/common/enums/BusinessCode.php`
- Create: `app/common/exceptions/ImageImportException.php`
- Create: `app/entities/DownloadedImageEntity.php`
- Create: `app/entities/ImageArtifactEntity.php`
- Create: `app/entities/ProcessedImageSetEntity.php`
- Create: `app/entities/StoredImageSetEntity.php`
- Create: `config/image.php`
- Modify: `.env.example`
- Create: `tests/Unit/ImageImportContractTest.php`

**Interfaces:**
- `ImageImportException::businessCode(): BusinessCode`
- `ImageImportException::failureType(): string`
- `ImageImportException::safeMessage(): string`
- Entities expose typed getters only and never serialize internal paths into API payloads.

- [ ] **Step 1: Write the failing contract test**

```php
self::assertSame([
    'IMAGE_SOURCE_NOT_ALLOWED',
    'IMAGE_DOWNLOAD_FAILED',
    'INVALID_IMAGE',
    'IMAGE_PROCESSING_FAILED',
    'IMAGE_STORAGE_FAILED',
], array_map(
    static fn (BusinessCode $code): string => $code->value,
    [
        BusinessCode::ImageSourceNotAllowed,
        BusinessCode::ImageDownloadFailed,
        BusinessCode::InvalidImage,
        BusinessCode::ImageProcessingFailed,
        BusinessCode::ImageStorageFailed,
    ],
));

$exception = new ImageImportException(
    BusinessCode::InvalidImage,
    'image_validation',
    '图片内容无效。',
);
self::assertSame('INVALID_IMAGE', $exception->businessCode()->value);
```

- [ ] **Step 2: Run the focused test and confirm RED**

Run:

```bash
docker exec -w /www/english-memory/.worktrees/stage-4-image-storage php82 \
  ./vendor/bin/phpunit --no-configuration --bootstrap tests/bootstrap.php tests/Unit/ImageImportContractTest.php
```

Expected: failure because the new enum cases, exception, entities, and configuration do not exist.

- [ ] **Step 3: Implement the stable failure and entity contracts**

Add enum cases exactly as asserted. `ImageImportException` must extend `RuntimeException` but pass only the safe Chinese message to its parent. Store `BusinessCode` and failure type as readonly properties.

`DownloadedImageEntity` contains temporary path, MIME, byte count, width, and height. `ImageArtifactEntity` contains role, path, extension, MIME, width, height, byte count, and SHA-256. `ProcessedImageSetEntity` contains `original()`, `large()`, and `small()`. `StoredImageSetEntity` contains driver, all keys and checksums, original metadata, and `largeUrl()`/`smallUrl()`.

- [ ] **Step 4: Add exact configuration defaults**

```php
return [
    'source_hosts' => array_values(array_filter(array_map('trim', explode(',', getenv('IMAGE_SOURCE_HOSTS') ?: 's.coze.cn')))),
    'connect_timeout' => (int) (getenv('IMAGE_CONNECT_TIMEOUT') ?: 5),
    'total_timeout' => (int) (getenv('IMAGE_TOTAL_TIMEOUT') ?: 20),
    'max_redirects' => (int) (getenv('IMAGE_MAX_REDIRECTS') ?: 2),
    'max_bytes' => (int) (getenv('IMAGE_MAX_BYTES') ?: 10485760),
    'min_dimension' => (int) (getenv('IMAGE_MIN_DIMENSION') ?: 256),
    'max_dimension' => (int) (getenv('IMAGE_MAX_DIMENSION') ?: 8192),
    'max_pixels' => (int) (getenv('IMAGE_MAX_PIXELS') ?: 40000000),
    'webp_quality' => (int) (getenv('IMAGE_WEBP_QUALITY') ?: 82),
    'storage_driver' => getenv('STORAGE_DRIVER') ?: 'local',
    'storage_public_url' => getenv('STORAGE_PUBLIC_URL') ?: 'http://e.test/storage',
    'storage_local_root' => getenv('STORAGE_LOCAL_ROOT') ?: 'public/storage',
];
```

Mirror every key in `.env.example` without credentials.

- [ ] **Step 5: Run focused/full tests and commit**

```bash
docker exec -w /www/english-memory/.worktrees/stage-4-image-storage php82 ./vendor/bin/phpunit
git diff --check
git add app/common app/entities config/image.php .env.example tests/Unit/ImageImportContractTest.php
git commit -m "feat: define image import contracts"
```

---

### Task 2: Image Metadata Migration And Model

**Files:**
- Create: `database/migrations/0006_create_memory_card_images.sql`
- Create: `app/models/MemoryCardImage.php`
- Create: `tests/Unit/MemoryCardImageMigrationContractTest.php`
- Modify: `tests/Unit/DatabaseMigrationContractTest.php`

**Interfaces:**
- `MemoryCardImage::forOwnedCard(int $userId, int $cardId): ?self`
- Fillable attributes exactly match the migration metadata fields.

- [ ] **Step 1: Write the failing migration/model test**

```php
$sql = file_get_contents($root . '/database/migrations/0006_create_memory_card_images.sql');
self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `memory_card_images`', $sql);
self::assertMatchesRegularExpression('/UNIQUE KEY[^\n]+`memory_card_id`/', $sql);
self::assertStringContainsString('ON DELETE CASCADE', $sql);

$model = new MemoryCardImage();
self::assertContains('large_sha256', $model->getFillable());
self::assertTrue(method_exists($model, 'forOwnedCard'));
```

- [ ] **Step 2: Run the focused test and confirm RED**

Expected: migration and model missing.

- [ ] **Step 3: Add migration `0006`**

Create columns from the approved specification using unsigned BIGINT identifiers, `CHAR(64)` checksums, `VARCHAR(512)` keys, `VARCHAR(128)` driver/MIME values, unsigned dimension/byte fields, one unique key on `memory_card_id`, a user/card lookup index, and user/card cascading foreign keys.

- [ ] **Step 4: Implement the model and ownership query**

```php
public static function forOwnedCard(int $userId, int $cardId): ?self
{
    /** @var self|null $image */
    $image = self::query()
        ->where('user_id', $userId)
        ->where('memory_card_id', $cardId)
        ->first();
    return $image;
}
```

- [ ] **Step 5: Apply migration twice, test, and commit**

```bash
docker exec -w /www/english-memory/.worktrees/stage-4-image-storage php82 php scripts/migrate.php
docker exec -w /www/english-memory/.worktrees/stage-4-image-storage php82 php scripts/migrate.php
docker exec -w /www/english-memory/.worktrees/stage-4-image-storage php82 ./vendor/bin/phpunit
git add database/migrations app/models tests/Unit
git commit -m "feat: persist memory card image metadata"
```

Expected: first migration run applies `0006`; second skips it.

---

### Task 3: Secure Remote Image Downloader

**Files:**
- Create: `app/services/contracts/DnsResolver.php`
- Create: `app/services/contracts/RemoteImageDownloader.php`
- Create: `app/services/SystemDnsResolver.php`
- Create: `app/services/SecureHttpImageDownloader.php`
- Create: `tests/Unit/SecureHttpImageDownloaderTest.php`
- Modify: `config/dependence.php`

**Interfaces:**
- `DnsResolver::resolve(string $host): array` returns unique IP address strings.
- `RemoteImageDownloader::download(string $url): DownloadedImageEntity`.
- Caller owns and removes the returned temporary file.

- [ ] **Step 1: Write failing URL/DNS/download tests**

Use a fake `DnsResolver` and Guzzle `MockHandler`/history middleware. Cover:

```php
#[DataProvider('blockedUrlProvider')]
public function test_it_rejects_unsafe_sources(string $url): void
{
    $this->expectException(ImageImportException::class);
    $this->downloader(['93.184.216.34'])->download($url);
}

public static function blockedUrlProvider(): array
{
    return [
        ['http://s.coze.cn/a.png'],
        ['https://user@s.coze.cn/a.png'],
        ['https://s.coze.cn:444/a.png'],
        ['https://evil.example/a.png'],
    ];
}
```

Also assert rejection for empty DNS, any private/mixed DNS result, third redirect, redirect to another host, `Content-Length` above the limit, observed bytes above the limit, non-2xx response, and transport exception. Assert a successful request uses `CURLOPT_RESOLVE`, writes a temporary file, and returns accurate metadata.

- [ ] **Step 2: Run focused tests and confirm RED**

Expected: downloader contracts/classes missing.

- [ ] **Step 3: Implement DNS resolution and public-address validation**

`SystemDnsResolver` uses `dns_get_record` for A and AAAA records. `SecureHttpImageDownloader` rejects an address unless `filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)` succeeds. Reject the entire host if any resolved address is invalid.

- [ ] **Step 4: Implement manually redirected, pinned streaming download**

For every hop:

```php
$options = [
    'allow_redirects' => false,
    'connect_timeout' => $connectTimeout,
    'timeout' => $totalTimeout,
    'sink' => $temporaryPath,
    'headers' => ['Accept' => 'image/jpeg,image/png,image/webp'],
    'curl' => [CURLOPT_RESOLVE => ["{$host}:443:{$verifiedIp}"]],
    'progress' => static function ($downloadTotal, $downloaded) use ($maxBytes): void {
        if ($downloadTotal > $maxBytes || $downloaded > $maxBytes) {
            throw new RuntimeException('download limit exceeded');
        }
    },
];
```

Resolve relative `Location` headers against the current URL, then repeat full URL and DNS validation. Map unsafe source errors to `IMAGE_SOURCE_NOT_ALLOWED` and transfer failures to `IMAGE_DOWNLOAD_FAILED`. Always delete partial files.

- [ ] **Step 5: Bind services, run tests, and commit**

Bind `DnsResolver` to `SystemDnsResolver` and `RemoteImageDownloader` to `SecureHttpImageDownloader` using `config('image')` values and a Guzzle cURL handler.

```bash
docker exec -w /www/english-memory/.worktrees/stage-4-image-storage php82 ./vendor/bin/phpunit --filter SecureHttpImageDownloaderTest
docker exec -w /www/english-memory/.worktrees/stage-4-image-storage php82 ./vendor/bin/phpunit
git add app/services config/dependence.php tests/Unit/SecureHttpImageDownloaderTest.php
git commit -m "feat: securely download generated images"
```

---

### Task 4: GD Image Processing

**Files:**
- Create: `app/services/contracts/ImageProcessor.php`
- Create: `app/services/GdImageProcessor.php`
- Create: `tests/Unit/GdImageProcessorTest.php`
- Modify: `config/dependence.php`

**Interfaces:**
- `ImageProcessor::process(DownloadedImageEntity $source): ProcessedImageSetEntity`.
- Caller removes all returned artifact temporary paths after storage or failure.

- [ ] **Step 1: Write failing processor tests with generated fixtures**

Generate JPEG, PNG with transparency, and WebP fixtures using GD. Assert:

```php
$processed = $processor->process($downloaded);
self::assertSame([$sourceWidth, $sourceHeight], [$processed->original()->width(), $processed->original()->height()]);
self::assertLessThanOrEqual(1024, max($processed->large()->width(), $processed->large()->height()));
self::assertLessThanOrEqual(512, max($processed->small()->width(), $processed->small()->height()));
self::assertSame(hash_file('sha256', $processed->large()->path()), $processed->large()->sha256());
```

Cover no enlargement, aspect ratio, transparent output, corrupt data, MIME mismatch, unsupported GIF, dimensions below/above bounds, pixel count above 40 million through injected metadata, and cleanup of partial artifacts after encode failure.

- [ ] **Step 2: Run focused tests and confirm RED**

Expected: `ImageProcessor` and `GdImageProcessor` missing.

- [ ] **Step 3: Implement safe decode and dimension validation**

Use fileinfo plus `getimagesize`, require MIME agreement, then decode with the exact GD decoder for JPEG/PNG/WebP. Validate dimensions and pixel count before allocating derivative canvases. Map validation errors to `INVALID_IMAGE`.

- [ ] **Step 4: Implement proportional WebP variants**

```php
$scale = min(1, $maxEdge / max($sourceWidth, $sourceHeight));
$targetWidth = max(1, (int) round($sourceWidth * $scale));
$targetHeight = max(1, (int) round($sourceHeight * $scale));
```

Use true-color canvases, alpha save/blending for transparent sources, high-quality resampling, and `imagewebp(..., $quality)`. Copy the original bytes unchanged to a managed temporary artifact. Map decode/encode errors to stable validation/processing exceptions and destroy every GD resource in `finally`.

- [ ] **Step 5: Bind, test, and commit**

```bash
docker exec -w /www/english-memory/.worktrees/stage-4-image-storage php82 ./vendor/bin/phpunit --filter GdImageProcessorTest
docker exec -w /www/english-memory/.worktrees/stage-4-image-storage php82 ./vendor/bin/phpunit
git add app/services config/dependence.php tests/Unit/GdImageProcessorTest.php
git commit -m "feat: generate memory card image variants"
```

---

### Task 5: Replaceable Local Image Storage

**Files:**
- Create: `app/services/contracts/ImageStorage.php`
- Create: `app/services/LocalImageStorage.php`
- Create: `tests/Unit/LocalImageStorageTest.php`
- Modify: `config/dependence.php`

**Interfaces:**
- `ImageStorage::store(int $userId, int $cardId, ProcessedImageSetEntity $images): StoredImageSetEntity`.
- `ImageStorage::deleteKeys(array $keys): void` is idempotent.

- [ ] **Step 1: Write failing storage tests with a unique temporary root**

Assert all final paths remain under the configured root, keys follow `memory-cards/{user}/{card}/{import-id}-{role}.{ext}`, public URLs use the configured base, bytes/checksums match, missing-key deletion succeeds, `../` is impossible, and simulated second/third write failures remove every new file.

- [ ] **Step 2: Run focused tests and confirm RED**

Expected: storage contract and implementation missing.

- [ ] **Step 3: Implement atomic local writes**

Create directories with mode 0755, copy each artifact into a random destination temporary name opened with exclusive creation, flush and close it, then rename atomically. Derive all keys internally from integers, a cryptographically random import ID, fixed roles, and fixed extensions. Verify the real destination parent remains within the configured root.

- [ ] **Step 4: Implement idempotent key deletion and URL building**

Reject absolute keys, null bytes, and any `..` segment. Missing files are successful deletions. Remove empty card/user directories after deleting known keys, but never recursively delete a path outside the configured `memory-cards` root.

- [ ] **Step 5: Bind, test, and commit**

```bash
docker exec -w /www/english-memory/.worktrees/stage-4-image-storage php82 ./vendor/bin/phpunit --filter LocalImageStorageTest
docker exec -w /www/english-memory/.worktrees/stage-4-image-storage php82 ./vendor/bin/phpunit
git add app/services config/dependence.php tests/Unit/LocalImageStorageTest.php
git commit -m "feat: store generated images locally"
```

---

### Task 6: Import And Cleanup Businesses

**Files:**
- Create: `app/businesses/ImportMemoryCardImageBusiness.php`
- Create: `app/businesses/DeleteStoredMemoryCardImagesBusiness.php`
- Create: `tests/Unit/Businesses/ImportMemoryCardImageBusinessTest.php`
- Create: `tests/Unit/Businesses/DeleteStoredMemoryCardImagesBusinessTest.php`
- Modify: `config/dependence.php`

**Interfaces:**
- `ImportMemoryCardImageBusiness::import(int $userId, int $cardId, int $jobId, string $sourceUrl): void`.
- `DeleteStoredMemoryCardImagesBusiness::deleteCard(int $userId, int $cardId): void`.
- `DeleteStoredMemoryCardImagesBusiness::deleteUser(int $userId): void`.

- [ ] **Step 1: Write failing import orchestration tests**

Use fake downloader, processor, and storage services. Cover successful metadata/card/Job transaction, replacement old-key deletion after commit, failed database write cleanup of new keys, wrong user/card/job rejection, and no temporary source URL in either table.

```php
$business->import($userId, $cardId, $jobId, 'https://s.coze.cn/t/example');
self::assertSame('completed', Db::table('ai_generation_jobs')->where('id', $jobId)->value('status'));
self::assertStringStartsWith('http://e.test/storage/', Db::table('memory_cards')->where('id', $cardId)->value('image_url'));
self::assertStringNotContainsString('s.coze.cn', json_encode(Db::table('memory_card_images')->where('memory_card_id', $cardId)->first()));
```

- [ ] **Step 2: Write failing cleanup Business tests**

Cover ownership scope, all three keys, missing files, card metadata deletion, bounded user batches, multiple cards, and storage failure leaving metadata available for a later retry.

- [ ] **Step 3: Run focused tests and confirm RED**

Expected: Businesses missing.

- [ ] **Step 4: Implement import transaction and file rollback**

Download and process outside the database transaction, store all new files, then lock the owned card and generating-image Job. Upsert `memory_card_images`, set the card's large URL/key, and set Job `completed` with `completed_at`. Delete old keys only after commit. In `finally`, remove download/artifact temporary paths. On any failure before commit, delete new stored keys and rethrow the stable exception.

- [ ] **Step 5: Implement ownership-scoped cleanup**

Delete storage keys before metadata. If storage deletion throws, keep metadata unchanged. `deleteUser` reads at most 100 image rows ordered by ID per loop and delegates to the same owned-card deletion behavior.

- [ ] **Step 6: Bind, test, and commit**

```bash
docker exec -w /www/english-memory/.worktrees/stage-4-image-storage php82 ./vendor/bin/phpunit --filter 'ImportMemoryCardImageBusinessTest|DeleteStoredMemoryCardImagesBusinessTest'
docker exec -w /www/english-memory/.worktrees/stage-4-image-storage php82 ./vendor/bin/phpunit
git add app/businesses config/dependence.php tests/Unit/Businesses
git commit -m "feat: import and clean up card images"
```

---

### Task 7: Worker Image Import Integration

**Files:**
- Modify: `app/businesses/ProcessAiGenerationJobBusiness.php`
- Modify: `tests/Unit/Businesses/ProcessAiGenerationJobBusinessTest.php`
- Modify: `tests/Unit/AiGenerationWorkerTest.php`
- Modify: `config/dependence.php`

**Interfaces:**
- `ProcessAiGenerationJobBusiness` receives both `MemoryCardGenerator` and `ImportMemoryCardImageBusiness`.
- `ImportMemoryCardImageBusiness` owns the final `completed` transition.

- [ ] **Step 1: Extend failing processor tests**

Assert successful text is persisted before the fake importer runs, success reaches completed through the importer, image exception leaves text/card payload persisted, image fields remain null or retain a previous application URL, Job uses the exception's stable code/failure type/safe message, and no source URL or raw message is persisted.

- [ ] **Step 2: Extend Worker ACK tests**

Assert stable image failure is ACKed after the Job becomes failed, while an unexpected import/persistence exception escapes and is not ACKed.

- [ ] **Step 3: Run focused tests and confirm RED**

Expected: old processor completes directly and does not call importer.

- [ ] **Step 4: Refactor the state machine**

After card validation, use a short transaction to persist text and move to `generating_image`. Call importer outside the transaction. Catch only `ImageImportException` and persist:

```php
[
    'status' => AiGenerationStatus::Failed->value,
    'error_code' => $exception->businessCode()->value,
    'error_message' => $exception->safeMessage(),
    'failure_type' => $exception->failureType(),
    'completed_at' => date('Y-m-d H:i:s'),
]
```

Do not persist the source URL in `provider_payload`. Preserve the existing behavior for Coze and structural validation failures.

- [ ] **Step 5: Update dependency construction, run tests, and commit**

```bash
docker exec -w /www/english-memory/.worktrees/stage-4-image-storage php82 ./vendor/bin/phpunit --filter 'ProcessAiGenerationJobBusinessTest|AiGenerationWorkerTest'
docker exec -w /www/english-memory/.worktrees/stage-4-image-storage php82 ./vendor/bin/phpunit
git add app/businesses/ProcessAiGenerationJobBusiness.php config/dependence.php tests/Unit
git commit -m "feat: complete jobs after owned image import"
```

---

### Task 8: Runtime Verification And Stage Handoff

**Files:**
- Modify: `PROJECT_PLAN.md`
- Modify: `docs/superpowers/plans/2026-07-19-owned-memory-card-image-storage.md`

**Interfaces:**
- Produces the verified Stage 4 handoff and Stage 5 entry point.

- [ ] **Step 1: Run deterministic final verification**

```bash
docker exec -w /www/english-memory/.worktrees/stage-4-image-storage php82 php scripts/migrate.php
docker exec -w /www/english-memory/.worktrees/stage-4-image-storage php82 php scripts/migrate.php
docker exec -w /www/english-memory/.worktrees/stage-4-image-storage php82 composer validate --no-check-publish
docker exec -w /www/english-memory/.worktrees/stage-4-image-storage php82 ./vendor/bin/phpunit
docker exec redis redis-cli PING
docker exec nginx nginx -t
git diff --check
```

Also syntax-check every changed PHP file and scan tracked files for PATs, bearer values, temporary Coze URLs in persistence code, and local temporary paths in API entities.

- [ ] **Step 2: Merge locally and restart Webman**

Use `superpowers:finishing-a-development-branch`, merge only after all tests pass, restart from `/www/english-memory`, and verify consumer/compensation/webman/monitor exit counts remain zero.

- [ ] **Step 3: Run one controlled real image import smoke**

Create a temporary authenticated user, enqueue one `ambition` card, poll until terminal, and require `completed`. Verify:

```bash
curl -I http://e.test/storage/<large-key>
curl -I http://e.test/storage/<small-key>
```

Both must return 200 and `Content-Type: image/webp`. Read dimensions with GD and require longest edges at most 1024 and 512. Verify the original checksum matches stored bytes, no database URL contains `s.coze.cn`, then call cleanup and delete the temporary user.

- [ ] **Step 4: Update handoff documents with exact evidence**

Mark Stage 4 complete only with the actual migration number, test/assertion baseline, worker status, image MIME/dimensions/checksums, cleanup result, current branch state, and the next Stage 5 task. Do not mark Stage 5 deletion endpoint wiring complete.

- [ ] **Step 5: Commit documentation and run a fresh final verification**

```bash
git add PROJECT_PLAN.md docs/superpowers/plans/2026-07-19-owned-memory-card-image-storage.md
git commit -m "docs: complete owned image storage handoff"
docker exec -w /www/english-memory php82 ./vendor/bin/phpunit
git status --short --branch
```
