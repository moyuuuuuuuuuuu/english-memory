<?php

use app\middleware\Authenticate;
use app\businesses\ProcessAiGenerationJobBusiness;
use app\businesses\CompensateQueuedAiJobsBusiness;
use app\businesses\ImportMemoryCardImageBusiness;
use app\businesses\DeleteStoredMemoryCardImagesBusiness;
use app\processes\AiGenerationWorker;
use app\services\CozeWorkflowService;
use app\services\RedisAccessTokenStore;
use app\services\RedisStreamAiGenerationQueue;
use app\services\LogPasswordResetMail;
use app\services\TokenService;
use app\services\SecureHttpImageDownloader;
use app\services\SystemDnsResolver;
use app\services\GdImageProcessor;
use app\services\LocalImageStorage;
use app\services\contracts\PasswordResetMail;
use app\services\contracts\AiGenerationQueue;
use app\services\contracts\MemoryCardGenerator;
use app\services\contracts\DnsResolver;
use app\services\contracts\RemoteImageDownloader;
use app\services\contracts\ImageProcessor;
use app\services\contracts\ImageStorage;
use GuzzleHttp\Client;
use Psr\Container\ContainerInterface;
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

return [
    ImportMemoryCardImageBusiness::class => static fn (ContainerInterface $container): ImportMemoryCardImageBusiness => new ImportMemoryCardImageBusiness(
        $container->get(RemoteImageDownloader::class),
        $container->get(ImageProcessor::class),
        $container->get(ImageStorage::class),
    ),
    DeleteStoredMemoryCardImagesBusiness::class => static fn (ContainerInterface $container): DeleteStoredMemoryCardImagesBusiness => new DeleteStoredMemoryCardImagesBusiness(
        $container->get(ImageStorage::class),
    ),
    ImageStorage::class => static function (): ImageStorage {
        $image = config('image');
        $root = (string) $image['storage_local_root'];
        if (!str_starts_with($root, DIRECTORY_SEPARATOR)) {
            $root = base_path($root);
        }

        return new LocalImageStorage(
            $root,
            $image['storage_public_url'],
            $image['storage_driver'],
        );
    },
    ImageProcessor::class => static function (): ImageProcessor {
        $image = config('image');

        return new GdImageProcessor(
            runtime_path('image-imports'),
            $image['min_dimension'],
            $image['max_dimension'],
            $image['max_pixels'],
            $image['webp_quality'],
        );
    },
    DnsResolver::class => static fn (): DnsResolver => new SystemDnsResolver(),
    RemoteImageDownloader::class => static function (ContainerInterface $container): RemoteImageDownloader {
        $image = config('image');

        return new SecureHttpImageDownloader(
            new Client(),
            $container->get(DnsResolver::class),
            $image['source_hosts'],
            runtime_path('image-imports'),
            $image['connect_timeout'],
            $image['total_timeout'],
            $image['max_redirects'],
            $image['max_bytes'],
            $image['min_dimension'],
            $image['max_dimension'],
            $image['max_pixels'],
        );
    },
    MemoryCardGenerator::class => static function (): MemoryCardGenerator {
        $coze = config('coze');

        return new CozeWorkflowService(
            new Client(),
            $coze['api_base'],
            $coze['workflow_id'],
            $coze['access_token'],
            $coze['timeout'],
        );
    },
    AiGenerationQueue::class => static fn (): AiGenerationQueue => new RedisStreamAiGenerationQueue(),
    ProcessAiGenerationJobBusiness::class => static fn (ContainerInterface $container): ProcessAiGenerationJobBusiness => new ProcessAiGenerationJobBusiness(
        $container->get(MemoryCardGenerator::class),
        $container->get(ImportMemoryCardImageBusiness::class),
    ),
    AiGenerationWorker::class => static fn (ContainerInterface $container): AiGenerationWorker => new AiGenerationWorker(
        $container->get(AiGenerationQueue::class),
        $container->get(ProcessAiGenerationJobBusiness::class),
        (int) config('ai_generation.claim_idle_ms', 60000),
        (int) config('ai_generation.block_ms', 5000),
    ),
    CompensateQueuedAiJobsBusiness::class => static fn (ContainerInterface $container): CompensateQueuedAiJobsBusiness => new CompensateQueuedAiJobsBusiness(
        $container->get(AiGenerationQueue::class),
        (int) config('ai_generation.compensation_age_seconds', 120),
    ),
    PasswordResetMail::class => static fn (): PasswordResetMail => new LogPasswordResetMail(),
    TokenService::class => static fn (): TokenService => new TokenService(
        new RedisAccessTokenStore(),
        (int) config('auth.access_token_ttl', 900),
    ),
    Authenticate::class => static fn (ContainerInterface $container): Authenticate => new Authenticate(
        $container->get(TokenService::class),
    ),
];
