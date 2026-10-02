<?php

namespace Dercol1\LibrenmsIfAlias\Web;

use App\Models\Plugin;
use Dercol1\LibrenmsIfAlias\IfAliasPluginProvider;
use Dercol1\LibrenmsIfAlias\Report\ReportOptions;
use Dercol1\LibrenmsIfAlias\Report\ReportResult;
use Dercol1\LibrenmsIfAlias\Settings\PluginSettings;

/**
 * The view data of the plugin settings page.
 *
 * LibreNMS renders the page through its own controller and its own view, which
 * only include the plugin view with whatever data the hook returned. The run
 * route needs the very same page, this is what both build it from, so the page
 * looks the same whether it was reached from the Settings button or from a run.
 */
final class SettingsPage
{
    /**
     * @param  array<string, mixed>  $stored  the settings as saved in the database
     * @param  array<string, bool|int|string>|null  $run  the submitted run form, null to show the saved defaults
     * @param  array{result: ReportResult, html: string, seconds: float}|null  $report  null when nothing was run yet
     * @return array<string, mixed>
     */
    public static function data(array $stored, ?array $run = null, ?array $report = null): array
    {
        $plugin = IfAliasPluginProvider::PLUGIN_NAME;
        $settings = PluginSettings::fromArray($stored);
        $model = Plugin::where('plugin_name', $plugin)->first();

        return [
            // the keys core expects, see PluginSettingsController
            'title' => trans('plugins.settings_page', ['plugin' => $plugin]),
            'plugin_name' => $plugin,
            'plugin_id' => $model?->plugin_id,
            // core validates plugin_active on every settings save, so the form
            // has to carry the value already stored
            'plugin_active' => $model === null ? 1 : (int) $model->plugin_active,
            'content_view' => "$plugin::settings",
            'settings' => $stored,

            // resolved values, keyed like the settings form fields
            'defaults' => [
                'device_spec' => $settings->deviceSpec(),
                'lines' => $settings->lines(),
                'max_devices' => $settings->maxDevices(),
                'pager' => $settings->pager() ?? '',
                'snmp' => $settings->snmp(),
                'diff_only' => $settings->diffOnly(),
                'override_only' => $settings->overrideOnly(),
                'include_inactive' => $settings->includeInactive(),
                'colors' => $settings->colors(),
            ],

            // the run form, keyed like its own fields
            'run' => $run ?? ReportOptions::fromSettings($settings)->toInput(),
            'report' => $report,
        ];
    }
}
