<?php

namespace Dercol1\LibrenmsIfAlias;

use Dercol1\LibrenmsIfAlias\Console\PortIfAliasCommand;
use Dercol1\LibrenmsIfAlias\Hooks\Settings;
use Dercol1\LibrenmsIfAlias\Web\RunReportController;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use LibreNMS\Interfaces\Plugins\Hooks\SettingsHook;
use LibreNMS\Interfaces\Plugins\PluginManagerInterface;

class IfAliasPluginProvider extends ServiceProvider
{
    /** Keep in sync with the librenms plugin name and the view namespace. */
    public const PLUGIN_NAME = 'ifalias';

    /** The gate the plugin pages in routes/web.php are behind. */
    private const PERMISSION = 'plugin.admin';

    public function register(): void
    {
        // strings live in the plugin, the core lang folder is not ours to edit
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', self::PLUGIN_NAME);

        // the defaults must exist even when config/ifalias.php was never
        // published, otherwise every default would be null on a fresh install
        $this->mergeConfigFrom(__DIR__ . '/../config/ifalias.php', self::PLUGIN_NAME);
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

        $this->registerRoutes();

        $this->commands([PortIfAliasCommand::class]);

        AboutCommand::add('ifAlias plugin', fn (): array => [
            'Version' => self::version(),
        ]);
    }

    /**
     * The route that runs the report from the settings page.
     *
     * The settings button of the plugin admin page is served by core, so this is
     * the one url the plugin adds on its own. It sits behind the same auth and
     * gate as the settings page it renders.
     *
     * Nothing is registered when the routes are cached: the cached collection
     * already holds them (it was built with this provider booted) and adding to
     * a compiled collection throws. Re-run route:cache after an update, which
     * daily.sh does anyway, since it starts with optimize:clear.
     */
    private function registerRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        Route::middleware(['web', 'auth', 'can:' . self::PERMISSION])
            ->prefix('plugin/' . self::PLUGIN_NAME)
            ->name(self::PLUGIN_NAME . '.')
            ->group(function (): void {
                Route::get('run', RunReportController::class)->name('run');
            });
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
