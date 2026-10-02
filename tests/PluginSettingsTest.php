<?php

/**
 * PluginSettingsTest.php
 *
 * The settings page hands over strings: an unchecked checkbox arrives as "0" and
 * a text field as whatever was typed. Everything has to be coerced before the
 * report can rely on it, and a missing or broken value must fall back to the
 * config default instead of breaking a run.
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

use Dercol1\LibrenmsIfAlias\Report\ReportOptions;
use Dercol1\LibrenmsIfAlias\Settings\PluginSettings;
use LibreNMS\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class PluginSettingsTest extends TestCase
{
    public function test_nothing_stored_means_the_config_defaults(): void
    {
        $settings = PluginSettings::fromArray([]);

        $this->assertSame('all', $settings->deviceSpec());
        $this->assertTrue($settings->snmp());
        $this->assertFalse($settings->diffOnly());
        $this->assertFalse($settings->overrideOnly());
        $this->assertFalse($settings->includeInactive());
        $this->assertTrue($settings->colors());
        $this->assertSame(0, $settings->maxDevices());
        $this->assertSame(30, $settings->lines());
        $this->assertNull($settings->pager());
    }

    public function test_a_stored_value_wins_over_the_config(): void
    {
        $settings = PluginSettings::fromArray(['device_spec' => 'core*', 'snmp' => '0', 'lines' => '80']);

        $this->assertSame('core*', $settings->deviceSpec());
        $this->assertFalse($settings->snmp());
        $this->assertSame(80, $settings->lines());
    }

    /**
     * @param  mixed  $value
     */
    #[DataProvider('offValues')]
    public function test_every_way_of_saying_off_means_off($value): void
    {
        $this->assertFalse(PluginSettings::fromArray(['snmp' => $value])->snmp());
    }

    /**
     * @param  mixed  $value
     */
    #[DataProvider('onValues')]
    public function test_every_way_of_saying_on_means_on($value): void
    {
        $this->assertTrue(PluginSettings::fromArray(['colors' => $value])->colors());
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function offValues(): array
    {
        return [
            'zero' => ['0'],
            'false' => ['false'],
            'no' => ['no'],
            'off' => ['OFF'],
            'empty' => [''],
            'zero int' => [0],
            'false bool' => [false],
        ];
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function onValues(): array
    {
        return [
            'one' => ['1'],
            'true' => ['true'],
            'yes' => ['yes'],
            'on' => ['on'],
            'true bool' => [true],
            'one int' => [1],
        ];
    }

    public function test_a_blank_device_spec_falls_back_to_all(): void
    {
        $this->assertSame('all', PluginSettings::fromArray(['device_spec' => '   '])->deviceSpec());
    }

    public function test_a_blank_pager_means_auto_detect(): void
    {
        $this->assertNull(PluginSettings::fromArray(['pager' => '  '])->pager());
        $this->assertSame('most -R', PluginSettings::fromArray(['pager' => ' most -R '])->pager());
    }

    public function test_numbers_are_clamped_to_something_usable(): void
    {
        $this->assertSame(1, PluginSettings::fromArray(['lines' => 0])->lines());
        $this->assertSame(1, PluginSettings::fromArray(['lines' => -5])->lines());
        $this->assertSame(1000, PluginSettings::fromArray(['lines' => 99999])->lines());
        $this->assertSame(0, PluginSettings::fromArray(['max_devices' => -1])->maxDevices());
        $this->assertSame(30, PluginSettings::fromArray(['lines' => 'not a number'])->lines());
    }

    /**
     * The run form sends 0 or 1, an absent field must fall back to the setting.
     */
    public function test_report_options_take_the_input_over_the_setting(): void
    {
        $settings = PluginSettings::fromArray([
            'device_spec' => 'core*',
            'snmp' => 0,
            'diff_only' => 0,
            'max_devices' => 10,
        ]);

        $options = ReportOptions::fromInput([
            'device_spec' => ' router01 ',
            'snmp' => '1',
            'diff' => '1',
            'max_devices' => '50',
        ], $settings);

        $this->assertSame('router01', $options->deviceSpec);
        $this->assertTrue($options->snmp);
        $this->assertTrue($options->diffOnly);
        $this->assertSame(50, $options->maxDevices);
        // not sent by the form, so the setting decides
        $this->assertFalse($options->overrideOnly);
        $this->assertFalse($options->includeInactive);
    }

    /**
     * An empty device spec in the form must not turn into "match nothing".
     */
    public function test_an_empty_device_spec_keeps_the_setting(): void
    {
        $settings = PluginSettings::fromArray(['device_spec' => 'core*']);

        $this->assertSame('core*', ReportOptions::fromInput(['device_spec' => '  '], $settings)->deviceSpec);
        $this->assertSame('core*', ReportOptions::fromInput([], $settings)->deviceSpec);
    }
}
