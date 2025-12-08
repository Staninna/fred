<?php

declare(strict_types=1);

namespace Fred\Application\Content;

use Fred\Infrastructure\Config\AppConfig;

use DOMDocument;
use DOMXPath;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function filter_var;
use function implode;
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
use function strlen;
use function strtolower;
use function trim;
use function filemtime;

use const FILTER_FLAG_NO_PRIV_RANGE;
use const FILTER_FLAG_NO_RES_RANGE;
use const FILTER_VALIDATE_IP;
use const JSON_PRETTY_PRINT;
use const LIBXML_NONET;

final class LinkPreviewer
{
    private string $cacheDir;
    private int $ttlSeconds = 43200; // 12 hours

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
        preg_match_all('#https?://[^\s\[\]<>"\']+#i', $text, $matches);
        $urls = array_slice(array_unique($matches[0] ?? []), 0, max(1, $limit));

        $previews = [];
        foreach ($urls as $url) {
            $preview = $this->previewForUrl($url);
            if ($preview !== null) {
                $previews[] = $preview;
            }
        }

        return $previews;
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

        if (file_exists($cachePath) && (time() - filemtime($cachePath)) < $this->ttlSeconds) {
            $cached = json_decode((string) file_get_contents($cachePath), true);
            if (is_array($cached) && isset($cached['url'], $cached['title'])) {
                return $cached;
            }
        }

        $metadata = $this->fetchMetadata($url);
        if ($metadata === null) {
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
                'timeout' => 3,
                'follow_location' => 1,
                'user_agent' => 'FredForumLinkPreview/1.0',
            ],
        ]);

        $html = @file_get_contents($url, false, $context);
        if ($html === false || trim($html) === '') {
            return null;
        }

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
}
