<?php

namespace Dercol1\LibrenmsIfAlias;

use Dercol1\LibrenmsIfAlias\Console\PortIfAliasCommand;
use Dercol1\LibrenmsIfAlias\Hooks\Settings;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\ServiceProvider;
use LibreNMS\Interfaces\Plugins\Hooks\SettingsHook;
use LibreNMS\Interfaces\Plugins\PluginManagerInterface;

class IfAliasPluginProvider extends ServiceProvider
{
    /** Keep in sync with the librenms plugin name and the view namespace. */
    public const PLUGIN_NAME = 'ifalias';

    public function register(): void
    {
        // strings live in the plugin, the core lang folder is not ours to edit
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', self::PLUGIN_NAME);
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(PluginManagerInterface $pluginManager): void
    {
        // a settings hook keeps the plugin listed in the ui. Without any hook
        // LibreNMS considers the plugin to have nothing to show and may drop it
        $pluginManager->publishHook(self::PLUGIN_NAME, SettingsHook::class, Settings::class);

        if (! $pluginManager->pluginEnabled(self::PLUGIN_NAME)) {
            return; // do nothing but keep the hook while the plugin is disabled
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', self::PLUGIN_NAME);

        $this->publishes([
            __DIR__ . '/../config/ifalias.php' => config_path('ifalias.php'),
        ], 'config');

        $this->commands([PortIfAliasCommand::class]);

        AboutCommand::add('ifAlias plugin', fn (): array => [
            'Version' => self::version(),
        ]);
    }

    private static function version(): string
    {
        $composer = __DIR__ . '/../composer.json';

        if (! is_file($composer)) {
            return 'unknown';
        }

        $decoded = json_decode((string) file_get_contents($composer), true);

        return is_array($decoded) ? (string) ($decoded['version'] ?? 'dev') : 'dev';
    }
}
