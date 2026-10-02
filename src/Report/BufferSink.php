<?php

namespace Dercol1\LibrenmsIfAlias\Report;

/**
 * Keeps the whole report in memory, so the web ui can show it in one go.
 */
final class BufferSink implements ReportSink
{
    private string $buffer = '';

    public function page(string $text): void
    {
        $this->buffer .= $text;
    }

    public function write(string $text): void
    {
        $this->buffer .= $text;
    }

    public function quitRequested(): bool
    {
        return false; // nobody walks away from a web page halfway
    }

    public function text(): string
    {
        return $this->buffer;
    }
}
