<?php

declare(strict_types=1);

namespace app\services;

use app\common\enums\BusinessCode;
use app\common\exceptions\ImageImportException;
use app\entities\DownloadedImageEntity;
use app\services\contracts\DnsResolver;
use app\services\contracts\RemoteImageDownloader;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

final class SecureHttpImageDownloader implements RemoteImageDownloader
{
    public function __construct(
        private readonly ClientInterface $http,
        private readonly DnsResolver $dns,
        private readonly array $allowedHosts,
        private readonly string $temporaryDirectory,
        private readonly int $connectTimeout,
        private readonly int $totalTimeout,
        private readonly int $maxRedirects,
        private readonly int $maxBytes,
        private readonly int $minDimension,
        private readonly int $maxDimension,
        private readonly int $maxPixels,
        private readonly array $redirectHostSuffixes = [],
    ) {
    }

    public function download(string $url): DownloadedImageEntity
    {
        $this->ensureTemporaryDirectory();
        $temporaryPath = tempnam($this->temporaryDirectory, 'download-');
        if ($temporaryPath === false) {
            throw $this->downloadFailure();
        }

        $deadline = microtime(true) + $this->totalTimeout;
        $currentUrl = $url;
        $redirects = 0;

        try {
            while (true) {
                [$host, $resolveEntry] = $this->validateSource($currentUrl, $redirects > 0);
                $remaining = $deadline - microtime(true);
                if ($remaining <= 0) {
                    throw new RuntimeException('download deadline exceeded');
                }

                $response = $this->request($currentUrl, $host, $resolveEntry, $temporaryPath, $remaining);
                $status = $response->getStatusCode();
                if ($status >= 300 && $status < 400) {
                    if ($redirects >= $this->maxRedirects) {
                        throw new RuntimeException('redirect limit exceeded');
                    }
                    $location = trim($response->getHeaderLine('Location'));
                    if ($location === '') {
                        throw new RuntimeException('redirect location missing');
                    }
                    $currentUrl = (string) UriResolver::resolve(new Uri($currentUrl), new Uri($location));
                    ++$redirects;
                    continue;
                }
                if ($status < 200 || $status >= 300) {
                    throw new RuntimeException('unexpected image response status');
                }
                break;
            }

            $bytes = filesize($temporaryPath);
            if ($bytes === false || $bytes <= 0 || $bytes > $this->maxBytes) {
                throw new RuntimeException('invalid downloaded size');
            }
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
            $dimensions = @getimagesize($temporaryPath);
            if (!is_string($mime) || !is_array($dimensions)) {
                throw new RuntimeException('downloaded bytes are not an image');
            }
            $width = (int) ($dimensions[0] ?? 0);
            $height = (int) ($dimensions[1] ?? 0);
            $detectedMime = (string) ($dimensions['mime'] ?? '');
            if ($mime !== $detectedMime
                || !in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)
                || !$this->dimensionsAllowed($width, $height)) {
                throw new RuntimeException('downloaded image metadata is invalid');
            }

            return new DownloadedImageEntity($temporaryPath, $mime, (int) $bytes, $width, $height);
        } catch (ImageImportException $exception) {
            @unlink($temporaryPath);
            throw $exception;
        } catch (Throwable) {
            @unlink($temporaryPath);
            throw $this->downloadFailure();
        }
    }

    private function request(
        string $url,
        string $host,
        string $resolveEntry,
        string $temporaryPath,
        float $remaining,
    ): ResponseInterface {
        return $this->http->request('GET', $url, [
            'allow_redirects' => false,
            'http_errors' => false,
            'connect_timeout' => min($this->connectTimeout, $remaining),
            'timeout' => $remaining,
            'sink' => $temporaryPath,
            'headers' => ['Accept' => 'image/jpeg,image/png,image/webp'],
            'curl' => [CURLOPT_RESOLVE => ["{$host}:443:{$resolveEntry}"]],
            'on_headers' => function (ResponseInterface $response): void {
                $declared = $response->getHeaderLine('Content-Length');
                if ($declared !== '' && ctype_digit($declared) && (int) $declared > $this->maxBytes) {
                    throw new RuntimeException('declared image size exceeds limit');
                }
            },
            'progress' => function (int|float $downloadTotal, int|float $downloaded): void {
                if ($downloadTotal > $this->maxBytes || $downloaded > $this->maxBytes) {
                    throw new RuntimeException('streamed image size exceeds limit');
                }
            },
        ]);
    }

    /** @return array{string, string} */
    private function validateSource(string $url, bool $isRedirect): array
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (!is_array($parts)
            || $scheme !== 'https'
            || $host === ''
            || !$this->hostAllowed($host, $isRedirect)
            || isset($parts['user'])
            || isset($parts['pass'])
            || (isset($parts['port']) && (int) $parts['port'] !== 443)) {
            throw $this->sourceNotAllowed();
        }

        $addresses = $this->dns->resolve($host);
        if ($addresses === []) {
            throw $this->sourceNotAllowed();
        }
        foreach ($addresses as $address) {
            if (filter_var(
                $address,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
            ) === false) {
                throw $this->sourceNotAllowed();
            }
        }

        $selected = (string) $addresses[0];
        if (str_contains($selected, ':')) {
            $selected = '[' . $selected . ']';
        }

        return [$host, $selected];
    }

    private function hostAllowed(string $host, bool $isRedirect): bool
    {
        if (in_array($host, array_map('strtolower', $this->allowedHosts), true)) {
            return true;
        }
        if (!$isRedirect) {
            return false;
        }

        foreach ($this->redirectHostSuffixes as $suffix) {
            $suffix = strtolower(trim((string) $suffix));
            if ($suffix !== '' && preg_match(
                '/^p[1-9][0-9]*-' . preg_quote($suffix, '/') . '$/D',
                $host,
            ) === 1) {
                return true;
            }
        }

        return false;
    }

    private function dimensionsAllowed(int $width, int $height): bool
    {
        return $width >= $this->minDimension
            && $height >= $this->minDimension
            && $width <= $this->maxDimension
            && $height <= $this->maxDimension
            && $width * $height <= $this->maxPixels;
    }

    private function ensureTemporaryDirectory(): void
    {
        if (!is_dir($this->temporaryDirectory)
            && !mkdir($this->temporaryDirectory, 0700, true)
            && !is_dir($this->temporaryDirectory)) {
            throw $this->downloadFailure();
        }
    }

    private function sourceNotAllowed(): ImageImportException
    {
        return new ImageImportException(
            BusinessCode::ImageSourceNotAllowed,
            'image_download',
            '图片来源不在允许范围内。',
        );
    }

    private function downloadFailure(): ImageImportException
    {
        return new ImageImportException(
            BusinessCode::ImageDownloadFailed,
            'image_download',
            '图片下载失败，请手动重试。',
        );
    }
}
