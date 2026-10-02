<?php

namespace Dercol1\LibrenmsIfAlias\Report;

use Dercol1\LibrenmsIfAlias\Settings\PluginSettings;

/**
 * Everything the report needs to know, independent of who asked for it.
 *
 * The command line and the web ui both build one of these, so the two report
 * exactly the same thing.
 */
final class ReportOptions
{
    public function __construct(
        public readonly string $deviceSpec = 'all',
        public readonly bool $snmp = true,
        public readonly bool $diffOnly = false,
        public readonly bool $overrideOnly = false,
        public readonly bool $includeInactive = false,
        public readonly bool $colors = false,
        public readonly int $maxDevices = 0,
        public readonly int $lines = 30,
        public readonly ?string $pager = null,
    ) {}

    /** The settings page defaults, used when a run is not submitted. */
    public static function fromSettings(PluginSettings $settings): self
    {
        return new self(
            deviceSpec: $settings->deviceSpec(),
            snmp: $settings->snmp(),
            diffOnly: $settings->diffOnly(),
            overrideOnly: $settings->overrideOnly(),
            includeInactive: $settings->includeInactive(),
            colors: $settings->colors(),
            maxDevices: $settings->maxDevices(),
            lines: $settings->lines(),
            pager: $settings->pager(),
        );
    }

    /**
     * The form state of the run form, as it comes back from a request: a field
     * the form did not send falls back to the saved setting.
     *
     * @param  array<string, mixed>  $input
     */
    public static function fromInput(array $input, PluginSettings $settings): self
    {
        return new self(
            deviceSpec: self::text($input, 'device_spec', $settings->deviceSpec()),
            snmp: self::flag($input, 'snmp', $settings->snmp()),
            diffOnly: self::flag($input, 'diff', $settings->diffOnly()),
            overrideOnly: self::flag($input, 'override_only', $settings->overrideOnly()),
            includeInactive: self::flag($input, 'inactive', $settings->includeInactive()),
            colors: self::flag($input, 'colors', $settings->colors()),
            maxDevices: self::number($input, 'max_devices', $settings->maxDevices(), 0, 100000),
            lines: $settings->lines(),
            pager: $settings->pager(),
        );
    }

    /**
     * The same options as form fields, so the blade can fill the inputs without
     * repeating the coercion rules.
     *
     * @return array<string, bool|int|string>
     */
    public function toInput(): array
    {
        return [
            'device_spec' => $this->deviceSpec,
            'snmp' => $this->snmp,
            'diff' => $this->diffOnly,
            'override_only' => $this->overrideOnly,
            'inactive' => $this->includeInactive,
            'colors' => $this->colors,
            'max_devices' => $this->maxDevices,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private static function text(array $input, string $key, string $default): string
    {
        $value = $input[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : $default;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private static function flag(array $input, string $key, bool $default): bool
    {
        return array_key_exists($key, $input) ? (bool) $input[$key] : $default;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private static function number(array $input, string $key, int $default, int $min, int $max): int
    {
        $value = $input[$key] ?? null;

        return max($min, min($max, is_numeric($value) ? (int) $value : $default));
    }
}
