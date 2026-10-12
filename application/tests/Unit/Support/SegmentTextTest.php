<?php

namespace Tests\Unit\Support;

use App\Support\SegmentText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SegmentTextTest extends TestCase
{
    public static function normalizeCases(): array
    {
        return [
            'plain text is unchanged' => ['Hello world', 'Hello world'],
            'allowed tags are kept' => ['a <b>b</b> <i>i</i> <u>u</u> <s>s</s> x<sup>2</sup> H<sub>2</sub>O', 'a <b>b</b> <i>i</i> <u>u</u> <s>s</s> x<sup>2</sup> H<sub>2</sub>O'],
            'browser tags are mapped' => ['<strong>b</strong> <em>i</em> <del>s</del> <ins>u</ins> <strike>t</strike>', '<b>b</b> <i>i</i> <s>s</s> <u>u</u> <s>t</s>'],
            'span and font are unwrapped' => ['<span style="color:red">a</span> <font>b</font>', 'a b'],
            'nbsp becomes a character' => ['a&nbsp;b', "a\u{00A0}b"],
            'escaped characters stay escaped' => ['a &amp; b &lt; c &gt; d', 'a &amp; b &lt; c &gt; d'],
            'stray ampersand is escaped' => ['Tom & Jerry', 'Tom &amp; Jerry'],
            'stray less-than is escaped' => ['1 < 2', '1 &lt; 2'],
            'escaped markup stays text' => ['&lt;b&gt;not bold&lt;/b&gt;', '&lt;b&gt;not bold&lt;/b&gt;'],
            'numeric and named entities' => ['&#65;&eacute;&quot;', 'Aé"'],
            'enter in the editor' => ['line one<div>line two</div><div><br></div><div>line four</div>', 'line one<br>line two<br><br>line four'],
            'trailing br is dropped' => ['text<br>', 'text'],
            'empty formatting is dropped' => ['a<b></b>b', 'ab'],
            'unclosed tags are balanced' => ['a <b>bold', 'a <b>bold</b>'],
            'script is dropped' => ['a<script>alert(1)</script>b', 'ab'],
            'nested formatting' => ['<b><i>both</i></b>', '<b><i>both</i></b>'],
        ];
    }

    #[DataProvider('normalizeCases')]
    public function test_normalize(string $input, string $expected): void
    {
        $this->assertSame($expected, SegmentText::normalize($input));
        // normalizing is idempotent
        $this->assertSame($expected, SegmentText::normalize($expected));
    }

    public function test_normalize_keeps_null_and_empty(): void
    {
        $this->assertNull(SegmentText::normalize(null));
        $this->assertSame('', SegmentText::normalize(''));
    }

    public function test_plain_returns_the_text_a_person_reads(): void
    {
        $this->assertSame('Tom & Jerry <3 x2 line two', SegmentText::plain('Tom &amp; Jerry &lt;3 x<sup>2</sup> line<br>two'));
        $this->assertSame('', SegmentText::plain(null));
    }

    public function test_escape_and_like_pattern_match_stored_text(): void
    {
        $this->assertSame('a &amp; b &lt;c&gt;', SegmentText::escape('a & b <c>'));
        $this->assertSame('%100\\% a\\_b &amp;%', SegmentText::likePattern('100% a_b &'));
    }
}
