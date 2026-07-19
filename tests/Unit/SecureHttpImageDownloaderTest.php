<?php

declare(strict_types=1);

namespace Tests\Unit;

use app\common\enums\BusinessCode;
use app\common\exceptions\ImageImportException;
use app\services\SecureHttpImageDownloader;
use app\services\contracts\DnsResolver;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SecureHttpImageDownloaderTest extends TestCase
{
    private string $tempDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDirectory = sys_get_temp_dir() . '/english-memory-download-' . bin2hex(random_bytes(6));
        mkdir($this->tempDirectory, 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tempDirectory . '/*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->tempDirectory)) {
            rmdir($this->tempDirectory);
        }
        parent::tearDown();
    }

    #[DataProvider('blockedUrlProvider')]
    public function test_it_rejects_unsafe_source_urls(string $url): void
    {
        $downloader = $this->downloader([new Response(200)], ['93.184.216.34']);

        try {
            $downloader->download($url);
            self::fail('Expected unsafe URL rejection.');
        } catch (ImageImportException $exception) {
            self::assertSame(BusinessCode::ImageSourceNotAllowed, $exception->businessCode());
        }
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

    public function test_it_rejects_empty_private_or_mixed_dns_results(): void
    {
        foreach ([[], ['127.0.0.1'], ['93.184.216.34', '10.0.0.1']] as $addresses) {
            $downloader = $this->downloader([new Response(200)], $addresses);
            try {
                $downloader->download('https://s.coze.cn/a.png');
                self::fail('Expected DNS rejection.');
            } catch (ImageImportException $exception) {
                self::assertSame(BusinessCode::ImageSourceNotAllowed, $exception->businessCode());
            }
        }
    }

    public function test_it_revalidates_redirects_and_enforces_the_limit(): void
    {
        $outside = $this->downloader([
            new Response(302, ['Location' => 'https://evil.example/a.png']),
        ], ['93.184.216.34']);
        $tooMany = $this->downloader([
            new Response(302, ['Location' => '/b.png']),
            new Response(302, ['Location' => '/c.png']),
            new Response(302, ['Location' => '/d.png']),
        ], ['93.184.216.34'], maxRedirects: 2);

        foreach ([$outside, $tooMany] as $downloader) {
            try {
                $downloader->download('https://s.coze.cn/a.png');
                self::fail('Expected redirect rejection.');
            } catch (ImageImportException $exception) {
                self::assertContains($exception->businessCode(), [
                    BusinessCode::ImageSourceNotAllowed,
                    BusinessCode::ImageDownloadFailed,
                ]);
            }
        }
    }

    public function test_it_rejects_declared_oversize_and_transport_errors(): void
    {
        $oversize = $this->downloader([
            new Response(200, ['Content-Length' => '999999', 'Content-Type' => 'image/png'], $this->png()),
        ], ['93.184.216.34'], maxBytes: 1000);
        $request = new Request('GET', 'https://s.coze.cn/a.png');
        $transport = $this->downloader([
            new ConnectException('connection secret', $request),
        ], ['93.184.216.34']);

        foreach ([$oversize, $transport] as $downloader) {
            try {
                $downloader->download('https://s.coze.cn/a.png');
                self::fail('Expected download failure.');
            } catch (ImageImportException $exception) {
                self::assertSame(BusinessCode::ImageDownloadFailed, $exception->businessCode());
                self::assertStringNotContainsString('secret', $exception->getMessage());
            }
        }
    }

    public function test_successful_download_is_pinned_streamed_and_described(): void
    {
        $history = [];
        $downloader = $this->downloader([
            new Response(200, ['Content-Type' => 'image/png'], $this->png()),
        ], ['93.184.216.34'], history: $history);

        $download = $downloader->download('https://s.coze.cn/card.png');

        self::assertFileExists($download->path());
        self::assertSame('image/png', $download->mime());
        self::assertSame(256, $download->width());
        self::assertSame(256, $download->height());
        self::assertSame(filesize($download->path()), $download->bytes());
        self::assertSame(['s.coze.cn:443:93.184.216.34'], $history[0]['options']['curl'][CURLOPT_RESOLVE]);
        self::assertArrayNotHasKey('Authorization', $history[0]['request']->getHeaders());
        unlink($download->path());
    }

    private function downloader(
        array $responses,
        array $addresses,
        int $maxRedirects = 2,
        int $maxBytes = 10485760,
        ?array &$history = null,
    ): SecureHttpImageDownloader {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        if ($history !== null) {
            $stack->push(Middleware::history($history));
        }
        $dns = new class($addresses) implements DnsResolver {
            public function __construct(private readonly array $addresses) {}
            public function resolve(string $host): array { return $this->addresses; }
        };

        return new SecureHttpImageDownloader(
            new Client(['handler' => $stack]),
            $dns,
            ['s.coze.cn'],
            $this->tempDirectory,
            5,
            20,
            $maxRedirects,
            $maxBytes,
            256,
            8192,
            40000000,
        );
    }

    private function png(): string
    {
        $image = imagecreatetruecolor(256, 256);
        ob_start();
        imagepng($image);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);
        return $bytes;
    }
}
