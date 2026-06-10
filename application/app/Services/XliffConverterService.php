<?php

namespace App\Services;

use App\Models\Media;
use App\Services\XmlInlineTagEncoder;
use Illuminate\Support\Facades\Http;


class XliffConverterService
{
    // private static $base = "http://host.docker.internal:8732";
    private static $base = "http://matecat-filters:8732";

    public static function convertOriginalToXliff($sourceLocale, $targetLocale, Media $sourceFile) {
        $stream = $sourceFile->stream();
        $content = stream_get_contents($stream);

        $ext = strtolower(pathinfo($sourceFile->file_name, PATHINFO_EXTENSION));
        if ($ext === 'xml') {
            $content = XmlInlineTagEncoder::encode($content);
        }

        $response = static::client()
            ->attach('documentContent', $content, $sourceFile->file_name)
            ->post("/AutomationService/original2xliff", [
                'sourceLocale' => $sourceLocale,
                'targetLocale' => $targetLocale,
            ]);
        $json = $response->throw()->json();

        if ($ext === 'xml' && !empty($json['xliffContent'])) {
            $json['xliffContent'] = XmlInlineTagEncoder::decode($json['xliffContent']);
        }

        return $json;
    }

    public static function convertXliffToOriginal(Media $xliffFile, string $originalFileName = '') {
        $stream = $xliffFile->stream();
        $content = stream_get_contents($stream);

        $ext = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
        if ($ext === 'xml') {
            $content = XmlInlineTagEncoder::encode($content);
        }

        $response = static::client()
            ->attach('xliffContent', $content, $xliffFile->file_name)
            ->post("/AutomationService/xliff2original");

        return $response->throw()->json();
    }

    private static function client() {
        return Http::baseUrl(static::$base);
    }
}
