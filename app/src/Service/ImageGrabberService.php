<?php

namespace App\Service;

use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ImageGrabberService
{
    private string $uploadDir;
    private string $uploadPath = '/uploads';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $projectDir,
    ) {
        $this->uploadDir = $this->projectDir . '/public' . $this->uploadPath;
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }

    public function grabImages(string $pageUrl, int $minWidth, int $minHeight, string $text): array
    {
        $html = $this->fetchHtml($pageUrl);
        $imageUrls = $this->extractImageUrls($html, $pageUrl);

        $saved = [];
        foreach ($imageUrls as $imageUrl) {
            try {
                $publicPath = $this->processAndSave($imageUrl, $minWidth, $minHeight, $text);
                if ($publicPath !== null) {
                    $saved[] = $publicPath;
                }
            } catch (\Throwable) {
                // пропускаем битые картинки
            }
        }
        return $saved;
    }

    public function getSavedImages(): array
    {
        $files = glob($this->uploadDir . '/*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);
        if (!$files) return [];
        usort($files, static fn($a, $b) => filemtime($b) <=> filemtime($a));
        return array_map(fn($f) => $this->uploadPath . '/' . basename($f), $files);
    }

    private function fetchHtml(string $url): string
    {
        $response = $this->httpClient->request('GET', $url, [
            'timeout' => 30,
            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; ImageGrabber/1.0)'],
        ]);
        return $response->getContent();
    }

    private function extractImageUrls(string $html, string $baseUrl): array
    {
        $crawler = new Crawler($html, $baseUrl);
        $urls = [];
        $crawler->filter('img')->each(function (Crawler $node) use ($baseUrl, &$urls) {
            foreach (['src', 'data-src', 'data-lazy-src'] as $attr) {
                $src = $node->attr($attr);
                if ($src && !str_starts_with($src, 'data:')) {
                    $urls[] = $this->resolveUrl($src, $baseUrl);
                    break;
                }
            }
        });
        return array_unique(array_filter($urls));
    }

    private function resolveUrl(string $src, string $baseUrl): string
    {
        if (str_starts_with($src, 'http://') || str_starts_with($src, 'https://')) return $src;
        $parsed = parse_url($baseUrl);
        $scheme = $parsed['scheme'] ?? 'https';
        $host   = $parsed['host'] ?? '';
        if (str_starts_with($src, '//')) return $scheme . ':' . $src;
        if (str_starts_with($src, '/')) return $scheme . '://' . $host . $src;
        $basePath = isset($parsed['path']) ? dirname($parsed['path']) : '';
        return $scheme . '://' . $host . rtrim($basePath, '/') . '/' . $src;
    }

    private function processAndSave(string $imageUrl, int $minWidth, int $minHeight, string $text): ?string
    {
        $response = $this->httpClient->request('GET', $imageUrl, [
            'timeout' => 15,
            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; ImageGrabber/1.0)'],
        ]);
        $content = $response->getContent();
        if (empty($content)) return null;

        $image = @imagecreatefromstring($content);
        if ($image === false) return null;

        $origWidth  = imagesx($image);
        $origHeight = imagesy($image);

        if (($minWidth > 0 && $origWidth < $minWidth) || ($minHeight > 0 && $origHeight < $minHeight)) {
            imagedestroy($image);
            return null;
        }

        // Ресайз по высоте до 200px
        $ratio     = 200 / $origHeight;
        $newWidth  = max(1, (int) round($origWidth * $ratio));
        $resized   = imagecreatetruecolor($newWidth, 200);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, 200, $origWidth, $origHeight);
        imagedestroy($image);

        // Обрезка по ширине до 200px
        $cropX   = (int) max(0, ($newWidth - 200) / 2);
        $cropped = imagecreatetruecolor(200, 200);
        $white   = imagecolorallocate($cropped, 255, 255, 255);
        imagefill($cropped, 0, 0, $white);
        imagecopy($cropped, $resized, 0, 0, $cropX, 0, min(200, $newWidth), 200);
        imagedestroy($resized);

        // Текст на картинке
        if ($text !== '') {
            $this->drawText($cropped, $text);
        }

        $filename = substr(md5($imageUrl . microtime()), 0, 16) . '_' . time() . '.jpg';
        $filepath = $this->uploadDir . '/' . $filename;
        imagejpeg($cropped, $filepath, 88);
        imagedestroy($cropped);

        return $this->uploadPath . '/' . $filename;
    }

    private function drawText(\GdImage $image, string $text): void
    {
        $overlay = imagecreatetruecolor(200, 30);
        imagealphablending($overlay, false);
        $bg = imagecolorallocatealpha($overlay, 0, 0, 0, 64);
        imagefill($overlay, 0, 0, $bg);
        imagecopy($image, $overlay, 0, 170, 0, 0, 200, 30);
        imagedestroy($overlay);

        $white  = imagecolorallocate($image, 255, 255, 255);
        $shadow = imagecolorallocate($image, 0, 0, 0);
        $font   = 4;
        $maxChars = (int) floor(196 / imagefontwidth($font));
        $display  = mb_strlen($text) > $maxChars ? mb_substr($text, 0, $maxChars - 1) . '…' : $text;

        imagestring($image, $font, 7, 177, $display, $shadow);
        imagestring($image, $font, 6, 176, $display, $white);
    }
}
