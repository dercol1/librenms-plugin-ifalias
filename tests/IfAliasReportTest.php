<?php

/**
 * IfAliasReportTest.php
 *
 * The report is the piece both the terminal and the web ui show, so it is tested
 * on its own here: it writes to a sink instead of a terminal, and it reports
 * what it found instead of only printing it.
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

namespace Dercol1\LibrenmsIfAlias\Tests\Feature;

use App\Models\Device;
use App\Models\Port;
use Dercol1\LibrenmsIfAlias\Report\BufferSink;
use Dercol1\LibrenmsIfAlias\Report\IfAliasReport;
use Dercol1\LibrenmsIfAlias\Report\ReportOptions;
use Dercol1\LibrenmsIfAlias\Report\ReportResult;
use LibreNMS\Data\Source\Snmp\SnmpQueryInterface;
use LibreNMS\Data\Source\Snmp\SnmpResponse;
use LibreNMS\Tests\InMemoryDbTestCase;
use Mockery;
use RuntimeException;

final class IfAliasReportTest extends InMemoryDbTestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_an_unknown_device_is_reported_as_not_matched(): void
    {
        [$result, $text] = $this->render(new ReportOptions(deviceSpec: 'nothing-like-this'));

        $this->assertFalse($result->matched);
        $this->assertSame(0, $result->devices);
        $this->assertStringContainsString('No device matched', $text);
    }

    public function test_the_counters_cover_every_port_not_only_the_printed_ones(): void
    {
        $device = Device::factory()->create(['os' => 'generic']);
        $this->port($device, 1, 'Gi0/1', 'cust: Acme [1Gbps]');
        $this->port($device, 2, 'Gi0/2', 'transit: provider [10Gbps]');
        $device->setAttrib('ifName:Gi0/1', '1');
        $this->fakeSnmp([1 => 'transit: other [1Gbps]', 2 => 'transit: provider [10Gbps]']);

        [$result, $text] = $this->render(new ReportOptions(
            deviceSpec: $device->hostname,
            diffOnly: true,
            colors: true,
        ));

        $this->assertTrue($result->matched);
        $this->assertSame(1, $result->devices);
        $this->assertSame(2, $result->ports);
        $this->assertSame(1, $result->different);
        $this->assertSame(1, $result->overrides);
        $this->assertSame(1, $result->same);
        // only the differing port was printed, but the counters know about both
        $this->assertStringNotContainsString('Gi0/2', $text);
        $this->assertStringContainsString('Stored ifAlias equals the device value: 1', $text);
    }

    /**
     * The note under the header is written by the caller, the report only puts it
     * in the right place.
     */
    public function test_the_caller_note_lands_under_the_header(): void
    {
        Device::factory()->create(['os' => 'generic']);

        [, $text] = $this->render(new ReportOptions(snmp: false), 'Pager: off');

        $this->assertStringStartsWith(__('ifalias::command.sources'), $text);
        $this->assertStringContainsString("Pager: off\n", $text);
    }

    public function test_the_device_limit_stops_the_report_and_says_so(): void
    {
        Device::factory()->count(3)->create(['os' => 'generic']);

        [$result, $text] = $this->render(new ReportOptions(snmp: false, maxDevices: 2));

        $this->assertTrue($result->matched);
        $this->assertTrue($result->truncated);
        $this->assertSame(2, $result->devices);
        $this->assertStringContainsString(__('ifalias::command.truncated', ['devices' => 2]), $text);
    }

    /**
     * A limit that is not reached must not claim the report was cut short.
     */
    public function test_a_limit_that_is_not_reached_does_not_warn(): void
    {
        Device::factory()->count(2)->create(['os' => 'generic']);

        [$result, $text] = $this->render(new ReportOptions(snmp: false, maxDevices: 5));

        $this->assertFalse($result->truncated);
        $this->assertSame(2, $result->devices);
        $this->assertStringNotContainsString('only the first', $text);
    }

    public function test_without_snmp_nothing_can_be_compared(): void
    {
        $device = Device::factory()->create(['os' => 'generic']);
        $this->port($device, 1, 'Gi0/1', 'transit: provider [10Gbps]');

        [$result, $text] = $this->render(new ReportOptions(deviceSpec: $device->hostname, snmp: false));

        $this->assertSame(0, $result->different);
        $this->assertSame(0, $result->same);
        $this->assertStringContainsString('(not polled)', $text);
    }

    /**
     * A device that cannot be walked must not take the whole report down.
     */
    public function test_a_failing_walk_is_reported_as_no_device_data(): void
    {
        $device = Device::factory()->create(['os' => 'generic']);
        $this->port($device, 1, 'Gi0/1', 'transit: provider [10Gbps]');

        $this->app->bind(SnmpQueryInterface::class, function () {
            $mock = Mockery::mock(SnmpQueryInterface::class);
            $mock->shouldReceive('hideMib')->andReturnSelf();
            $mock->shouldReceive('device')->andReturnSelf();
            $mock->shouldReceive('walk')->andThrow(new RuntimeException('snmp unreachable'));

            return $mock;
        });

        [$result, $text] = $this->render(new ReportOptions(deviceSpec: $device->hostname));

        $this->assertTrue($result->matched);
        $this->assertSame(1, $result->ports);
        $this->assertStringContainsString('Gi0/1', $text);
    }

    public function test_the_colours_can_be_left_out(): void
    {
        $device = Device::factory()->create(['os' => 'generic']);
        $this->port($device, 1, 'Gi0/1', 'transit: provider [10Gbps]');

        [, $text] = $this->render(new ReportOptions(deviceSpec: $device->hostname, snmp: false));

        $this->assertStringNotContainsString("\033[", $text);
    }

    /**
     * @return array{0: ReportResult, 1: string}
     */
    private function render(ReportOptions $options, ?string $note = null): array
    {
        $sink = new BufferSink;
        $result = (new IfAliasReport($options))->render($sink, $note);

        return [$result, $sink->text()];
    }

    private function port(Device $device, int $ifIndex, string $ifName, ?string $ifAlias): Port
    {
        return Port::factory()->create([
            'device_id' => $device->device_id,
            'ifIndex' => $ifIndex,
            'ifName' => $ifName,
            'ifAlias' => $ifAlias,
            'ifDescr' => $ifName,
        ]);
    }

    /**
     * @param  array<int, string>  $alias
     */
    private function fakeSnmp(array $alias): void
    {
        $this->app->bind(SnmpQueryInterface::class, function () use ($alias) {
            $mock = Mockery::mock(SnmpQueryInterface::class);
            $mock->shouldReceive('hideMib')->andReturnSelf();
            $mock->shouldReceive('device')->andReturnSelf();
            $mock->shouldReceive('walk')->andReturnUsing(function (array $oids) use ($alias) {
                $values = [];
                foreach ($oids as $oid) {
                    foreach ($oid === 'ifAlias' ? $alias : [] as $index => $value) {
                        $values["$oid.$index"] = $value;
                    }
                }

                return new SnmpResponse($values);
            });

            return $mock;
        });
    }
}
