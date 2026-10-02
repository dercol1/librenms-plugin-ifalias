<?php

namespace Dercol1\LibrenmsIfAlias\Web;

use Dercol1\LibrenmsIfAlias\IfAliasPluginProvider;
use Dercol1\LibrenmsIfAlias\Report\BufferSink;
use Dercol1\LibrenmsIfAlias\Report\IfAliasReport;
use Dercol1\LibrenmsIfAlias\Report\ReportOptions;
use Dercol1\LibrenmsIfAlias\Settings\PluginSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use LibreNMS\Interfaces\Plugins\PluginManagerInterface;

/**
 * Run the report and show it in the text window of the settings page.
 *
 * A plain GET, no form state kept on the server: the query string is the run,
 * so the result can be bookmarked, reloaded and linked. The report is read only,
 * it never polls and never writes.
 */
final class RunReportController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(Request $request, PluginManagerInterface $manager): View
    {
        // the same gate the settings page itself is behind, see web.php
        $this->authorize('plugin.admin');

        $stored = $manager->getSettings(IfAliasPluginProvider::PLUGIN_NAME);
        $settings = PluginSettings::fromArray($stored);

        // the form always sends its fields, so anything else is a bare url: show
        // the page with the saved defaults and no output
        if (! $request->has('run')) {
            return view('plugins.settings', SettingsPage::data($stored));
        }

        $input = $this->validated($request);
        $options = ReportOptions::fromInput($input, $settings);
        $started = microtime(true);

        $sink = new BufferSink;
        $result = (new IfAliasReport($options))->render($sink, $this->note($options));

        return view('plugins.settings', SettingsPage::data(
            $stored,
            $options->toInput(),
            [
                'result' => $result,
                'html' => $options->colors ? AnsiToHtml::convert($sink->text()) : e($sink->text()),
                'seconds' => microtime(true) - $started,
            ]
        ));
    }

    /**
     * The fields the run form sends. Booleans are 0 or 1, because an unchecked
     * checkbox needs the hidden field next to it to say anything at all.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'run' => ['in:0,1'],
            'device_spec' => ['nullable', 'string', 'max:255'],
            'snmp' => ['in:0,1'],
            'diff' => ['in:0,1'],
            'override_only' => ['in:0,1'],
            'inactive' => ['in:0,1'],
            'colors' => ['in:0,1'],
            'max_devices' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);
    }

    /** The line under the header, so a copied report says where it comes from. */
    private function note(ReportOptions $options): string
    {
        return __('ifalias::settings.note', [
            'date' => now()->format('Y-m-d H:i'),
            'device' => $options->deviceSpec,
            'snmp' => $options->snmp
                ? __('ifalias::settings.snmp_on')
                : __('ifalias::settings.snmp_off'),
        ]);
    }
}
