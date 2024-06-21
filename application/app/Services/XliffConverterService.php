<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Support\Facades\Http;


class XliffConverterService
{
    // private static $base = "http://host.docker.internal:8732";
    private static $base = "http://matecat-filters:8732";

    public static function convertOriginalToXliff($sourceLocale, $targetLocale, Media $sourceFile) {
        $stream = $sourceFile->stream();
        $content = stream_get_contents($stream);
        $response = static::client()
            ->attach('documentContent', $content, $sourceFile->file_name)
            ->post("/AutomationService/original2xliff", [
                'sourceLocale' => $sourceLocale,
                'targetLocale' => $targetLocale,
            ]);
        return $response->throw()->json();
    }

    public static function convertXliffToOriginal(Media $xliffFile) {
        $stream = $xliffFile->stream();
        $content = stream_get_contents($stream);
        $response = static::client()
            ->attach('xliffContent', $content, $xliffFile->file_name)
            ->post("/AutomationService/xliff2original");
        return $response->throw()->json();
    }

    private static function client() {
        return Http::baseUrl(static::$base);
    }
}
