{{--
    The plugin manual, shown on the settings page. A fragment like the settings
    view itself, see settings.blade.php.
--}}
<div class="panel panel-default" id="ifalias-manual">
    <div class="panel-heading">
        <strong>{{ __('ifalias::settings.manual.panel') }}</strong>
    </div>
    <div class="panel-body">

        <p class="ifalias-toc">
            <a href="#ifalias-manual-summary">{{ __('ifalias::settings.manual.summary') }}</a> &middot;
            <a href="#ifalias-manual-reading">{{ __('ifalias::settings.manual.reading') }}</a> &middot;
            <a href="#ifalias-manual-web">{{ __('ifalias::settings.manual.web') }}</a> &middot;
            <a href="#ifalias-manual-settings">{{ __('ifalias::settings.manual.settings_ref') }}</a> &middot;
            <a href="#ifalias-manual-cli">{{ __('ifalias::settings.manual.cli') }}</a> &middot;
            <a href="#ifalias-manual-limits">{{ __('ifalias::settings.manual.limits') }}</a>
        </p>

        <h4 id="ifalias-manual-summary">{{ __('ifalias::settings.manual.summary') }}</h4>

        <p>
            LibreNMS keeps one <code>ifAlias</code> per port in the <code>ports</code> table, but the value
            in it is not always what the device sent. It can come from three places:
        </p>

        <div class="table-responsive">
            <table class="table table-condensed ifalias-table">
                <thead>
                    <tr>
                        <th>Source</th>
                        <th>Meaning</th>
                        <th>Written by</th>
                        <th>Survives a poll?</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="ifalias-yellow-row">
                        <td><code>override</code></td>
                        <td>A user wrote the description, so it is theirs to maintain.</td>
                        <td>Device &rarr; Edit &rarr; Ports, or the api</td>
                        <td>Yes, the next poll does not touch it</td>
                    </tr>
                    <tr class="ifalias-green-row">
                        <td><code>device</code></td>
                        <td>The device reports <code>IF-MIB::ifAlias</code>.</td>
                        <td>The device</td>
                        <td>Yes, it is refreshed every poll</td>
                    </tr>
                    <tr class="ifalias-blue-row">
                        <td><code>fill ifDescr</code><br><code>fill ifName</code></td>
                        <td>
                            The device reports no <code>ifAlias</code> and LibreNMS copied another field into
                            it, so the column looks filled even though nobody asked for it.
                        </td>
                        <td><code>port_fill_missing_and_trim()</code>, on every poll</td>
                        <td>Yes, it is refreshed every poll</td>
                    </tr>
                    <tr class="ifalias-red-row">
                        <td><code>fill ?</code></td>
                        <td>
                            The device reports no <code>ifAlias</code> and the stored value matches neither
                            <code>ifDescr</code> nor <code>ifName</code>: its origin cannot be established.
                        </td>
                        <td>Unknown</td>
                        <td>&mdash;</td>
                    </tr>
                    <tr>
                        <td><code>db only</code></td>
                        <td>The device was not polled, so the stored value is reported alone.</td>
                        <td>&mdash;</td>
                        <td>&mdash;</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p>
            The <code>override</code> marker is a <code>devices_attribs</code> entry named
            <code>ifName:&lt;ifName&gt;</code>, which is exactly what the pencil icon in the web ui writes.
            The automatic fallback looks like an override in the ui but is not one: that confusion is the
            whole reason this report exists.
        </p>

        <h4 id="ifalias-manual-reading">{{ __('ifalias::settings.manual.reading') }}</h4>

        <p>
            One block per device, then a row per port:
            <code>ifIndex</code>, <code>ifName</code>, the stored value (<code>ports.ifAlias</code>), the
            value the device reports (<code>IF-MIB::ifAlias</code>), the <code>source</code> and the
            <code>status</code>. A row is followed by a dim note when something needs explaining, for
            instance that the next poll will overwrite a value nobody protected.
        </p>

        <p>{{ __('ifalias::settings.settings.colors_help') }}</p>

        <div class="table-responsive">
            <table class="table table-condensed ifalias-table">
                <thead>
                    <tr><th>Colour</th><th>Row</th></tr>
                </thead>
                <tbody>
                    <tr><td><span class="ifalias-green">green</span></td><td>the stored value is what the device reports</td></tr>
                    <tr><td><span class="ifalias-yellow">yellow</span></td><td>a user override, the value survives every poll</td></tr>
                    <tr><td><span class="ifalias-blue">blue</span></td><td>the automatic fallback, nobody asked for this value</td></tr>
                    <tr><td><span class="ifalias-red">red</span></td><td>differs from the device and no override is set</td></tr>
                    <tr><td><strong>bold</strong></td><td>the device and table headers</td></tr>
                    <tr><td><span class="ifalias-dim">dim</span></td><td>the notes under a row</td></tr>
                </tbody>
            </table>
        </div>

        <p>
            The counters at the end always cover every port examined, not only the printed ones, so the
            summary does not change depending on the filters used.
        </p>

        <h4 id="ifalias-manual-web">{{ __('ifalias::settings.manual.web') }}</h4>

        <p>
            Everything the command does is available above, on this page: pick a device spec, tick what you
            want to see and press <strong>{{ __('ifalias::settings.run.submit') }}</strong>. The report shows
            up in the text window under the form, where you can scroll it, look for a string in it, copy it,
            switch long line wrapping off and read it with or without colours.
        </p>

        <ul>
            <li>The run is a plain <code>GET</code>: the address bar holds the whole run, so it can be reloaded, bookmarked and sent to a colleague.</li>
            <li>Asking the devices walks <code>ifAlias</code> and <code>ifDescr</code> on every selected device. On a large install that takes a while: untick it for a database only report, narrow the device spec, or cap <em>{{ __('ifalias::settings.settings.max_devices') }}</em>.</li>
            <li>The report is read only. Nothing is polled on a schedule, nothing is written, no device configuration is touched.</li>
            <li>The page is behind the <code>plugin.admin</code> permission, the same one that guards the plugin list.</li>
        </ul>

        <h4 id="ifalias-manual-settings">{{ __('ifalias::settings.manual.settings_ref') }}</h4>

        <p>
            The settings below are the defaults of both this form and of <code>lnms port:ifAlias</code>. They
            live in the database (the <code>plugins</code> table) and survive a LibreNMS update, so they are
            not touched by <code>daily.sh</code>. A field left empty keeps the value from
            <code>config/ifalias.php</code>, which you can publish with:
        </p>

        <pre class="ifalias-code"><code>php artisan vendor:publish --provider="Dercol1\LibrenmsIfAlias\IfAliasPluginProvider" --tag=config</code></pre>

        <div class="table-responsive">
            <table class="table table-condensed ifalias-table">
                <thead>
                    <tr>
                        <th>Setting</th>
                        <th>Default</th>
                        <th>What it does</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>{{ __('ifalias::settings.settings.device_spec') }}</code></td>
                        <td><code>all</code></td>
                        <td>{!! __('ifalias::settings.settings.device_spec_help') !!}</td>
                    </tr>
                    <tr>
                        <td><code>{{ __('ifalias::settings.settings.snmp') }}</code></td>
                        <td>on</td>
                        <td>{!! __('ifalias::settings.settings.snmp_help') !!}</td>
                    </tr>
                    <tr>
                        <td><code>{{ __('ifalias::settings.settings.diff_only') }}</code></td>
                        <td>off</td>
                        <td>{!! __('ifalias::settings.settings.diff_only_help') !!}</td>
                    </tr>
                    <tr>
                        <td><code>{{ __('ifalias::settings.settings.override_only') }}</code></td>
                        <td>off</td>
                        <td>{!! __('ifalias::settings.settings.override_only_help') !!}</td>
                    </tr>
                    <tr>
                        <td><code>{{ __('ifalias::settings.settings.include_inactive') }}</code></td>
                        <td>off</td>
                        <td>{!! __('ifalias::settings.settings.include_inactive_help') !!}</td>
                    </tr>
                    <tr>
                        <td><code>{{ __('ifalias::settings.settings.colors') }}</code></td>
                        <td>on</td>
                        <td>{!! __('ifalias::settings.settings.colors_help') !!}</td>
                    </tr>
                    <tr>
                        <td><code>{{ __('ifalias::settings.settings.max_devices') }}</code></td>
                        <td><code>0</code> (no limit)</td>
                        <td>{!! __('ifalias::settings.settings.max_devices_help') !!}</td>
                    </tr>
                    <tr>
                        <td><code>{{ __('ifalias::settings.settings.lines') }}</code></td>
                        <td><code>30</code></td>
                        <td>{!! __('ifalias::settings.settings.lines_help') !!}</td>
                    </tr>
                    <tr>
                        <td><code>{{ __('ifalias::settings.settings.pager') }}</code></td>
                        <td>auto detected</td>
                        <td>{!! __('ifalias::settings.settings.pager_help') !!}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <h4 id="ifalias-manual-cli">{{ __('ifalias::settings.manual.cli') }}</h4>

        <p>
            Same report from a terminal, for when you want it in a pipe or over ssh. Every option defaults
            to the setting above and can be flipped per run with its <code>--no-</code> twin:
        </p>

        <pre class="ifalias-code"><code>lnms port:ifAlias all                      # every active device
lnms port:ifAlias router01                 # one device
lnms port:ifAlias 'core*'                  # a group
lnms port:ifAlias all --override-only      # only the ports a user overrode
lnms port:ifAlias all --diff               # only the ones differing from the device
lnms port:ifAlias router01 --no-snmp       # database only, no device contact
lnms port:ifAlias router01 --inactive      # include deleted and disabled ports
lnms port:ifAlias all --max-devices=50     # stop after 50 devices
lnms port:ifAlias all --no-diff --no-inactive   # override the settings</code></pre>

        <div class="table-responsive">
            <table class="table table-condensed ifalias-table">
                <thead>
                    <tr><th>Option</th><th>Meaning</th></tr>
                </thead>
                <tbody>
                    <tr><td><code>[device spec]</code></td><td>{{ __('ifalias::command.arguments.device spec') }}</td></tr>
                    <tr><td><code>-d</code>, <code>--diff</code> / <code>--no-diff</code></td><td>{{ __('ifalias::command.options.diff') }}</td></tr>
                    <tr><td><code>--override-only</code> / <code>--no-override-only</code></td><td>{{ __('ifalias::command.options.override-only') }}</td></tr>
                    <tr><td><code>-o</code>, <code>--inactive</code> / <code>--no-inactive</code></td><td>{{ __('ifalias::command.options.inactive') }}</td></tr>
                    <tr><td><code>--snmp</code> / <code>--no-snmp</code></td><td>{{ __('ifalias::command.options.snmp') }}</td></tr>
                    <tr><td><code>--max-devices=</code></td><td>{{ __('ifalias::command.options.max-devices') }}</td></tr>
                    <tr><td><code>--pager=</code></td><td>{{ __('ifalias::command.options.pager') }}</td></tr>
                    <tr><td><code>--lines=</code></td><td>{{ __('ifalias::command.options.lines') }}</td></tr>
                    <tr><td><code>--ansi</code> / <code>--no-ansi</code></td><td>force colours on or off, whatever the terminal says</td></tr>
                </tbody>
            </table>
        </div>

        <p>
            On a terminal the report is streamed into <code>{{ __('ifalias::command.default_pager') }}</code>,
            so colours are kept, long lines are chopped instead of folded and a page break always lands
            between two ports. Search with <code>/</code>, move with <code>space</code>, <code>b</code>,
            <code>g</code>, <code>G</code>, quit with <code>q</code>. Redirecting the output turns paging off
            by itself:
        </p>

        <pre class="ifalias-code"><code>lnms port:ifAlias all &gt; ifalias.txt          # no paging, whole report captured
IFALIAS_NO_PAGER=1 lnms port:ifAlias all     # straight to the terminal scrollback
IFALIAS_PAGER='most -R' lnms port:ifAlias all
lnms port:ifAlias all --pager=cat            # no pager at all</code></pre>

        <h4 id="ifalias-manual-limits">{{ __('ifalias::settings.manual.limits') }}</h4>

        <ul>
            <li>
                The report is a snapshot of what is stored right now. It never polls: a value that is about to
                be overwritten by the next discovery is reported as it is, not as it will be.
            </li>
            <li>
                Without a device walk the stored value can only be compared against nothing, so every port is
                reported as <code>db only</code> and the counters stay at zero. That is on purpose: it says
                "this was not verified", not "this is fine".
            </li>
            <li>
                An unreachable device does not stop the report, the two columns that need the device are
                simply reported as unavailable. The other devices are still reported.
            </li>
            <li>
                Only active devices are considered, a device that has left LibreNMS has nothing to report.
            </li>
            <li>
                The exit code is <code>0</code> when at least one device matched, <code>1</code> when the
                device spec matched nothing.
            </li>
            <li>
                Asking the devices needs the LibreNMS snmp credentials to be able to read
                <code>IF-MIB::ifAlias</code> and <code>IF-MIB::ifDescr</code> on them. A device that does
                not implement them is reported as reporting no <code>ifAlias</code>, which is not the same
                as reporting an empty one.
            </li>
        </ul>
    </div>
</div>

@push('styles')
    <style>
        #ifalias-manual h4 { margin-top: 20px; padding-bottom: 4px; border-bottom: 1px solid #eee; }
        #ifalias-manual .ifalias-toc { font-size: 13px; }
        #ifalias-manual .ifalias-table { margin-bottom: 10px; font-size: 13px; }
        #ifalias-manual .ifalias-table th { width: 20%; }
        #ifalias-manual pre.ifalias-code {
            background-color: #f7f7f9;
            border: 1px solid #dfe0e5;
            border-radius: 3px;
            font-size: 12px;
        }
        #ifalias-manual .ifalias-yellow-row td:first-child { border-left: 3px solid #9a6700; }
        #ifalias-manual .ifalias-green-row td:first-child { border-left: 3px solid #1a7f37; }
        #ifalias-manual .ifalias-blue-row td:first-child { border-left: 3px solid #0969da; }
        #ifalias-manual .ifalias-red-row td:first-child { border-left: 3px solid #cf222e; }
        html.dark #ifalias-manual h4 { border-bottom-color: #30363d; }
        html.dark #ifalias-manual pre.ifalias-code { background-color: #161b22; border-color: #30363d; }
        html.dark #ifalias-manual .ifalias-yellow-row td:first-child { border-left-color: #d29922; }
        html.dark #ifalias-manual .ifalias-green-row td:first-child { border-left-color: #57ab5a; }
        html.dark #ifalias-manual .ifalias-blue-row td:first-child { border-left-color: #539bf5; }
        html.dark #ifalias-manual .ifalias-red-row td:first-child { border-left-color: #f85149; }
    </style>
@endpush
