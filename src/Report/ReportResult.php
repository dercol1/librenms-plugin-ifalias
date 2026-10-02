<?php

namespace Dercol1\LibrenmsIfAlias\Report;

/**
 * What a run found, so the caller can report it without parsing the text.
 */
final class ReportResult
{
    public function __construct(
        /** False when no device matched the device spec at all. */
        public readonly bool $matched,
        public readonly int $devices,
        public readonly int $ports,
        public readonly int $overrides,
        public readonly int $fills,
        public readonly int $different,
        public readonly int $same,
        /** True when the device limit stopped the report early. */
        public readonly bool $truncated,
    ) {}
}
