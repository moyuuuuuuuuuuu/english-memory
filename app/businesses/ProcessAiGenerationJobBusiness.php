<?php

declare(strict_types=1);

namespace app\businesses;

use app\common\enums\AiGenerationStatus;
use app\common\enums\BusinessCode;
use app\common\exceptions\ImageImportException;
use app\entities\AiGenerationResultEntity;
use app\models\AiGenerationJob;
use app\models\MemoryCard;
use app\services\contracts\MemoryCardGenerator;
use support\Db;
use Throwable;

final class ProcessAiGenerationJobBusiness
{
    public function __construct(
        private readonly MemoryCardGenerator $generator,
        private readonly ImportMemoryCardImageBusiness $imageImporter,
    ) {
    }

    public function process(int $jobId): void
    {
        $request = $this->claim($jobId);
        if ($request === null) {
            return;
        }

        try {
            $result = $this->generator->generate(
                (string) ($request['text'] ?? ''),
                (string) ($request['content_type'] ?? 'word'),
                'zh-CN',
                (string) ($request['memory_style'] ?? 'auto'),
            );
        } catch (Throwable) {
            $this->fail(
                $jobId,
                BusinessCode::AiProviderError,
                'provider',
                '记忆卡生成服务暂时不可用，请稍后手动重试。',
            );
            return;
        }

        if (!$result->isSuccess()) {
            $this->fail(
                $jobId,
                BusinessCode::AiProviderError,
                'provider',
                '记忆卡生成失败，请稍后手动重试。',
            );
            return;
        }

        $cardPayload = $this->validatedCard($result);
        if ($cardPayload === null) {
            $this->fail(
                $jobId,
                BusinessCode::InvalidAiResult,
                'validation',
                '生成结果格式不完整，请手动重试。',
            );
            return;
        }

        $context = Db::transaction(static function () use ($jobId, $result, $cardPayload): ?array {
            /** @var AiGenerationJob|null $job */
            $job = AiGenerationJob::query()->where('id', $jobId)->lockForUpdate()->first();
            if ($job === null || $job->status !== AiGenerationStatus::GeneratingText->value) {
                return null;
            }

            $updated = MemoryCard::query()
                ->where('id', (int) $job->memory_card_id)
                ->where('user_id', (int) $job->user_id)
                ->update([
                    'normalized_text' => (string) ($cardPayload['normalized_text'] ?? $cardPayload['word']),
                    'card_payload' => $cardPayload,
                ]);
            if ($updated !== 1) {
                return null;
            }

            $job->forceFill([
                'provider_payload' => ['execute_id' => $result->executeId()],
                'status' => AiGenerationStatus::GeneratingImage->value,
                'error_code' => null,
                'error_message' => null,
                'failure_type' => null,
                'completed_at' => null,
            ])->save();

            return [
                'user_id' => (int) $job->user_id,
                'card_id' => (int) $job->memory_card_id,
            ];
        });

        if ($context === null) {
            return;
        }

        try {
            $this->imageImporter->import(
                $context['user_id'],
                $context['card_id'],
                $jobId,
                $result->imageUrl(),
            );
        } catch (ImageImportException $exception) {
            $this->fail(
                $jobId,
                $exception->businessCode(),
                $exception->failureType(),
                $exception->safeMessage(),
                false,
            );
        }
    }

    private function claim(int $jobId): ?array
    {
        return Db::transaction(static function () use ($jobId): ?array {
            /** @var AiGenerationJob|null $job */
            $job = AiGenerationJob::query()->where('id', $jobId)->lockForUpdate()->first();
            if ($job === null || $job->status !== AiGenerationStatus::Queued->value) {
                return null;
            }

            $job->forceFill([
                'status' => AiGenerationStatus::GeneratingText->value,
                'attempts' => (int) $job->attempts + 1,
                'started_at' => date('Y-m-d H:i:s'),
                'completed_at' => null,
                'error_code' => null,
                'error_message' => null,
                'failure_type' => null,
            ])->save();

            return is_array($job->request_payload) ? $job->request_payload : null;
        });
    }

    private function validatedCard(AiGenerationResultEntity $result): ?array
    {
        $card = $result->card();
        $normalized = trim((string) ($card['normalized_text'] ?? $card['word'] ?? ''));
        $hasMnemonic = trim((string) ($card['story'] ?? '')) !== ''
            || trim((string) ($card['mnemonic_sentence'] ?? '')) !== '';
        $example = $card['example'] ?? null;

        if (($card['success'] ?? false) !== true
            || $normalized === ''
            || !isset($card['meanings'])
            || !is_array($card['meanings'])
            || $card['meanings'] === []
            || !$hasMnemonic
            || !is_array($example)
            || trim((string) ($example['en'] ?? '')) === ''
            || trim((string) ($example['zh'] ?? '')) === ''
            || trim($result->imageUrl()) === '') {
            return null;
        }

        $card['normalized_text'] = $normalized;
        unset($card['reasoning_content']);

        return $card;
    }

    private function fail(
        int $jobId,
        BusinessCode $code,
        string $failureType,
        string $safeMessage,
        bool $clearProviderPayload = true,
    ): void {
        $values = [
            'status' => AiGenerationStatus::Failed->value,
            'error_code' => $code->value,
            'error_message' => $safeMessage,
            'failure_type' => $failureType,
            'completed_at' => date('Y-m-d H:i:s'),
        ];
        if ($clearProviderPayload) {
            $values['provider_payload'] = null;
        }

        AiGenerationJob::query()
            ->where('id', $jobId)
            ->whereIn('status', [
                AiGenerationStatus::GeneratingText->value,
                AiGenerationStatus::GeneratingImage->value,
            ])
            ->update($values);
    }
}
