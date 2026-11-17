<?php

declare(strict_types=1);

namespace Explorin\LogInspector\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

final class AssetController
{
    /**
     * Serve internal package assets (CSS, JS, Fonts) directly with strict security & HTTP caching.
     */
    public function serve(Request $request, string $path): Response
    {
        // 1. Path traversal guard
        if (str_contains($path, '..') || str_contains($path, "\0")) {
            abort(404);
        }

        $packagePublicDir = realpath(__DIR__ . '/../../../public');
        if ($packagePublicDir === false) {
            abort(404);
        }

        $cleanPath = ltrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
        $fullPath = $packagePublicDir . DIRECTORY_SEPARATOR . $cleanPath;

        $resolvedPath = realpath($fullPath);
        if ($resolvedPath === false || ! is_file($resolvedPath)) {
            abort(404);
        }

        // Strict boundary check: ensure resolved realpath is within package's public directory
        if (! str_starts_with($resolvedPath, $packagePublicDir . DIRECTORY_SEPARATOR)) {
            abort(404);
        }

        // 2. MIME type detection
        $ext = strtolower(pathinfo($resolvedPath, PATHINFO_EXTENSION));
        $contentType = match ($ext) {
            'css' => 'text/css; charset=UTF-8',
            'js' => 'application/javascript; charset=UTF-8',
            'map' => 'application/json; charset=UTF-8',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            default => 'text/plain',
        };

        // 3. ETag & HTTP 304 Cache Negotiation
        $lastModified = (int) filemtime($resolvedPath);
        $etag = '"' . md5($resolvedPath . $lastModified) . '"';

        $clientEtag = $request->header('If-None-Match');
        $clientSince = $request->header('If-Modified-Since');

        if ($clientEtag === $etag || ($clientSince !== null && strtotime($clientSince) >= $lastModified)) {
            return new Response(null, 304, [
                'ETag' => $etag,
                'Cache-Control' => 'public, max-age=31536000, immutable',
            ]);
        }

        // 4. Return cached response
        $headers = [
            'Content-Type' => $contentType,
            'Content-Length' => (string) filesize($resolvedPath),
            'Last-Modified' => gmdate('D, d M Y H:i:s', $lastModified) . ' GMT',
            'ETag' => $etag,
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ];

        return new BinaryFileResponse($resolvedPath, 200, $headers, false, null, false, false);
    }
}
