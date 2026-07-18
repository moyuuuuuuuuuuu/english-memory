<?php

namespace app\services;

use app\entities\AiGenerationResultEntity;
use app\services\contracts\MemoryCardGenerator;
use GuzzleHttp\ClientInterface;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

final class CozeWorkflowService implements MemoryCardGenerator
{
    public function __construct(
        private readonly ClientInterface $http,
        private readonly string $apiBase,
        private readonly string $workflowId,
        private readonly string $accessToken,
        private readonly int $timeout = 180,
    ) {
    }

    public function generate(
        string $text,
        string $contentType,
        string $nativeLanguage,
        string $memoryStyle,
    ): AiGenerationResultEntity {
        $text = trim($text);
        if ($text === '') {
            throw new InvalidArgumentException('English text is required.');
        }

        $response = $this->http->request('POST', rtrim($this->apiBase, '/') . '/v1/workflow/run', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Accept' => 'application/json',
            ],
            'json' => [
                'workflow_id' => $this->workflowId,
                'parameters' => [
                    'text' => $text,
                    'content_type' => $contentType,
                    'native_language' => $nativeLanguage,
                    'memory_style' => $memoryStyle,
                ],
            ],
            'timeout' => $this->timeout,
        ]);

        try {
            $envelope = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            $workflowOutput = $this->decodeObject($envelope['data'] ?? null, 'Coze response data');
            $card = $this->decodeObject($workflowOutput['card_json'] ?? null, 'memory card');
        } catch (JsonException $exception) {
            throw new RuntimeException('Coze returned invalid JSON.', 0, $exception);
        }

        $executeId = (string) ($envelope['execute_id'] ?? '');
        if (!(bool) ($workflowOutput['success'] ?? false)) {
            return AiGenerationResultEntity::failure(
                $card,
                (string) ($workflowOutput['error'] ?? ''),
                $executeId,
            );
        }

        return AiGenerationResultEntity::success(
            $card,
            (string) ($workflowOutput['image_url'] ?? ''),
            $executeId,
        );
    }

    /**
     * @throws JsonException
     */
    private function decodeObject(mixed $value, string $label): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || $value === '') {
            throw new RuntimeException("Missing {$label}.");
        }

        $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new RuntimeException("Invalid {$label}.");
        }

        return $decoded;
    }
}
