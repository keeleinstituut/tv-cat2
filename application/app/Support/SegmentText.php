<?php

namespace App\Support;

use DOMDocument;
use DOMNode;

/**
 * The text of a segment (and of a translation memory entry) is plain text with a few formatting tags:
 *
 *   <b> <i> <u> <s> <sup> <sub> <br>   and   &amp; &lt; &gt;   for the characters & < >
 *
 * Nothing else: this is what catto-filters reads from and writes into files, and what machine translation and
 * translation memories see. Text from the browser editor or from a TMX import is brought into this form with
 * normalize(); the rest of the helpers deal with the escaping when the text is compared, counted or searched.
 */
final class SegmentText
{
    /** Element name to the tag it is stored as; elements not listed are unwrapped (their text stays). */
    private const TAGS = [
        'b' => 'b', 'strong' => 'b',
        'i' => 'i', 'em' => 'i',
        'u' => 'u', 'ins' => 'u',
        's' => 's', 'strike' => 's', 'del' => 's',
        'sup' => 'sup',
        'sub' => 'sub',
    ];

    /** Elements that start a new line (a browser editor creates them when Enter is pressed). */
    private const BLOCKS = ['div', 'p', 'li', 'tr'];

    private function __construct()
    {
    }

    /**
     * Brings text into the segment text form: formatting tags are mapped to the allowed ones, other markup is
     * dropped, entities become characters, and & < > are escaped again. Applying it twice changes nothing.
     */
    public static function normalize(?string $text): ?string
    {
        if ($text === null || $text === '') {
            return $text;
        }
        // Text without markup characters is already in the right form.
        if (!preg_match('/[<&>]/', $text)) {
            return $text;
        }

        $previous = libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $doc->loadHTML('<?xml encoding="UTF-8"><body>' . $text . '</body>', LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $body = $doc->getElementsByTagName('body')->item(0);
        $out = $body ? self::children($body) : '';

        // Browsers leave a <br> at the end of the last line.
        return preg_replace('/(<br>)+$/', '', $out);
    }

    private static function children(DOMNode $node): string
    {
        $out = '';
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE || $child->nodeType === XML_CDATA_SECTION_NODE) {
                $out .= self::escape($child->nodeValue);
                continue;
            }
            if ($child->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            $name = strtolower($child->nodeName);
            if ($name === 'br') {
                $out .= '<br>';
            } elseif (isset(self::TAGS[$name])) {
                $inner = self::children($child);
                $out .= $inner === '' ? '' : '<' . self::TAGS[$name] . '>' . $inner . '</' . self::TAGS[$name] . '>';
            } elseif (in_array($name, self::BLOCKS, true)) {
                // A block is a line: text before it ends the previous line, and it ends with a line break itself.
                // <div><br></div> is how a browser writes an empty line.
                $inner = self::children($child);
                if ($inner === '<br>') {
                    $inner = '';
                }
                if ($out !== '' && !str_ends_with($out, '<br>')) {
                    $out .= '<br>';
                }
                $out .= $inner . '<br>';
            } elseif ($name !== 'script' && $name !== 'style') {
                $out .= self::children($child);
            }
        }

        return $out;
    }

    /** Escapes text typed by a user so it can be compared with segment text (& < > only). */
    public static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }

    /**
     * The text a person reads: tags removed, <br> as a space, entities decoded. For word and character counts and for
     * services that need plain text (terminology search, no-translate rules).
     */
    public static function plain(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        return html_entity_decode(strip_tags(str_ireplace(['<br>', '<br/>', '<br />'], ' ', $text)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /** Number of words a person reads in the text (tags and entities do not count). */
    public static function wordCount(?string $text): int
    {
        return (int) preg_match_all('/[\p{L}\p{N}]+(?:[\'’-][\p{L}\p{N}]+)*/u', self::plain($text));
    }

    /** Number of characters a person reads in the text (tags and entities do not count). */
    public static function charCount(?string $text): int
    {
        return mb_strlen(self::plain($text));
    }

    /** A "%text%" pattern for ilike that finds typed text in segment text (escaped, with LIKE wildcards neutralised). */
    public static function likePattern(string $text): string
    {
        return '%' . addcslashes(self::escape($text), '\\%_') . '%';
    }
}
