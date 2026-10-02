<?php

namespace Dercol1\LibrenmsIfAlias\Web;

/**
 * Turn the escape sequences of a decorated report into html.
 *
 * The report writes raw codes because the terminal needs them, and the web ui
 * needs the same report inside a page. Only the codes the report actually emits
 * are mapped, anything else is consumed and ignored, so an unexpected sequence
 * cannot leak markup. Every piece of text is escaped before it is wrapped.
 */
final class AnsiToHtml
{
    /** The codes IfAliasReport emits, and the class each one becomes. */
    private const STYLES = [
        '1' => 'ifalias-bold',
        '2' => 'ifalias-dim',
        '0;31' => 'ifalias-red',
        '0;32' => 'ifalias-green',
        '0;33' => 'ifalias-yellow',
        '0;34' => 'ifalias-blue',
    ];

    public static function convert(string $text): string
    {
        $parts = preg_split('/(\033\[[0-9;]*m)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        if ($parts === false) {
            return self::escape($text);
        }

        $html = '';
        $open = false;

        foreach ($parts as $part) {
            if (str_starts_with($part, "\033[")) {
                // close before opening again: one span per run keeps the html flat
                if ($open) {
                    $html .= '</span>';
                    $open = false;
                }

                $class = self::STYLES[substr($part, 2, -1)] ?? null;

                if ($class !== null) {
                    $html .= '<span class="' . $class . '">';
                    $open = true;
                }

                continue;
            }

            $html .= self::escape($part);
        }

        return $html . ($open ? '</span>' : '');
    }

    private static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
