<?php

namespace Dercol1\LibrenmsIfAlias\Report;

/**
 * Where the report writes.
 *
 * The terminal writes through a pager, which can pause and can be quit, the web
 * ui collects everything into a buffer. The report itself does not care, it
 * only asks for a page break to land between two ports and stops early when the
 * reader walked away.
 */
interface ReportSink
{
    /**
     * Write a block, breaking the page here if needed. A page break must land
     * between blocks, so a caller passes a whole table row as one block and it
     * is never split.
     */
    public function page(string $text): void;

    /** Write without page accounting, for headers and the summary. */
    public function write(string $text): void;

    /** True once the reader stopped reading, the report must stop too. */
    public function quitRequested(): bool;
}
