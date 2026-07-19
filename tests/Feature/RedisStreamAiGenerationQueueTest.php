<?php

declare(strict_types=1);

namespace Tests\Feature;

use app\services\RedisStreamAiGenerationQueue;
use PHPUnit\Framework\TestCase;
use support\Redis;

final class RedisStreamAiGenerationQueueTest extends TestCase
{
    private string $stream;
    private string $group;

    protected function setUp(): void
    {
        parent::setUp();
        $suffix = bin2hex(random_bytes(8));
        $this->stream = "test:ai-generation:{$suffix}";
        $this->group = "test-group-{$suffix}";
    }

    protected function tearDown(): void
    {
        Redis::del($this->stream);
        parent::tearDown();
    }

    public function test_publish_consume_and_ack(): void
    {
        $publisher = new RedisStreamAiGenerationQueue($this->stream, $this->group);
        $queue = new RedisStreamAiGenerationQueue($this->stream, $this->group);

        $publisher->publish(42);
        $message = $queue->consume('consumer-a', 50);

        self::assertNotNull($message);
        self::assertSame(42, $message->jobId());
        self::assertNotSame('', $message->messageId());

        $queue->ack($message);
        $pending = Redis::xpending($this->stream, $this->group);
        self::assertSame(0, (int) ($pending[0] ?? 0));
    }

    public function test_stale_pending_message_can_be_claimed(): void
    {
        $queue = new RedisStreamAiGenerationQueue($this->stream, $this->group);
        $queue->publish(73);

        self::assertNotNull($queue->consume('consumer-that-died', 50));

        $claimed = $queue->claimStale('replacement-consumer', 0);

        self::assertNotNull($claimed);
        self::assertSame(73, $claimed->jobId());
        $queue->ack($claimed);
    }
}
