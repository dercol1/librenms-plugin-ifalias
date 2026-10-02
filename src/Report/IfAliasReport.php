<?php

namespace Dercol1\LibrenmsIfAlias\Report;

use App\Models\Device;
use App\Models\Port;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use SnmpQuery;
use Throwable;

/**
 * Report where the ifAlias stored for each port comes from.
 *
 * The value in the ports table is not necessarily what the device reports. It
 * can come from the device, from a user override, or from the fallback
 * LibreNMS applies when a device reports no ifAlias. Only the web ui hints at
 * which one is in use, so auditing which ifAlias values are user maintained
 * otherwise means writing SQL against devices_attribs by hand.
 *
 * Read only: nothing is polled and nothing is written. The report is built here
 * and handed to a sink, so the terminal and the web ui show the same thing.
 */
final class IfAliasReport
{
    /** The device attribute the web ui writes to mark a user override. */
    private const OVERRIDE_PREFIX = 'ifName:';

    /** The value written by the web ui, meaning "use ports.ifAlias". */
    private const OVERRIDE_LEGACY = '1';

    private const RESET = "\033[0m";

    private const BOLD = "\033[1m";

    private const DIM = "\033[2m";

    private const RED = "\033[0;31m";

    private const GREEN = "\033[0;32m";

    private const YELLOW = "\033[0;33m";

    private const BLUE = "\033[0;34m";

    private int $same = 0;

    private int $different = 0;

    private int $overrides = 0;

    private int $fills = 0;

    private int $ports = 0;

    private int $devices = 0;

    private bool $truncated = false;

    public function __construct(private readonly ReportOptions $options) {}

    /**
     * Build the report.
     *
     * @param  string|null  $note  a line under the header, the caller describes
     *                             itself there (pager, or web ui)
     */
    public function render(ReportSink $sink, ?string $note = null): ReportResult
    {
        $devices = $this->selectDevices();

        if ($devices->isEmpty()) {
            $sink->write(__('ifalias::command.errors.no_device') . PHP_EOL);

            return $this->result(false);
        }

        $sink->write($this->header($note) . PHP_EOL);

        foreach ($devices as $device) {
            $this->processDevice($device, $sink);

            if ($sink->quitRequested()) {
                break;
            }
        }

        // a summary over a partial run would be misleading
        if (! $sink->quitRequested()) {
            $sink->write(PHP_EOL . $this->summary() . PHP_EOL);
        }

        return $this->result(true);
    }

    /**
     * The devices to examine, at most max_devices of them.
     *
     * One extra device is fetched to tell a report that stopped at the limit
     * from one that simply had nothing left, the difference is what the header
     * says.
     *
     * @return Collection<int, Device>
     */
    private function selectDevices(): Collection
    {
        $query = Device::whereDeviceSpec($this->options->deviceSpec)->orderBy('hostname');

        if ($this->options->maxDevices > 0) {
            $query->limit($this->options->maxDevices + 1);
        }

        $devices = $query->get();

        if ($this->options->maxDevices > 0 && $devices->count() > $this->options->maxDevices) {
            $this->truncated = true;

            return $devices->take($this->options->maxDevices)->values();
        }

        return $devices;
    }

    private function header(?string $note): string
    {
        $lines = [__('ifalias::command.sources') . ' ' . __('ifalias::command.source_legend')];

        if ($note !== null && $note !== '') {
            $lines[] = $note;
        }

        if ($this->truncated) {
            $lines[] = __('ifalias::command.truncated', ['devices' => $this->options->maxDevices]);
        }

        return $this->paint(self::DIM) . implode(PHP_EOL, $lines) . $this->paint(self::RESET);
    }

    private function processDevice(Device $device, ReportSink $sink): void
    {
        $ports = $device->ports()
            ->when(! $this->options->includeInactive, fn (Builder $query) => $query->where('deleted', 0)->where('disabled', 0))
            ->orderBy('ifIndex')
            ->get();

        $this->devices++;
        $this->ports += $ports->count();

        $sink->page($this->deviceHeader($device, $ports->count()) . PHP_EOL);

        if ($ports->isEmpty()) {
            $sink->page(__('ifalias::command.errors.no_ports') . PHP_EOL . PHP_EOL);

            return;
        }

        $device_values = $this->options->snmp ? $this->fetchDeviceValues($device) : [];

        $sink->page($this->tableHeader() . PHP_EOL);

        foreach ($ports as $port) {
            $this->processPort($device, $port, $device_values, $sink);

            if ($sink->quitRequested()) {
                return;
            }
        }

        $sink->page(PHP_EOL);
    }

    private function deviceHeader(Device $device, int $portCount): string
    {
        // Device::displayName() is deprecated, read the field directly
        $name = (string) ($device->display ?: $device->hostname);

        return $this->paint(self::BOLD) . __('ifalias::command.device', [
            'device' => $name,
            'os' => $device->os,
            'ports' => $portCount,
        ]) . $this->paint(self::RESET);
    }

    /**
     * Live values from the device, keyed by ifIndex then by bare OID name.
     *
     * ifAlias and ifDescr are walked separately so that a device not
     * implementing one of them still reports the other. hideMib() is required:
     * without it the keys come back as IF-MIB::ifAlias.1 and the lookups below
     * would never match.
     *
     * @return array<int, array<string, string>>
     */
    private function fetchDeviceValues(Device $device): array
    {
        $values = [];

        foreach (['ifAlias', 'ifDescr'] as $oid) {
            try {
                $response = SnmpQuery::make()->hideMib()->device($device)->walk([$oid]);
            } catch (Throwable) {
                continue; // not supported, the column is reported as unavailable
            }

            if (! $response->isValid()) {
                continue;
            }

            foreach ($response->valuesByIndex() as $index => $row) {
                $values[$index] = array_merge($values[$index] ?? [], $row);
            }
        }

        return $values;
    }

    /**
     * @param  array<int, array<string, string>>  $device_values
     */
    private function processPort(Device $device, Port $port, array $device_values, ReportSink $sink): void
    {
        $db = (string) ($port->ifAlias ?? '');
        $device_alias = (string) ($device_values[$port->ifIndex]['ifAlias'] ?? '');
        $device_descr = (string) ($device_values[$port->ifIndex]['ifDescr'] ?? '');

        $override = $device->getAttrib(self::OVERRIDE_PREFIX . $port->ifName);
        $is_override = $override !== null;
        $is_different = $this->options->snmp && $db !== $device_alias;

        $source = $this->determineSource($is_override, $device_alias, $device_descr, $db, (string) $port->ifName);

        // count every port, then filter, so the summary always describes the
        // whole install and not only what was printed
        $is_fill = str_starts_with($source, 'fill');
        if ($is_override) {
            $this->overrides++;
        }
        if ($is_fill) {
            $this->fills++;
        }
        if ($is_different) {
            $this->different++;
        } elseif ($this->options->snmp) {
            $this->same++;
        }

        if (! $this->shouldPrint($is_override, $is_different)) {
            return;
        }

        $sink->page($this->portRow(
            $port,
            $db,
            $device_alias,
            $source,
            $is_override,
            $override === null ? null : (string) $override,
            $is_different,
            $is_fill
        ));
    }

    /**
     * Where the stored ifAlias comes from.
     *
     * An override always wins, so it is checked first. Otherwise the device has
     * the last word, unless it reports no ifAlias at all: in that case
     * port_fill_missing_and_trim() in includes/functions.php has copied ifDescr,
     * or ifName when there is no ifDescr either, into the ifAlias field. That is
     * LibreNMS filling a gap, not something the user asked for, and the two are
     * reported separately so they are never confused.
     */
    private function determineSource(
        bool $is_override,
        string $device_alias,
        string $device_descr,
        string $db,
        string $ifName
    ): string {
        if ($is_override) {
            return 'override';
        }

        if (! $this->options->snmp) {
            return 'db only';
        }

        if ($device_alias !== '') {
            return 'device';
        }

        $expected = $device_descr !== '' ? $device_descr : $ifName;

        if ($expected !== '' && $db === $expected) {
            return 'fill ' . ($device_descr !== '' ? 'ifDescr' : 'ifName');
        }

        return 'fill ?';
    }

    private function shouldPrint(bool $is_override, bool $is_different): bool
    {
        if ($this->options->overrideOnly && ! $is_override) {
            return false;
        }

        return ! $this->options->diffOnly || $is_different;
    }

    private function portRow(
        Port $port,
        string $db,
        string $device_alias,
        string $source,
        bool $is_override,
        ?string $override,
        bool $is_different,
        bool $is_fill
    ): string {
        $status = $is_different ? 'DIFFERENT' : ($this->options->snmp ? 'same' : 'db-only');
        $row = $this->paint($this->rowColor($is_override, $is_fill, $is_different)) . sprintf(
            '%-7s %-20s %-30s %-30s %-13s %s',
            $port->ifIndex,
            $this->shorten((string) $port->ifName, 18),
            $this->shorten($db, 28),
            $this->options->snmp ? $this->shorten($device_alias, 28) : '(not polled)',
            $source,
            $status
        ) . $this->paint(self::RESET) . PHP_EOL;

        $note = $this->paint(self::DIM);

        if ($is_override && $override !== self::OVERRIDE_LEGACY) {
            $row .= '           ' . $note . __('ifalias::command.notes.override_value', ['value' => $override]) . $this->paint(self::RESET) . PHP_EOL;
        }

        if ($is_override && $override === self::OVERRIDE_LEGACY) {
            $row .= '           ' . $note . __('ifalias::command.notes.override_legacy') . $this->paint(self::RESET) . PHP_EOL;
        }

        if ($source === 'fill ?') {
            $row .= '           ' . $note . __('ifalias::command.notes.fill_unknown') . $this->paint(self::RESET) . PHP_EOL;
        } elseif ($is_fill) {
            $row .= '           ' . $note . __('ifalias::command.notes.fill', [
                'field' => $source === 'fill ifDescr' ? 'ifDescr' : 'ifName',
            ]) . $this->paint(self::RESET) . PHP_EOL;
        }

        if ($is_different && ! $is_override && $device_alias !== '') {
            $row .= '           ' . $note . __('ifalias::command.notes.will_overwrite') . $this->paint(self::RESET) . PHP_EOL;
        }

        return $row;
    }

    /**
     * One colour per case, so a long report can be skimmed: yellow is a value
     * a user controls, blue is the automatic fallback, red differs from the
     * device without an override, green matches it.
     */
    private function rowColor(bool $is_override, bool $is_fill, bool $is_different): string
    {
        return match (true) {
            $is_override => self::YELLOW,
            $is_fill => self::BLUE,
            $is_different => self::RED,
            $this->options->snmp => self::GREEN,
            default => '',
        };
    }

    /** Escape sequence, or nothing when the report is not decorated. */
    private function paint(string $code): string
    {
        return $this->options->colors ? $code : '';
    }

    private function tableHeader(): string
    {
        return $this->paint(self::BOLD) . sprintf(
            '%-7s %-20s %-30s %-30s %-13s %s',
            'ifIndex',
            'ifName',
            'db (ports.ifAlias)',
            'snmp (IF-MIB::ifAlias)',
            'source',
            'status'
        ) . $this->paint(self::RESET) . PHP_EOL;
    }

    private function shorten(string $value, int $width): string
    {
        return mb_strimwidth($value, 0, $width, '..');
    }

    private function summary(): string
    {
        return __('ifalias::command.summary', [
            'different' => $this->different,
            'overrides' => $this->overrides,
            'fills' => $this->fills,
            'same' => $this->same,
        ]);
    }

    private function result(bool $matched): ReportResult
    {
        return new ReportResult(
            matched: $matched,
            devices: $this->devices,
            ports: $this->ports,
            overrides: $this->overrides,
            fills: $this->fills,
            different: $this->different,
            same: $this->same,
            truncated: $this->truncated,
        );
    }
}
