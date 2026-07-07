<?php

namespace App\Services;

class XmlInlineTagEncoder
{
    private static array $tags = ['i', 'sup', 'b', 'sub', 'strong', 'em'];

    public static function encode(string $content): string
    {
        foreach (self::$tags as $tag) {
            $content = preg_replace("/<{$tag}>/i", "&lt;{$tag}&gt;", $content);
            $content = preg_replace("/<\/{$tag}>/i", "&lt;/{$tag}&gt;", $content);
        }
        return $content;
    }

    public static function decode(string $content): string
    {
        foreach (self::$tags as $tag) {
            $content = str_replace("&lt;{$tag}&gt;", "<{$tag}>", $content);
            $content = str_replace("&lt;/{$tag}&gt;", "</{$tag}>", $content);
            // handle half-encoded edge cases (e.g. <sup&gt;)
            $content = str_replace("<{$tag}&gt;", "<{$tag}>", $content);
            $content = str_replace("</{$tag}&gt;", "</{$tag}>", $content);
        }
        return $content;
    }
}
