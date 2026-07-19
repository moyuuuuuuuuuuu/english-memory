<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AiGenerationProcessContractTest extends TestCase
{
    public function test_consumer_and_compensation_processes_are_registered(): void
    {
        $root = dirname(__DIR__, 2);
        $process = file_get_contents($root . '/config/process.php');

        self::assertFileExists($root . '/app/processes/AiGenerationConsumerProcess.php');
        self::assertFileExists($root . '/app/processes/AiGenerationCompensationProcess.php');
        self::assertStringContainsString("'ai-generation-consumer'", $process);
        self::assertStringContainsString("'ai-generation-compensation'", $process);
        self::assertStringContainsString('AiGenerationConsumerProcess::class', $process);
        self::assertStringContainsString('AiGenerationCompensationProcess::class', $process);
    }
}
