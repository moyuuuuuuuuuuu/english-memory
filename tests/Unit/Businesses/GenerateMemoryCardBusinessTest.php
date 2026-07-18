<?php

namespace Tests\Unit\Businesses;

use app\businesses\GenerateMemoryCardBusiness;
use app\entities\AiGenerationResultEntity;
use app\services\contracts\MemoryCardGenerator;
use PHPUnit\Framework\TestCase;

final class GenerateMemoryCardBusinessTest extends TestCase
{
    public function testItRejectsBlankTextWithoutCallingService(): void
    {
        $service = new class implements MemoryCardGenerator {
            public function generate(string $text, string $contentType, string $nativeLanguage, string $memoryStyle): AiGenerationResultEntity
            {
                throw new \LogicException('Service must not be called.');
            }
        };

        $result = (new GenerateMemoryCardBusiness($service))->generate('   ', 'word', 'auto');

        self::assertSame(422, $result->httpStatus());
        self::assertSame([
            'success' => false,
            'error' => [
                'code' => 'INVALID_INPUT',
                'message' => '请输入英文单词或句子。',
            ],
        ], $result->toResponseArray());
    }

    public function testItRejectsUnsupportedOptions(): void
    {
        $service = new class implements MemoryCardGenerator {
            public function generate(string $text, string $contentType, string $nativeLanguage, string $memoryStyle): AiGenerationResultEntity
            {
                throw new \LogicException('Service must not be called.');
            }
        };

        $result = (new GenerateMemoryCardBusiness($service))->generate('ambition', 'paragraph', 'forced');

        self::assertSame(422, $result->httpStatus());
        self::assertSame('INVALID_INPUT', $result->toResponseArray()['error']['code']);
    }

    public function testItMapsSuccessfulProviderResult(): void
    {
        $service = new class implements MemoryCardGenerator {
            public function generate(string $text, string $contentType, string $nativeLanguage, string $memoryStyle): AiGenerationResultEntity
            {
                return AiGenerationResultEntity::success(
                    ['word' => $text, 'mnemonic_sentence' => '俺必胜'],
                    'https://s.coze.cn/t/example/',
                    'execute-123',
                );
            }
        };

        $result = (new GenerateMemoryCardBusiness($service))->generate(' ambition ', 'word', 'auto');

        self::assertSame(200, $result->httpStatus());
        self::assertSame([
            'success' => true,
            'data' => [
                'card' => ['word' => 'ambition', 'mnemonic_sentence' => '俺必胜'],
                'image_url' => 'https://s.coze.cn/t/example/',
                'execute_id' => 'execute-123',
            ],
        ], $result->toResponseArray());
    }

    public function testItHidesProviderExceptions(): void
    {
        $service = new class implements MemoryCardGenerator {
            public function generate(string $text, string $contentType, string $nativeLanguage, string $memoryStyle): AiGenerationResultEntity
            {
                throw new \RuntimeException('Bearer secret-token failed.');
            }
        };

        $result = (new GenerateMemoryCardBusiness($service))->generate('ambition', 'word', 'auto');

        self::assertSame(502, $result->httpStatus());
        self::assertSame([
            'success' => false,
            'error' => [
                'code' => 'AI_PROVIDER_ERROR',
                'message' => '记忆卡生成服务暂时不可用，请稍后重试。',
            ],
        ], $result->toResponseArray());
    }
}
