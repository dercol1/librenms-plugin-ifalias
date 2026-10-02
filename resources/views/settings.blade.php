{{--
    Plugin settings page.

    Core renders it: PluginSettingsController -> plugins.settings, which
    includes this view. It therefore must be a fragment, no @extends here, or
    the page would end up with one html document inside the other.
--}}
@use('Illuminate\Support\Facades\Route')

{{--
    The run route is added by the plugin, but routes may be cached from before it
    was installed. Asking for the route name anyway would take the whole page
    down, so fall back to the plain url and let the form answer with a 404 that
    at least tells what happened.
--}}
@php($run_url = Route::has('ifalias.run') ? route('ifalias.run') : url('plugin/ifalias/run'))

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">

            <div class="panel panel-default">
                <div class="panel-heading">
                    <strong>{{ __('ifalias::settings.title') }}</strong>
                </div>
                <div class="panel-body">
                    <p>{{ __('ifalias::settings.intro') }}</p>
                    <p class="text-muted">
                        <code>override</code> &mdash; a user set it, it survives every poll
                        &nbsp;&middot;&nbsp;
                        <code>device</code> &mdash; the device reports IF-MIB::ifAlias
                        &nbsp;&middot;&nbsp;
                        <code>fill ifDescr</code> / <code>fill ifName</code> &mdash; LibreNMS copied another field
                        because the device reports no ifAlias
                    </p>
                    <p>
                        <a href="#ifalias-run" class="btn btn-primary btn-sm">{{ __('ifalias::settings.run.panel') }}</a>
                        <a href="#ifalias-manual" class="btn btn-default btn-sm">{{ __('ifalias::settings.manual.panel') }}</a>
                    </p>
                </div>
            </div>

            <div class="panel panel-default">
                <div class="panel-heading">
                    <strong>{{ __('ifalias::settings.settings.panel') }}</strong>
                </div>
                <div class="panel-body">
                    <p>{!! __('ifalias::settings.settings.intro') !!}</p>

                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="post" action="{{ route('plugin.update', ['plugin' => $plugin_name]) }}">
                        @csrf
                        {{-- core validates plugin_active on every settings save --}}
                        <input type="hidden" name="plugin_active" value="{{ $plugin_active }}">

                        @foreach ([
                            'device_spec' => ['type' => 'text', 'label' => 'device_spec', 'max' => 255],
                            'lines' => ['type' => 'number', 'label' => 'lines', 'min' => 1, 'max' => 1000],
                            'max_devices' => ['type' => 'number', 'label' => 'max_devices', 'min' => 0, 'max' => 100000],
                            'pager' => ['type' => 'text', 'label' => 'pager', 'max' => 255],
                        ] as $key => $field)
                            <div class="form-group">
                                <label for="ifalias-setting-{{ $key }}">{{ __('ifalias::settings.settings.' . $field['label']) }}</label>
                                <input type="{{ $field['type'] }}" class="form-control" id="ifalias-setting-{{ $key }}"
                                       name="settings[{{ $key }}]" value="{{ $settings[$key] ?? '' }}"
                                       placeholder="{{ $defaults[$key] }}"
                                       @if (isset($field['min'])) min="{{ $field['min'] }}" @endif
                                       @if (isset($field['max'])) max="{{ $field['max'] }}" @endif>
                                <p class="help-block">{!! __('ifalias::settings.settings.' . $field['label'] . '_help') !!}</p>
                            </div>
                        @endforeach

                        @foreach (['snmp', 'diff_only', 'override_only', 'include_inactive', 'colors'] as $key)
                            <div class="checkbox">
                                <label>
                                    {{-- an unchecked checkbox sends nothing, the hidden field says "off" --}}
                                    <input type="hidden" name="settings[{{ $key }}]" value="0">
                                    <input type="checkbox" name="settings[{{ $key }}]" value="1" @checked((bool) $defaults[$key])>
                                    {{ __('ifalias::settings.settings.' . $key) }}
                                </label>
                                <p class="help-block">{!! __('ifalias::settings.settings.' . $key . '_help') !!}</p>
                            </div>
                        @endforeach

                        <button type="submit" class="btn btn-primary">{{ __('ifalias::settings.settings.save') }}</button>
                    </form>
                </div>
            </div>

            <div class="panel panel-default" id="ifalias-run">
                <div class="panel-heading">
                    <strong>{{ __('ifalias::settings.run.panel') }}</strong>
                </div>
                <div class="panel-body">
                    <p>{!! __('ifalias::settings.run.intro') !!}</p>

                    {{-- a plain GET: the query string is the run, so the result can be reloaded and linked --}}
                    <form method="get" action="{{ $run_url }}" class="form-inline">
                        <div class="form-group">
                            <label class="sr-only" for="ifalias-run-device-spec">{{ __('ifalias::settings.run.device_spec') }}</label>
                            <input type="text" class="form-control" id="ifalias-run-device-spec" name="device_spec"
                                   value="{{ $run['device_spec'] }}" placeholder="all">
                        </div>

                        <div class="form-group">
                            <label class="sr-only" for="ifalias-run-max-devices">{{ __('ifalias::settings.run.max_devices') }}</label>
                            <input type="number" class="form-control" id="ifalias-run-max-devices" name="max_devices"
                                   min="0" max="100000" value="{{ $run['max_devices'] }}" style="width: 7em">
                        </div>

                        @foreach (['snmp', 'diff', 'override_only', 'inactive', 'colors'] as $key)
                            <div class="checkbox">
                                <label>
                                    <input type="hidden" name="{{ $key }}" value="0">
                                    <input type="checkbox" name="{{ $key }}" value="1" @checked((bool) $run[$key])>
                                    {{ __('ifalias::settings.run.' . $key) }}
                                </label>
                            </div>
                        @endforeach

                        <button type="submit" name="run" value="1" class="btn btn-primary">
                            {{ __('ifalias::settings.run.submit') }}
                        </button>
                    </form>

                    @if ($report === null)
                        <p class="text-muted">{{ __('ifalias::settings.run.idle') }}</p>
                    @else
                        <div id="ifalias-output" class="ifalias-output">
                            <div class="ifalias-output-bar">
                                <span class="text-muted">
                                    {{ __('ifalias::settings.run.summary', [
                                        'devices' => $report['result']->devices,
                                        'ports' => $report['result']->ports,
                                        'seconds' => number_format($report['seconds'], 2),
                                    ]) }}
                                </span>
                                <span class="ifalias-output-tools">
                                    <input type="search" id="ifalias-filter" class="form-control input-sm"
                                           placeholder="{{ __('ifalias::settings.run.filter') }}"
                                           aria-label="{{ __('ifalias::settings.run.filter') }}">
                                    <label class="ifalias-tool">
                                        <input type="checkbox" id="ifalias-wrap" checked>
                                        {{ __('ifalias::settings.run.wrap') }}
                                    </label>
                                    <button type="button" class="btn btn-default btn-xs" id="ifalias-select">
                                        {{ __('ifalias::settings.run.copy') }}
                                    </button>
                                </span>
                            </div>
                            {{-- the report is escaped and coloured by AnsiToHtml before it gets here --}}
                            <pre id="ifalias-text" class="ifalias-text">{!! $report['html'] !!}</pre>
                            <p id="ifalias-filter-empty" class="text-muted" style="display: none">
                                {{ __('ifalias::settings.run.filter_none') }}
                            </p>
                        </div>
                    @endif
                </div>
            </div>

            @include('ifalias::manual')
        </div>
    </div>
</div>

@push('styles')
    <style>
        #ifalias-output { margin-top: 15px; }
        #ifalias-output .ifalias-output-bar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between; margin-bottom: 5px; }
        #ifalias-output .ifalias-output-tools { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
        #ifalias-output .ifalias-output-tools #ifalias-filter { width: 14em; display: inline-block; }
        #ifalias-output .ifalias-tool { font-weight: normal; margin: 0; }
        pre.ifalias-text {
            margin: 0;
            padding: 10px;
            max-height: 60vh;
            overflow: auto;
            background-color: #f7f7f9;
            border: 1px solid #dfe0e5;
            border-radius: 3px;
            font-size: 12px;
            line-height: 1.45;
            white-space: pre-wrap;
            word-break: break-word;
            tab-size: 4;
        }
        pre.ifalias-text.ifalias-nowrap { white-space: pre; word-break: normal; }
        html.dark pre.ifalias-text { background-color: #161b22; border-color: #30363d; }
        .ifalias-bold { font-weight: bold; }
        .ifalias-dim { opacity: .65; }
        .ifalias-green { color: #1a7f37; }
        .ifalias-yellow { color: #9a6700; }
        .ifalias-blue { color: #0969da; }
        .ifalias-red { color: #cf222e; }
        html.dark .ifalias-green { color: #57ab5a; }
        html.dark .ifalias-yellow { color: #d29922; }
        html.dark .ifalias-blue { color: #539bf5; }
        html.dark .ifalias-red { color: #f85149; }
    </style>
@endpush

@push('scripts')
    <script>
        (function () {
            var output = document.getElementById('ifalias-output');
            if (!output) {
                return;
            }

            var text = document.getElementById('ifalias-text');
            var filter = document.getElementById('ifalias-filter');
            var wrap = document.getElementById('ifalias-wrap');
            var select = document.getElementById('ifalias-select');
            var empty = document.getElementById('ifalias-filter-empty');
            var lines = text.innerHTML.split('\n');

            if (filter) {
                filter.addEventListener('input', function () {
                    var needle = filter.value.toLowerCase();
                    var kept = [];

                    lines.forEach(function (line) {
                        // compare what the reader sees, keep the markup so the colours survive
                        var probe = document.createElement('span');
                        probe.innerHTML = line;

                        if (needle === '' || probe.textContent.toLowerCase().indexOf(needle) !== -1) {
                            kept.push(line);
                        }
                    });

                    text.innerHTML = kept.join('\n');
                    empty.style.display = kept.length ? 'none' : 'block';
                });
            }

            if (wrap) {
                wrap.addEventListener('change', function () {
                    text.classList.toggle('ifalias-nowrap', !wrap.checked);
                });
            }

            if (select) {
                select.addEventListener('click', function () {
                    var range = document.createRange();
                    range.selectNodeContents(text);

                    var selection = window.getSelection();
                    selection.removeAllRanges();
                    selection.addRange(range);
                });
            }

            output.scrollIntoView({behavior: 'smooth', block: 'nearest'});
        })();
    </script>
@endpush
