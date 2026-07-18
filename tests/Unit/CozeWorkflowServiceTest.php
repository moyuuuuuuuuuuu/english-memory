<?php

namespace Tests\Unit;

use app\services\CozeWorkflowService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class CozeWorkflowServiceTest extends TestCase
{
    public function testItCallsPublishedWorkflowAndParsesMemoryCard(): void
    {
        $card = [
            'success' => true,
            'word' => 'ambition',
            'mnemonic_sentence' => '俺必胜',
        ];
        $workflowOutput = [
            'success' => true,
            'card_json' => json_encode($card, JSON_UNESCAPED_UNICODE),
            'image_url' => 'https://s.coze.cn/t/example/',
            'error' => '',
        ];
        $history = [];
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => json_encode($workflowOutput, JSON_UNESCAPED_UNICODE),
                'execute_id' => 'execute-123',
                'debug_url' => 'https://www.coze.cn/debug/example',
            ], JSON_UNESCAPED_UNICODE)),
        ]);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));
        $http = new Client(['handler' => $stack]);

        $client = new CozeWorkflowService(
            $http,
            'https://api.coze.cn',
            '7663771858193448987',
            'secret-token',
            180,
        );

        $result = $client->generate(' ambition ', 'word', 'zh-CN', 'auto');

        self::assertTrue($result->isSuccess());
        self::assertSame('ambition', $result->card()['word']);
        self::assertSame('俺必胜', $result->card()['mnemonic_sentence']);
        self::assertSame('https://s.coze.cn/t/example/', $result->imageUrl());
        self::assertSame('execute-123', $result->executeId());

        $request = $history[0]['request'];
        self::assertSame('/v1/workflow/run', $request->getUri()->getPath());
        self::assertSame('Bearer secret-token', $request->getHeaderLine('Authorization'));
        $payload = json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('7663771858193448987', $payload['workflow_id']);
        self::assertSame('ambition', $payload['parameters']['text']);
        self::assertSame('auto', $payload['parameters']['memory_style']);
    }

    public function testItRejectsBlankTextBeforeCallingCoze(): void
    {
        $mock = new MockHandler([]);
        $client = new CozeWorkflowService(
            new Client(['handler' => HandlerStack::create($mock)]),
            'https://api.coze.cn',
            'workflow-id',
            'secret-token',
            180,
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('English text is required.');

        $client->generate('   ', 'word', 'zh-CN', 'auto');
    }
}
