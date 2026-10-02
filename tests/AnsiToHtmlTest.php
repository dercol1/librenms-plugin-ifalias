<?php

/**
 * AnsiToHtmlTest.php
 *
 * The report is built as text with ansi escape sequences and shown in a browser
 * by turning those sequences into markup. A stored ifAlias comes from a device,
 * so the text must be escaped before it is wrapped in a span: this test pins
 * both halves of that promise.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * @link       https://www.librenms.org
 *
 * @copyright  2026 LibreNMS
 */

namespace Dercol1\LibrenmsIfAlias\Tests\Unit;

use Dercol1\LibrenmsIfAlias\Web\AnsiToHtml;
use PHPUnit\Framework\TestCase;

final class AnsiToHtmlTest extends TestCase
{
    public function test_plain_text_is_only_escaped(): void
    {
        $this->assertSame('override &amp; device', AnsiToHtml::convert('override & device'));
    }

    public function test_each_colour_becomes_its_own_class(): void
    {
        $html = AnsiToHtml::convert("\033[0;33moverride\033[0m plain");

        $this->assertSame('<span class="ifalias-yellow">override</span> plain', $html);
    }

    public function test_the_reset_closes_the_span(): void
    {
        $html = AnsiToHtml::convert("\033[1mDevice\033[0m core1");

        $this->assertSame('<span class="ifalias-bold">Device</span> core1', $html);
    }

    /**
     * An unknown sequence is dropped, not passed through: markup can never be
     * smuggled in through an escape sequence the report does not emit.
     */
    public function test_an_unknown_sequence_is_ignored(): void
    {
        $html = AnsiToHtml::convert("a\033[38;5;208mb\033[0m");

        $this->assertSame('ab', $html);
    }

    /**
     * Values stored in the database come from devices and from users, so they
     * are escaped even when they sit between colour codes.
     */
    public function test_text_is_escaped_inside_a_colour(): void
    {
        $html = AnsiToHtml::convert("\033[0;31m<script>alert(1)</script>\033[0m");

        $this->assertSame(
            '<span class="ifalias-red">&lt;script&gt;alert(1)&lt;/script&gt;</span>',
            $html
        );
    }

    public function test_quotes_are_escaped_too(): void
    {
        $html = AnsiToHtml::convert('"quoted" & \'quoted\'');

        $this->assertSame('&quot;quoted&quot; &amp; &#039;quoted&#039;', $html);
    }

    public function test_newlines_are_kept(): void
    {
        $this->assertSame("one\ntwo\n", AnsiToHtml::convert("one\ntwo\n"));
    }

    public function test_an_unterminated_colour_is_closed(): void
    {
        $this->assertSame('<span class="ifalias-blue">tail</span>', AnsiToHtml::convert("\033[0;34mtail"));
    }
}
