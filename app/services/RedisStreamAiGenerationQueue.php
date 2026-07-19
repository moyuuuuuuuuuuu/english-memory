<?php

declare(strict_types=1);

namespace app\services;

use app\entities\QueueMessageEntity;
use app\services\contracts\AiGenerationQueue;
use RuntimeException;
use support\Redis;
use Throwable;

final class RedisStreamAiGenerationQueue implements AiGenerationQueue
{
    private bool $groupEnsured = false;

    public function __construct(
        private readonly string $stream = '',
        private readonly string $group = '',
    ) {
    }

    public function publish(int $jobId): void
    {
        $this->ensureGroup();
        $messageId = Redis::xadd($this->stream(), '*', ['job_id' => (string) $jobId]);

        if ($messageId === false) {
            throw new RuntimeException('Failed to publish AI generation job.');
        }
    }

    public function consume(string $consumer, int $blockMs = 5000): ?QueueMessageEntity
    {
        $this->ensureGroup();
        $messages = Redis::xreadgroup(
            $this->group(),
            $consumer,
            [$this->stream() => '>'],
            1,
            $blockMs,
        );

        return $this->firstMessage($messages);
    }

    public function ack(QueueMessageEntity $message): void
    {
        Redis::xack($this->stream(), $this->group(), [$message->messageId()]);
    }

    public function claimStale(string $consumer, int $idleMs): ?QueueMessageEntity
    {
        $this->ensureGroup();
        $result = Redis::xautoclaim(
            $this->stream(),
            $this->group(),
            $consumer,
            $idleMs,
            '0-0',
            1,
        );

        return $this->firstMessage(is_array($result) ? ($result[1] ?? []) : []);
    }

    private function ensureGroup(): void
    {
        if ($this->groupEnsured) {
            return;
        }

        try {
            $created = Redis::xgroup('CREATE', $this->stream(), $this->group(), '0', true);
            if ($created === false && !str_contains((string) Redis::getLastError(), 'BUSYGROUP')) {
                throw new RuntimeException('Failed to create AI generation consumer group.');
            }
            Redis::clearLastError();
        } catch (Throwable $throwable) {
            if (!str_contains($throwable->getMessage(), 'BUSYGROUP')) {
                throw $throwable;
            }
        }

        $this->groupEnsured = true;
    }

    private function firstMessage(mixed $messages): ?QueueMessageEntity
    {
        if (!is_array($messages) || $messages === []) {
            return null;
        }

        $streamMessages = array_key_exists($this->stream(), $messages)
            ? $messages[$this->stream()]
            : $messages;
        if (!is_array($streamMessages) || $streamMessages === []) {
            return null;
        }

        $messageId = (string) array_key_first($streamMessages);
        $fields = $streamMessages[$messageId] ?? null;
        if ($messageId === '' || !is_array($fields) || !isset($fields['job_id'])) {
            throw new RuntimeException('Invalid AI generation queue message.');
        }

        return new QueueMessageEntity($messageId, (int) $fields['job_id']);
    }

    private function stream(): string
    {
        return $this->stream !== ''
            ? $this->stream
            : (string) config('ai_generation.stream', 'english-memory:ai-generation');
    }

    private function group(): string
    {
        return $this->group !== ''
            ? $this->group
            : (string) config('ai_generation.group', 'english-memory-workers');
    }
}
