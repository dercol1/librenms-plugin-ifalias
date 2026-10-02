<?php

/*
 * Strings of the plugin settings page. The page is rendered by core, which
 * includes the plugin view, so these strings live in the plugin and not in the
 * core lang folder.
 */

return [
    'title' => 'ifAlias report',

    'intro' => 'Tells apart the ifAlias values you control from the ones LibreNMS filled in on its own. Read only: it never polls and never writes anything.',
    'settings' => [
        'panel' => 'Settings',
        'intro' => 'These are the defaults of both the report below and of <code>lnms port:ifAlias</code>. They are stored in the database, so they survive a LibreNMS update. Leave a field alone to keep the value from <code>config/ifalias.php</code>.',
        'save' => 'Save settings',
        'reset' => 'Back to the saved defaults',

        'device_spec' => 'Device spec',
        'device_spec_help' => 'Which devices the report looks at: a device id, a hostname, a group name, a wildcard like <code>core*</code>, <code>odd</code>, <code>even</code> or <code>all</code>.',

        'snmp' => 'Ask the devices',
        'snmp_help' => 'Walks IF-MIB::ifAlias and IF-MIB::ifDescr on every device, so the stored value can be compared with the live one. Off reads only the database: fast, works on unreachable devices, but then every port is reported as <code>db only</code>, without telling an override from a fallback.',

        'diff_only' => 'Only the ports that differ',
        'diff_only_help' => 'Hides the ports whose stored ifAlias is what the device reports.',

        'override_only' => 'Only the ports you overrode',
        'override_only_help' => 'Hides every port without a user override, the quickest way to review the values you maintain.',

        'include_inactive' => 'Include deleted and disabled ports',
        'include_inactive_help' => 'Ports removed from the device or disabled in LibreNMS are hidden unless this is on.',

        'colors' => 'Colours',
        'colors_help' => 'Colours the report: green matches the device, yellow is your override, blue is the automatic fallback, red differs without an override. On a terminal this follows the usual ansi detection, here this setting decides.',

        'max_devices' => 'Devices at most',
        'max_devices_help' => 'A walk over every device of a large install takes a long time. 0 means no limit; otherwise the report stops at that many devices and says so.',

        'lines' => 'Lines per page',
        'lines_help' => 'Terminal only, used when no pager is available: the report pauses every N lines waiting for Enter.',

        'pager' => 'Pager command',
        'pager_help' => 'Terminal only, empty to detect it: <code>IFALIAS_PAGER</code>, then <code>PAGER</code>, then <code>less -R -S -X -F</code>. Write <code>cat</code> for no paging at all.',

        'saved' => 'Settings saved.',
    ],

    'run' => [
        'panel' => 'Run the report',
        'intro' => 'Same report as <code>lnms port:ifAlias</code>, no terminal involved. The button below gives you the result in this page.',
        'device_spec' => 'Device spec',
        'snmp' => 'Ask the devices (--no-snmp)',
        'diff' => 'Only the ports that differ (--diff)',
        'override_only' => 'Only the ports you overrode (--override-only)',
        'inactive' => 'Include deleted and disabled ports (--inactive)',
        'colors' => 'Colours',
        'max_devices' => 'Devices at most',
        'submit' => 'Run',
        'idle' => 'Nothing has been run yet. Pick what to look at and press Run.',

        'output' => 'Output',
        'summary' => ':devices devices, :ports ports examined, in :seconds s',
        'empty' => 'The report is empty.',
        'filter' => 'Find in the output',
        'filter_none' => 'No line matches.',
        'wrap' => 'Wrap long lines',
        'copy' => 'Select all',
    ],

    'manual' => [
        'panel' => 'Manual',
        'toc' => 'Contents',
        'summary' => 'In short',
        'reading' => 'Reading the output',
        'web' => 'Running from the browser',
        'settings_ref' => 'Settings reference',
        'cli' => 'Command line',
        'limits' => 'Limits and gotchas',
    ],

    'note' => 'web ui, :date, device spec ":device", snmp :snmp',
    'snmp_on' => 'on',
    'snmp_off' => 'off',
];
