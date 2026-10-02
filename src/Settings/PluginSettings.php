<?php

namespace Dercol1\LibrenmsIfAlias\Settings;

use Dercol1\LibrenmsIfAlias\IfAliasPluginProvider;
use LibreNMS\Interfaces\Plugins\PluginManagerInterface;

/**
 * The plugin settings, the values saved from the settings page first and the
 * published config file as fallback.
 *
 * The web ui stores them in the database (the settings column of the plugins
 * table), config/ifalias.php only carries the defaults. Everything is coerced
 * here, because the settings page hands over strings: an unchecked checkbox is
 * posted as "0" and a text field as whatever was typed.
 */
final class PluginSettings
{
    /**
     * @param  array<string, mixed>  $stored
     */
    public function __construct(private readonly array $stored = []) {}

    public static function current(): self
    {
        return self::fromArray(
            app(PluginManagerInterface::class)->getSettings(IfAliasPluginProvider::PLUGIN_NAME)
        );
    }

    /**
     * @param  array<string, mixed>  $stored
     */
    public static function fromArray(array $stored): self
    {
        return new self($stored);
    }

    /**
     * The stored settings as they came from the database, used to fill the
     * settings form.
     *
     * @return array<string, mixed>
     */
    public function stored(): array
    {
        return $this->stored;
    }

    /** Device spec used when neither the command line nor the form names one. */
    public function deviceSpec(): string
    {
        $spec = trim((string) $this->raw('device_spec'));

        return $spec === '' ? 'all' : $spec;
    }

    /** Contact the devices, otherwise the database values are reported alone. */
    public function snmp(): bool
    {
        return $this->flag('snmp');
    }

    /** Report only the ports whose stored ifAlias differs from the device. */
    public function diffOnly(): bool
    {
        return $this->flag('diff_only');
    }

    /** Report only the ports a user overrode. */
    public function overrideOnly(): bool
    {
        return $this->flag('override_only');
    }

    /** Include deleted and disabled ports. */
    public function includeInactive(): bool
    {
        return $this->flag('include_inactive');
    }

    /** Colour the report. */
    public function colors(): bool
    {
        return $this->flag('colors');
    }

    /** Devices to examine at most, 0 for no limit. */
    public function maxDevices(): int
    {
        return $this->number('max_devices', 0, 0, 100000);
    }

    /** Lines per page, only used when no usable pager is available. */
    public function lines(): int
    {
        return $this->number('lines', 30, 1, 1000);
    }

    /** Pager command, null to auto detect. */
    public function pager(): ?string
    {
        $pager = $this->raw('pager');

        return is_string($pager) && trim($pager) !== '' ? trim($pager) : null;
    }

    /** A stored value if there is one, the config default otherwise. */
    private function raw(string $key): mixed
    {
        return array_key_exists($key, $this->stored)
            ? $this->stored[$key]
            : config('ifalias.' . $key);
    }

    /**
     * The settings page posts strings, so "0", "false" and an empty field all
     * have to count as off.
     */
    private function flag(string $key): bool
    {
        $value = $this->raw($key);

        if (is_string($value)) {
            return ! in_array(strtolower(trim($value)), ['', '0', 'false', 'off', 'no'], true);
        }

        return (bool) $value;
    }

    private function number(string $key, int $default, int $min, int $max): int
    {
        $value = $this->raw($key);

        return max($min, min($max, is_numeric($value) ? (int) $value : $default));
    }
}
