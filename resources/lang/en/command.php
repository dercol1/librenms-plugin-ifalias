<?php

return [
    'description' => 'Show whether the ifAlias stored for each port comes from the device, from a user override, or from the automatic fallback used when a device reports no ifAlias',

    'arguments' => [
        'device spec' => 'Device spec: device_id, hostname, wildcard (*), odd, even, all',
    ],

    'options' => [
        'diff' => 'Only show ports where the stored ifAlias differs from the device value (--no-diff to show all)',
        'override-only' => 'Only show ports that have a user override (--no-override-only to show all)',
        'inactive' => 'Include deleted and disabled ports (--no-inactive to hide them)',
        'snmp' => 'Contact the devices, --no-snmp reports the database values only',
        'max-devices' => 'Examine at most this many devices, 0 for no limit',
        'pager' => 'Pager command to use, "cat" to disable paging',
        'lines' => 'Lines to print before pausing, when the pager is unavailable',
    ],

    'device' => 'Device :device: (os: :os, ports: :ports)',

    'sources' => 'Sources:',
    'source_legend' => 'override = set by a user, fill ifDescr / fill ifName = automatic fallback, device = reported by the device, db only = not polled',

    'pager' => 'Pager:',
    'default_pager' => 'less -R -S -X -F',
    'pager_fallback' => 'no usable pager, pausing every :lines lines instead',
    'pager_off' => 'off, the output is not a terminal',

    'truncated' => 'only the first :devices devices are reported, raise --max-devices or set it to 0 in the plugin settings',

    'notes' => [
        'override_value' => 'override attribute value: :value',
        'override_legacy' => 'override uses the value stored in ports.ifAlias',
        'fill' => 'the device reports no ifAlias and LibreNMS copied :field into it. This is an automatic fallback, not your override.',
        'fill_unknown' => 'the device reports no ifAlias, but the stored value matches neither ifDescr nor ifName, so its origin is unknown',
        'will_overwrite' => 'no override is set, the next poll will overwrite the stored value with the device value',
    ],

    'errors' => [
        'no_device' => 'No device matched. Only active devices are considered.',
        'no_ports' => 'No ports in the database, run discovery first.',
    ],

    'summary' => 'Stored ifAlias differs from the device: :different'
        . "\n  of which a user override: :overrides"
        . "\n  of which an automatic fallback (device reports no ifAlias): :fills"
        . "\nStored ifAlias equals the device value: :same",
];
