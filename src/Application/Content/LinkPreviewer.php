<?php

declare(strict_types=1);

namespace Fred\Application\Content;

use DOMDocument;
use DOMXPath;
use Fred\Infrastructure\Config\AppConfig;

use function array_slice;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function filemtime;
use function filter_var;
use function in_array;
use function is_array;
use function is_dir;
use function json_decode;
use function json_encode;
use function libxml_clear_errors;
use function libxml_use_internal_errors;
use function max;
use function parse_url;
use function preg_match_all;
use function sha1;
use function strtolower;
use function trim;

use const FILTER_FLAG_NO_PRIV_RANGE;
use const FILTER_FLAG_NO_RES_RANGE;
use const FILTER_VALIDATE_IP;
use const JSON_PRETTY_PRINT;
use const LIBXML_NONET;

final class LinkPreviewer
{
    private string $cacheDir;
    private int $ttlSeconds = 86400; // 24 hours for successful fetches
    private int $failedTtlSeconds = 3600; // Avoid retrying failed hosts for an hour

    public function __construct(private readonly AppConfig $config)
    {
        $this->cacheDir = rtrim($this->config->basePath, '/') . '/storage/link_previews';

        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0775, true);
        }
    }

    /**
     * @return array<int, array{url:string, title:string, description:string|null, image:string|null, host:string}>
     */
    public function previewsForText(string $text, int $limit = 3): array
    {
        $urls = $this->extractUrls($text, $limit);

        $previews = [];
        $toFetch = [];
        $cachePaths = [];

        foreach ($urls as $url) {
            $url = trim($url);

            if ($url === '' || !$this->isSafeUrl($url)) {
                continue;
            }

            $cacheCheck = $this->cachedPreview($url);
            $cachePaths[$url] = $cacheCheck['path'];

            if ($cacheCheck['status'] === 'hit' && isset($cacheCheck['data'])) {
                $previews[] = $cacheCheck['data'];
                continue;
            }

            if ($cacheCheck['status'] === 'failed_recent') {
                continue;
            }

            $toFetch[] = $url;
        }

        if ($toFetch === []) {
            return $previews;
        }

        $metadataByUrl = $this->fetchMetadataConcurrent($toFetch);

        foreach ($toFetch as $url) {
            $metadata = $metadataByUrl[$url] ?? null;
            $cachePath = $cachePaths[$url] ?? null;

            if ($cachePath === null) {
                continue;
            }

            if ($metadata !== null) {
                @file_put_contents($cachePath, (string) json_encode($metadata, JSON_PRETTY_PRINT));
                $previews[] = $metadata;
                continue;
            }

            @file_put_contents($cachePath, (string) json_encode(['failed' => true, 'url' => $url], JSON_PRETTY_PRINT));
        }

        return $previews;
    }

    /**
     * Extract unique HTTP(S) URLs from text without fetching them.
     *
     * @return array<int, string>
     */
    public function extractUrls(string $text, int $limit = 3): array
    {
        preg_match_all('#https?://[^\s\[\]<>"\']+#i', $text, $matches);
        $limit = max(1, $limit);

        return array_slice(array_unique($matches[0] ?? []), 0, $limit);
    }

    /** @return array{url:string, title:string, description:string|null, image:string|null, host:string}|null */
    public function previewForUrl(string $url): ?array
    {
        $url = trim($url);

        if ($url === '' || !$this->isSafeUrl($url)) {
            return null;
        }

        $cacheKey = sha1($url);
        $cachePath = $this->cacheDir . '/' . $cacheKey . '.json';

        if (file_exists($cachePath)) {
            $age = time() - filemtime($cachePath);
            $cached = json_decode((string) file_get_contents($cachePath), true);

            if (is_array($cached) && ($cached['failed'] ?? false) === true) {
                if ($age < $this->failedTtlSeconds) {
                    return null;
                }
            } elseif (is_array($cached) && isset($cached['url'], $cached['title']) && $age < $this->ttlSeconds) {
                return $cached;
            }
        }

        $metadata = $this->fetchMetadata($url);

        if ($metadata === null) {
            @file_put_contents($cachePath, (string) json_encode(['failed' => true, 'url' => $url], JSON_PRETTY_PRINT));

            return null;
        }

        @file_put_contents($cachePath, (string) json_encode($metadata, JSON_PRETTY_PRINT));

        return $metadata;
    }

    private function isSafeUrl(string $url): bool
    {
        $parts = parse_url($url);

        if (!is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        if (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($host === '' || $host === 'localhost') {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false && filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return false;
        }

        return true;
    }

    /** @return array{url:string, title:string, description:string|null, image:string|null, host:string}|null */
    private function fetchMetadata(string $url): ?array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 5,
                'follow_location' => 1,
                'user_agent' => 'FredForumLinkPreview/1.0',
            ],
        ]);

        $html = @file_get_contents($url, false, $context);

        if ($html === false || trim($html) === '') {
            return null;
        }

        return $this->parseMetadata($url, $html);
    }

    /**
     * Attempt to fetch multiple URLs concurrently. Falls back to sequential fetches if cURL multi is unavailable.
     *
     * @param array<int, string> $urls
     * @return array<string, array{url:string, title:string, description:string|null, image:string|null, host:string}|null>
     */
    private function fetchMetadataConcurrent(array $urls): array
    {
        if (!function_exists('curl_multi_init')) {
            $results = [];

            foreach ($urls as $url) {
                $results[$url] = $this->fetchMetadata($url);
            }

            return $results;
        }

        $multi = curl_multi_init();
        $handles = [];

        foreach ($urls as $url) {
            $ch = curl_init($url);

            if ($ch === false) {
                $handles[$url] = null;
                continue;
            }

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_USERAGENT => 'FredForumLinkPreview/1.0',
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);

            curl_multi_add_handle($multi, $ch);
            $handles[$url] = $ch;
        }

        do {
            $status = curl_multi_exec($multi, $active);

            if ($active) {
                curl_multi_select($multi, 1.0);
            }
        } while ($active && $status === CURLM_OK);

        $results = [];

        foreach ($handles as $url => $ch) {
            if ($ch === null) {
                $results[$url] = null;
                continue;
            }

            $error = curl_errno($ch);
            $content = $error === 0 ? (string) curl_multi_getcontent($ch) : '';

            if ($error === 0 && trim($content) !== '') {
                $results[$url] = $this->parseMetadata($url, $content);
            } else {
                $results[$url] = null;
            }

            curl_multi_remove_handle($multi, $ch);
            curl_close($ch);
        }

        curl_multi_close($multi);

        return $results;
    }

    /** @return array{url:string, title:string, description:string|null, image:string|null, host:string}|null */
    private function parseMetadata(string $url, string $html): ?array
    {
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $loaded = @$doc->loadHTML($html, LIBXML_NONET);
        libxml_clear_errors();

        if ($loaded === false) {
            return null;
        }

        $xpath = new DOMXPath($doc);
        $meta = static fn (string $prop): ?string => ($nodes = $xpath->query('//meta[@property="' . $prop . '"]/@content')) && $nodes->length > 0
            ? trim((string) $nodes->item(0)?->nodeValue)
            : null;

        $metaName = static fn (string $name): ?string => ($nodes = $xpath->query('//meta[@name="' . $name . '"]/@content')) && $nodes->length > 0
            ? trim((string) $nodes->item(0)?->nodeValue)
            : null;

        $title = $meta('og:title') ?? $metaName('title') ?? $this->textContent($xpath, '//title');

        if ($title === null || $title === '') {
            return null;
        }

        $description = $meta('og:description') ?? $metaName('description');
        $image = $meta('og:image');

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return [
            'url' => $url,
            'title' => $title,
            'description' => $description !== '' ? $description : null,
            'image' => $image !== '' ? $image : null,
            'host' => $host,
        ];
    }

    private function textContent(DOMXPath $xpath, string $query): ?string
    {
        $nodes = $xpath->query($query);

        if (!$nodes || $nodes->length === 0) {
            return null;
        }

        $text = trim((string) $nodes->item(0)?->textContent);

        return $text === '' ? null : $text;
    }

    /**
     * @return array{status: 'hit'|'miss'|'failed_recent', data?: array{url:string, title:string, description:string|null, image:string|null, host:string}, path: string}
     */
    private function cachedPreview(string $url): array
    {
        $cacheKey = sha1($url);
        $cachePath = $this->cacheDir . '/' . $cacheKey . '.json';

        if (!file_exists($cachePath)) {
            return ['status' => 'miss', 'path' => $cachePath];
        }

        $age = time() - filemtime($cachePath);
        $cached = json_decode((string) file_get_contents($cachePath), true);

        if (is_array($cached) && ($cached['failed'] ?? false) === true) {
            if ($age < $this->failedTtlSeconds) {
                return ['status' => 'failed_recent', 'path' => $cachePath];
            }

            return ['status' => 'miss', 'path' => $cachePath];
        }

        if (is_array($cached) && isset($cached['url'], $cached['title']) && $age < $this->ttlSeconds) {
            return ['status' => 'hit', 'data' => $cached, 'path' => $cachePath];
        }

        return ['status' => 'miss', 'path' => $cachePath];
    }
}

