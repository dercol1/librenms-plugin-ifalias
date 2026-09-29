<?php

namespace Dercol1\LibrenmsIfAlias\Hooks;

use Illuminate\Foundation\Auth\User;
use LibreNMS\Interfaces\Plugins\Hooks\SettingsHook;

/**
 * The plugin only adds a command, so the settings page just explains how to
 * use it. The hook is still needed: it keeps the plugin visible in the ui.
 */
class Settings implements SettingsHook
{
    public function authorize(User $user): bool
    {
        // read only and harmless, but still an administrative tool
        return $user->can('admin');
    }

    /**
     * @param  array<string, array<string, mixed>>  $settings
     * @return array<string, mixed>
     */
    public function handle(string $pluginName, array $settings): array
    {
        return [
            'content_view' => "$pluginName::settings",
            'settings' => $settings,
        ];
    }
}
