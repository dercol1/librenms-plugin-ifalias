@extends('layouts.librenmsv1')

@section('title', __('ifalias::command.description'))

@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <strong>lnms port:ifAlias</strong>
                </div>
                <div class="panel-body">
                    <p>
                        This plugin adds the <code>port:ifAlias</code> command. It reports where the
                        <code>ifAlias</code> stored for each port comes from:
                    </p>
                    <ul>
                        <li><code>override</code> &mdash; a user set it, it survives every poll</li>
                        <li><code>device</code> &mdash; the device reports IF-MIB::ifAlias</li>
                        <li><code>fill ifDescr</code> / <code>fill ifName</code> &mdash; LibreNMS copied
                            another field because the device reports no ifAlias</li>
                    </ul>
                    <p>The command is read only: it never polls and never writes.</p>
                    <pre><code>lnms port:ifAlias all
lnms port:ifAlias router01 --override-only
lnms port:ifAlias 'core*' --diff
lnms port:ifAlias router01 --no-snmp</code></pre>
                </div>
            </div>
        </div>
    </div>
@endsection
