<?php

namespace Dercol1\LibrenmsIfAlias\Hooks;

use Dercol1\LibrenmsIfAlias\Web\SettingsPage;
use Illuminate\Support\Facades\Gate;
use LibreNMS\Interfaces\Plugins\Hooks\SettingsHook;

/**
 * The plugin settings page: what the report looks for, and a way to run it
 * without touching the terminal.
 *
 * The hook itself is also what keeps the plugin listed in the ui, LibreNMS
 * considers a plugin with no hooks to have nothing to show and drops it.
 */
class Settings implements SettingsHook
{
    /**
     * The gate behind the plugin pages, see the can:plugin.admin group in
     * routes/web.php.
     *
     * The user instance is not used on purpose: the plugin manager injects an
     * empty App\Models\User, not the signed in one, so asking that instance
     * what it may do answers no for everybody. The gate resolves the real user
     * from the session itself.
     */
    public function authorize(): bool
    {
        return Gate::allows('plugin.admin');
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function handle(string $pluginName, array $settings): array
    {
        return SettingsPage::data($settings);
    }
}
