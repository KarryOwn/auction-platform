<?php

namespace App\Services;

class VideoEmbedService
{
    public function parse(string $url): ?array
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (! $host) {
            return null;
        }

        $host = strtolower($host);
        $allowedDomains = config('auction.video.allowed_domains', []);

        if (! in_array($host, $allowedDomains, true)) {
            return null;
        }

        $youtubeId = $this->extractYouTubeId($url);
        if ($youtubeId !== null) {
            return [
                'provider' => 'youtube',
                'id' => $youtubeId,
                'embed_url' => "https://www.youtube.com/embed/{$youtubeId}",
                'thumbnail_url' => "https://img.youtube.com/vi/{$youtubeId}/hqdefault.jpg",
            ];
        }

        $vimeoId = $this->extractVimeoId($url);
        if ($vimeoId !== null) {
            return [
                'provider' => 'vimeo',
                'id' => $vimeoId,
                'embed_url' => "https://player.vimeo.com/video/{$vimeoId}",
                'thumbnail_url' => null,
            ];
        }

        return null;
    }

    private function extractYouTubeId(string $url): ?string
    {
        $patterns = [
            '/youtu\.be\/([A-Za-z0-9_-]{6,15})/i',
            '/youtube\.com\/watch\?v=([A-Za-z0-9_-]{6,15})/i',
            '/youtube\.com\/embed\/([A-Za-z0-9_-]{6,15})/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches) === 1) {
                return $matches[1];
            }
        }

        return null;
    }

    private function extractVimeoId(string $url): ?string
    {
        $patterns = [
            '/vimeo\.com\/(\d+)/i',
            '/player\.vimeo\.com\/video\/(\d+)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches) === 1) {
                return $matches[1];
            }
        }

        return null;
    }
}
