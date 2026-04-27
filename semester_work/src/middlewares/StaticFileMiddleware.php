<?php

namespace MyApp\middlewares;

use http\Message\Body;
use MyApp\entities\Request;
use MyApp\middlewares\IMiddleware;

class StaticFileMiddleware implements IMiddleware
{

    private array $mimeTypes = [
        "js" => "application/javascript",
        "html" => "text/html",
        "css" => "text/css",
        "png" => "image/png",
        "jpg" => "image/jpeg",
        "jpeg" => "image/jpeg",
        "gif" => "image/gif",
        "svg" => "image/svg+xml",
        "json" => "application/json",
        "txt" => "text/plain",
        "xml" => "application/xml",
        "pdf" => "application/pdf",
        "zip" => "application/zip",
        "mp3" => "audio/mpeg",
        "mp4" => "video/mp4",
        "webm" => "video/webm",
        "webp" => "image/webp",
        "ico" => "image/x-icon",
        "woff" => "font/woff",
        "woff2" => "font/woff2",
        "ttf" => "font/ttf",
        "eot" => "application/vnd.ms-fontobject",
        "" => "application/octet-stream"
    ];
    public function handle(Request $request, $next)
    {
        $mimeType = $this->getMimeType($request->uri);
        $filePath = __DIR__ . '\..\..\public'. $request->uri;
        if (!is_file($filePath)) {
            exit;
        }
        $fileSize = filesize($filePath);
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . $fileSize);
        if (ob_get_level()) ob_end_clean();
        readfile($filePath);
        exit;
    }

    private function getMimeType($filename): string
    {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        return $this->mimeTypes[$ext];
    }
}