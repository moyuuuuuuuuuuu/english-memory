<?php

namespace Tests\Feature;

use app\businesses\GenerateMemoryCardBusiness;
use app\controllers\GenerateMemoryCardController;
use app\entities\AiGenerationResultEntity;
use app\services\contracts\MemoryCardGenerator;
use PHPUnit\Framework\TestCase;
use support\Request;

final class GenerateMemoryCardControllerTest extends TestCase
{
    public function testItRejectsBlankEnglishText(): void
    {
        $generator = new class implements MemoryCardGenerator {
            public function generate(string $text, string $contentType, string $nativeLanguage, string $memoryStyle): AiGenerationResultEntity
            {
                throw new \LogicException('Generator must not be called.');
            }
        };

        $response = (new GenerateMemoryCardController(new GenerateMemoryCardBusiness($generator)))($this->jsonRequest([
            'text' => '   ',
        ]));

        self::assertSame(422, $response->getStatusCode());
        self::assertSame([
            'success' => false,
            'error' => [
                'code' => 'INVALID_INPUT',
                'message' => '请输入英文单词或句子。',
            ],
        ], json_decode($response->rawBody(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testItReturnsGeneratedMemoryCard(): void
    {
        $generator = new class implements MemoryCardGenerator {
            public function generate(string $text, string $contentType, string $nativeLanguage, string $memoryStyle): AiGenerationResultEntity
            {
                return AiGenerationResultEntity::success(
                    ['word' => $text, 'mnemonic_sentence' => '俺必胜'],
                    'https://s.coze.cn/t/example/',
                    'execute-123',
                );
            }
        };

        $response = (new GenerateMemoryCardController(new GenerateMemoryCardBusiness($generator)))($this->jsonRequest([
            'text' => 'ambition',
            'content_type' => 'word',
            'memory_style' => 'auto',
        ]));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([
            'success' => true,
            'data' => [
                'card' => ['word' => 'ambition', 'mnemonic_sentence' => '俺必胜'],
                'image_url' => 'https://s.coze.cn/t/example/',
                'execute_id' => 'execute-123',
            ],
        ], json_decode($response->rawBody(), true, 512, JSON_THROW_ON_ERROR));
    }

    private function jsonRequest(array $payload): Request
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        return new Request(
            "POST /api/memory-cards/generate HTTP/1.1\r\n"
            . "Host: localhost\r\n"
            . "Content-Type: application/json\r\n"
            . 'Content-Length: ' . strlen($body) . "\r\n\r\n"
            . $body,
        );
    }
}
