<?php

namespace Tests\Unit\Services;

use App\Services\XliffExporter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class XliffExporterInlineTest extends TestCase
{
    public static function sourceCases(): array
    {
        return [
            'plain text' => ['Hello world', 'Hello world'],
            'entities stay valid xml' => ['Tom &amp; Jerry &lt; 3 &gt; 2', 'Tom &amp; Jerry &lt; 3 &gt; 2'],
            'a lone ampersand is escaped' => ['Tom & Jerry', 'Tom &amp; Jerry'],
            'nbsp becomes a character' => ['a&nbsp;b', "a\u{00A0}b"],
            'formatting' => [
                'a <b>b</b> <i>i</i> <u>u</u> <s>s</s> x<sup>2</sup> H<sub>2</sub>O',
                'a <g id="1" ctype="bold">b</g> <g id="2" ctype="italic">i</g> <g id="3" ctype="underlined">u</g> <g id="4" ctype="x-strikethrough">s</g> x<g id="5" ctype="x-superscript">2</g> H<g id="6" ctype="x-subscript">2</g>O',
            ],
            'nested formatting' => ['<b><i>both</i></b>', '<g id="1" ctype="bold"><g id="2" ctype="italic">both</g></g>'],
            'line break' => ['one<br>two', 'one<x id="1" ctype="lb"/>two'],
            'unclosed tags are closed' => ['a <b>bold', 'a <g id="1" ctype="bold">bold</g>'],
            'stray closing tag is dropped' => ['a</b> b', 'a b'],
            'characters xml does not allow are dropped' => ["a\x01b\x0Bc", 'abc'],
            'empty' => ['', ''],
        ];
    }

    #[DataProvider('sourceCases')]
    public function test_source_text_becomes_inline_xml(string $text, string $expected): void
    {
        $result = XliffExporter::inline($text);

        $this->assertSame($expected, $result['xml']);
        // the result is well-formed xml
        $this->assertNotFalse(simplexml_load_string('<r xmlns:x="urn:x">' . $result['xml'] . '</r>'));
    }

    public function test_target_tags_reuse_the_ids_of_source_tags_with_the_same_formatting(): void
    {
        $source = XliffExporter::inline('<b>one</b> and <i>two</i> and <b>three</b>');
        $this->assertSame([['kind' => 'b', 'id' => 1], ['kind' => 'i', 'id' => 2], ['kind' => 'b', 'id' => 3]], $source['tags']);

        // reordered: italic first, then the two bold spans
        $target = XliffExporter::inline('<i>kaks</i> ja <b>üks</b> ja <b>kolm</b>', $source['tags']);

        $this->assertSame(
            '<g id="2" ctype="italic">kaks</g> ja <g id="1" ctype="bold">üks</g> ja <g id="3" ctype="bold">kolm</g>',
            $target['xml']
        );
    }

    public function test_extra_target_spans_get_new_ids_above_the_source_ids(): void
    {
        $source = XliffExporter::inline('<b>one</b> two<br>three');

        $target = XliffExporter::inline('<b>üks</b> <b>veel</b> <u>alla</u><br>kolm<br>neli', $source['tags']);

        $this->assertSame(
            '<g id="1" ctype="bold">üks</g> <g id="3" ctype="bold">veel</g> <g id="4" ctype="underlined">alla</g><x id="2" ctype="lb"/>kolm<x id="5" ctype="lb"/>neli',
            $target['xml']
        );
    }

    public function test_target_without_formatting_in_the_source_numbers_from_one(): void
    {
        $this->assertSame('<g id="1" ctype="bold">x</g>', XliffExporter::inline('<b>x</b>', [])['xml']);
    }
}
