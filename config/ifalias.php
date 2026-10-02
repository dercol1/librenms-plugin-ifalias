<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    |
    | Every value below can also be changed from the web ui, Plugins ->
    | ifalias -> Settings. The web ui stores what you pick in the database, so
    | this file only carries the defaults: a value saved from the browser wins
    | over this file, anything left untouched falls back to it.
    |
    */

    /*
     | Device spec used when neither the command line nor the web form names
     | one: device_id, hostname, wildcard (*), odd, even, all.
     */
    'device_spec' => 'all',

    /*
     | Contact the devices over snmp. Off means the report only reads the
     | database, which is fast and works for unreachable devices, but then a
     | stored ifAlias can only be compared against nothing, so every port is
     | reported as "db only" instead of device / override / fill.
     */
    'snmp' => true,

    /*
     | Only show the ports whose stored ifAlias differs from the device value.
     */
    'diff_only' => false,

    /*
     * Only show the ports a user overrode.
     */
    'override_only' => false,

    /*
     * Include deleted and disabled ports.
     */
    'include_inactive' => false,

    /*
     | Colour the report. On a terminal this follows the usual ansi detection,
     | in the web ui this setting decides whether the text window is coloured.
     */
    'colors' => true,

    /*
     | How many devices to examine at most, 0 for no limit. A walk over every
     | device of a large install takes a long time, the limit keeps a run from
     | walking away, both on the terminal and in a web request. The report says
     | when it stopped early.
     */
    'max_devices' => 0,

    /*
     | How many lines are printed before the command pauses waiting for Enter.
     | Only used when no usable pager is available, since less -S handles long
     | reports on its own.
     */
    'lines' => 30,

    /*
     | Pager command, null to auto detect (IFALIAS_PAGER, then PAGER, then
     | less -R -S -X -F). "cat" or an empty string disables paging.
     */
    'pager' => null,
];
