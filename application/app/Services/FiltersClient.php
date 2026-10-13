<?php

namespace App\Services;

use App\Exceptions\UnsupportedFileTypeException;
use App\Models\Media;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

/**
 * Client of catto-filters. The service reads the translatable text out of a file (segments of plain text with
 * <b> <i> <u> <s> <sup> <sub> <br> and &amp; &lt; &gt; escaped) and writes translations back into the original file.
 */
class FiltersClient
{
    private const FORMATS_CACHE_KEY = 'filters.formats';

    /**
     * @return string[] lower case file extensions the service can read
     */
    public function formats(): array
    {
        return Cache::remember(self::FORMATS_CACHE_KEY, now()->addMinutes(10), function () {
            return $this->client()->timeout(10)->get('/formats')->throw()->json('extensions', []);
        });
    }

    /**
     * @return array<int, array{id: string, text: string}> the sentences of the file in document order
     */
    public function extract(Media $file, string $sourceLocale, string $targetLocale): array
    {
        $response = $this->client()
            ->attach('file', $file->stream(), $file->file_name)
            ->post('/extract', [
                'sourceLocale' => $sourceLocale,
                'targetLocale' => $targetLocale,
            ]);

        return $this->checked($response)->json('segments', []);
    }

    /**
     * @param  array<string, string>  $translations  segment id (as returned by extract) to translated text
     * @return string content of the translated file
     */
    public function merge(Media $file, array $translations, string $sourceLocale, string $targetLocale): string
    {
        $response = $this->client()
            ->attach('file', $file->stream(), $file->file_name)
            ->post('/merge', [
                'sourceLocale' => $sourceLocale,
                'targetLocale' => $targetLocale,
                // An empty list must stay a JSON object
                'translations' => json_encode((object) $translations, JSON_UNESCAPED_UNICODE),
            ]);

        return $this->checked($response)->body();
    }

    private function checked(Response $response): Response
    {
        if ($response->status() === 415) {
            throw new UnsupportedFileTypeException($response->json('error', 'Unsupported file type'));
        }
        if ($response->status() === 400) {
            throw new InvalidArgumentException($response->json('error', 'Invalid request to the filters service'));
        }

        return $response->throw();
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(config('services.filters.base_url'))
            ->timeout(config('services.filters.timeout'));
    }
}
