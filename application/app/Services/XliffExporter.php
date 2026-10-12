<?php

namespace App\Services;

use App\Models\Job;
use App\Models\Segment;
use XMLWriter;

/**
 * Writes a job as a bilingual XLIFF 1.2 document, to hand the translation work to another CAT tool.
 *
 * The file is self-contained: one trans-unit per segment (id = segment id), no skeleton. The tool that receives it
 * can read, translate or review the segments, but cannot rebuild the original file from it; the translated
 * original file is produced by Catto (see MergeSegmentsJob).
 *
 * Formatting in the segment text (<b> <i> <u> <s> <sup> <sub> <br>) becomes XLIFF inline elements: <g ctype="...">
 * and <x ctype="lb"/>. Target tags reuse the ids of the source tags with the same formatting, so a translation
 * that moves or repeats spans still matches the source for the tag checks of the receiving tool.
 */
class XliffExporter
{
    private const NAMESPACE = 'urn:oasis:names:tc:xliff:document:1.2';

    /** Formatting tag to the ctype of its <g> element (predefined in XLIFF 1.2, otherwise x- prefixed). */
    private const CTYPES = [
        'b' => 'bold',
        'i' => 'italic',
        'u' => 'underlined',
        's' => 'x-strikethrough',
        'sup' => 'x-superscript',
        'sub' => 'x-subscript',
    ];

    private const TOKEN_PATTERN = '/(<\/?(?:b|i|u|s|sup|sub)>|<br\s*\/?>)/i';

    public function export(Job $job): string
    {
        $job->loadMissing('project', 'sourceFileCollection');
        $sourceLocale = $job->project->source_locale;
        $targetLocale = $job->target_locale;
        $original = $job->sourceFileCollection->first()?->file_name ?? $job->id;

        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->setIndentString('  ');
        $xml->startDocument('1.0', 'UTF-8');

        $xml->startElement('xliff');
        $xml->writeAttribute('version', '1.2');
        $xml->writeAttribute('xmlns', self::NAMESPACE);

        $xml->startElement('file');
        $xml->writeAttribute('original', $original);
        $xml->writeAttribute('source-language', $sourceLocale);
        $xml->writeAttribute('target-language', $targetLocale);
        $xml->writeAttribute('datatype', 'plaintext');
        $xml->writeAttribute('tool-id', 'catto');

        // tool-id above refers to this element
        $xml->startElement('header');
        $xml->startElement('tool');
        $xml->writeAttribute('tool-id', 'catto');
        $xml->writeAttribute('tool-name', 'Catto');
        $xml->endElement();
        $xml->endElement(); // header

        $xml->startElement('body');
        Segment::where('job_id', $job->id)->orderBy('position')->cursor()->each(
            fn (Segment $segment) => $this->writeTransUnit($xml, $segment, $sourceLocale, $targetLocale)
        );
        $xml->endElement(); // body

        $xml->endElement(); // file
        $xml->endElement(); // xliff
        $xml->endDocument();

        return $xml->outputMemory();
    }

    private function writeTransUnit(XMLWriter $xml, Segment $segment, string $sourceLocale, string $targetLocale): void
    {
        $source = self::inline($segment->source);

        $xml->startElement('trans-unit');
        $xml->writeAttribute('id', $segment->id);
        if ($segment->filter_segment_id) {
            $xml->writeAttribute('resname', $segment->filter_segment_id);
        }
        if ($segment->confirmed) {
            $xml->writeAttribute('approved', 'yes');
        }
        $xml->writeAttribute('xml:space', 'preserve');

        $xml->startElement('source');
        $xml->writeAttribute('xml:lang', $sourceLocale);
        $xml->writeRaw($source['xml']);
        $xml->endElement();

        if ($segment->target !== null && $segment->target !== '') {
            $target = self::inline($segment->target, $source['tags']);
            $xml->startElement('target');
            $xml->writeAttribute('xml:lang', $targetLocale);
            $xml->writeAttribute('state', self::state($segment));
            if ($qualifier = self::stateQualifier($segment)) {
                $xml->writeAttribute('state-qualifier', $qualifier);
            }
            $xml->writeRaw($target['xml']);
            $xml->endElement();
        }

        if ($segment->pretranslate_suggestion_provider_type) {
            $score = $segment->pretranslate_suggestion_score;
            $xml->startElement('note');
            $xml->writeAttribute('from', 'catto');
            $xml->text('Pretranslation: ' . $segment->pretranslate_suggestion_provider_type . ($score !== null ? ' ' . round($score) . '%' : ''));
            $xml->endElement();
        }

        $xml->endElement(); // trans-unit
    }

    private static function state(Segment $segment): string
    {
        if ($segment->confirmed) {
            return $segment->locked ? 'final' : 'translated';
        }

        // A target that nobody confirmed yet: pretranslation or a draft
        return 'needs-review-translation';
    }

    private static function stateQualifier(Segment $segment): ?string
    {
        if ($segment->confirmed) {
            return null;
        }

        return match ($segment->pretranslate_suggestion_provider_type) {
            'MT' => 'mt-suggestion',
            'TM' => ($segment->pretranslate_suggestion_score ?? 0) >= 100 ? 'exact-match' : 'fuzzy-match',
            default => null,
        };
    }

    /**
     * Converts segment text to XLIFF inline content.
     *
     * @param  array<int, array{kind: string, id: int}>|null  $reference  tags of the source text: spans of the same
     *                                                                     kind reuse their ids; null numbers from 1
     * @return array{xml: string, tags: array<int, array{kind: string, id: int}>} the XML and the tags in order
     */
    public static function inline(string $text, ?array $reference = null): array
    {
        $unused = $reference ?? [];
        $nextId = $reference ? max(array_column($reference, 'id')) + 1 : 1;
        $assign = function (string $kind) use (&$unused, &$nextId, $reference): int {
            if ($reference !== null) {
                foreach ($unused as $i => $tag) {
                    if ($tag['kind'] === $kind) {
                        unset($unused[$i]);

                        return $tag['id'];
                    }
                }
            }

            return $nextId++;
        };

        $xml = '';
        $tags = [];
        $open = [];
        foreach (preg_split(self::TOKEN_PATTERN, $text, -1, PREG_SPLIT_DELIM_CAPTURE) as $piece) {
            if ($piece === '') {
                continue;
            }
            $name = strtolower(trim($piece, '</> /'));

            if (preg_match('/^<br\s*\/?>$/i', $piece)) {
                $id = $assign('br');
                $tags[] = ['kind' => 'br', 'id' => $id];
                $xml .= '<x id="' . $id . '" ctype="lb"/>';
            } elseif (preg_match('/^<(b|i|u|s|sup|sub)>$/i', $piece)) {
                $id = $assign($name);
                $tags[] = ['kind' => $name, 'id' => $id];
                $open[] = $id;
                $xml .= '<g id="' . $id . '" ctype="' . self::CTYPES[$name] . '">';
            } elseif (preg_match('/^<\/(b|i|u|s|sup|sub)>$/i', $piece)) {
                if ($open) {
                    array_pop($open);
                    $xml .= '</g>';
                }
            } else {
                $xml .= self::escape($piece);
            }
        }
        $xml .= str_repeat('</g>', count($open));

        return ['xml' => $xml, 'tags' => $tags];
    }

    /** Text as XML character data: entities decoded and encoded again, characters XML does not allow dropped. */
    private static function escape(string $text): string
    {
        $text = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $text) ?? '';

        return htmlspecialchars(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_XML1 | ENT_NOQUOTES, 'UTF-8');
    }
}
