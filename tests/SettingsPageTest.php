<?php

/**
 * SettingsPageTest.php
 *
 * The plugin settings page: the settings form, the manual and the report run
 * from the browser. The hook is what core renders the page with, so these are
 * end to end tests: a request to the core route, the html that comes back.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * @link       https://www.librenms.org
 *
 * @copyright  2026 LibreNMS
 */

namespace Dercol1\LibrenmsIfAlias\Tests\Feature;

use App\Models\Device;
use App\Models\Plugin;
use App\Models\Port;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Artisan as ArtisanFacade;
use LibreNMS\Data\Source\Snmp\SnmpQueryInterface;
use LibreNMS\Data\Source\Snmp\SnmpResponse;
use LibreNMS\Interfaces\Plugins\PluginManagerInterface;
use LibreNMS\Tests\InMemoryDbTestCase;
use Mockery;
use ReflectionProperty;
use Spatie\Permission\Models\Role;

final class SettingsPageTest extends InMemoryDbTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // an installed and enabled plugin: the row lives in the database, the
        // provider only creates it on a real install
        Plugin::query()->updateOrCreate(
            ['plugin_name' => 'ifalias'],
            ['plugin_active' => 1, 'version' => 2]
        );

        $this->forgetCachedPlugins();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * An admin must find the settings form and the manual on the page core
     * renders for the plugin.
     */
    public function test_settings_page_shows_the_form_and_the_manual(): void
    {
        $response = $this->actingAs($this->admin())->get(route('plugin.settings', 'ifalias'));

        $response->assertOk();
        $response->assertSee('name="settings[device_spec]"', escape: false);
        $response->assertSee('name="settings[snmp]"', escape: false);
        $response->assertSee(route('ifalias.run'), escape: false);
        // the manual is part of the page, not a link somewhere else
        $response->assertSee('id="ifalias-manual"', escape: false);
        $response->assertSee(__('ifalias::settings.manual.summary'), escape: false);
        $response->assertSee(__('ifalias::command.default_pager'), escape: false);
    }

    /**
     * The page must not be a second html document inside the first one: core
     * includes the plugin view, so it has to be a fragment.
     */
    public function test_settings_page_is_rendered_inside_the_layout(): void
    {
        $html = $this->actingAs($this->admin())->get(route('plugin.settings', 'ifalias'))->getContent();

        $this->assertIsString($html);
        $this->assertSame(1, substr_count((string) $html, '<!DOCTYPE HTML>'));
        $this->assertStringContainsString('id="ifalias-run"', (string) $html);
    }

    /**
     * The page is behind the same gate as the plugin list, and the hook must not
     * answer differently from the controller in front of it.
     */
    public function test_settings_page_is_closed_to_a_user_without_the_permission(): void
    {
        Role::findOrCreate('user');
        $user = User::factory()->create(['enabled' => 1]);

        $this->actingAs($user)->get(route('plugin.settings', 'ifalias'))->assertForbidden();
        $this->actingAs($user)->get(route('ifalias.run', ['run' => 1]))->assertForbidden();
    }

    public function test_guest_is_sent_to_the_login_page(): void
    {
        $this->get(route('ifalias.run', ['run' => 1]))->assertRedirect('/login');
    }

    /**
     * The settings form saves through the core route, so the values must land in
     * the plugins table and nowhere else.
     */
    public function test_settings_form_saves_through_the_core_route(): void
    {
        // strings, exactly what a browser posts for these fields
        $this->actingAs($this->admin())->post(route('plugin.update', ['plugin' => 'ifalias']), [
            'plugin_active' => 1,
            'settings' => [
                'device_spec' => 'core*',
                'snmp' => '0',
                'override_only' => '1',
                'lines' => '12',
            ],
        ])->assertRedirect();

        $plugin = Plugin::where('plugin_name', 'ifalias')->firstOrFail();

        // getAttribute() hands over the casted array, whatever the model phpdoc says
        $stored = $plugin->getAttribute('settings');

        $this->assertIsArray($stored);
        $this->assertSame('core*', $stored['device_spec'] ?? null);
        $this->assertSame('0', $stored['snmp'] ?? null, 'an unchecked checkbox arrives as 0');
        $this->assertSame('1', $stored['override_only'] ?? null);
        $this->assertSame(1, (int) $plugin->value('plugin_active'));
    }

    /**
     * What is stored must become the default of the command, otherwise the
     * settings would only be decoration on a page.
     */
    public function test_stored_settings_are_the_defaults_of_the_command(): void
    {
        $device = Device::factory()->create(['os' => 'generic']);
        $this->port($device, 1, 'Gi0/1', 'transit: provider [10Gbps]');

        // the same path the settings form uses, PluginSettings::current() reads it
        $this->storeSettings([
            'device_spec' => $device->hostname,
            'snmp' => 0,
            'override_only' => 0,
        ]);
        $this->reconfigureCommand();

        Artisan::call('port:ifAlias');
        $output = Artisan::output();

        // snmp is off in the settings, so the report can only come from the database
        $this->assertStringContainsString('db only', $output);
        $this->assertStringContainsString($device->hostname, $output);
    }

    /**
     * A single option on the command line must still win over the setting.
     */
    public function test_the_command_line_wins_over_the_settings(): void
    {
        $device = Device::factory()->create(['os' => 'generic']);
        $this->port($device, 1, 'Gi0/1', 'transit: provider [10Gbps]');

        $this->storeSettings(['device_spec' => $device->hostname, 'snmp' => 0]);
        $this->reconfigureCommand();
        $this->fakeSnmp([1 => 'transit: provider [10Gbps]']);

        Artisan::call('port:ifAlias', ['--snmp' => true]);
        $output = Artisan::output();

        // the device was reached, so the row has a source and a comparison
        $this->assertStringContainsString('device        same', $output);
        $this->assertStringContainsString('Stored ifAlias equals the device value: 1', $output);
    }

    /**
     * The settings form must show what was stored, not what the config file says.
     */
    public function test_stored_settings_come_back_in_the_form(): void
    {
        $this->storeSettings([
            'device_spec' => 'core*',
            'snmp' => 0,
            'override_only' => 1,
            'lines' => 12,
        ]);

        $response = $this->actingAs($this->admin())->get(route('plugin.settings', 'ifalias'));

        $response->assertOk();
        $response->assertSee('value="core*"', escape: false);
        $response->assertSee('value="12"', escape: false);
        $response->assertSee('name="settings[override_only]" value="1" checked', escape: false);
        $this->assertStringNotContainsString(
            'name="settings[snmp]" value="1" checked',
            (string) $response->getContent(),
            'snmp is off, the box must be empty'
        );
    }

    /**
     * A bare url must not run anything: it only shows the form.
     */
    public function test_run_route_without_parameters_only_shows_the_form(): void
    {
        $response = $this->actingAs($this->admin())->get(route('ifalias.run'));

        $response->assertOk();
        $response->assertSee(__('ifalias::settings.run.idle'));
        $response->assertDontSee('id="ifalias-text"', escape: false);
    }

    /**
     * The report of the run form ends up in the text window of the same page.
     */
    public function test_run_shows_the_report_in_the_page(): void
    {
        $device = Device::factory()->create(['os' => 'generic']);
        $this->port($device, 1, 'Gi0/1', 'cust: Acme [1Gbps] {C-1}');
        $this->port($device, 2, 'Gi0/2', 'transit: provider [10Gbps]');
        $device->setAttrib('ifName:Gi0/1', '1');

        $this->fakeSnmp([1 => 'transit: other [1Gbps]', 2 => 'transit: provider [10Gbps]']);

        $response = $this->actingAs($this->admin())->get(route('ifalias.run', [
            'run' => 1,
            'device_spec' => $device->hostname,
            'snmp' => 1,
            'colors' => 0,
            'diff' => 0,
            'override_only' => 0,
            'inactive' => 0,
            'max_devices' => 0,
        ]));

        $response->assertOk();
        $response->assertSee('id="ifalias-text"', escape: false);
        $response->assertSee('Gi0/1', escape: false);
        $response->assertSee('override', escape: false);
        $response->assertSee('DIFFERENT', escape: false);
        $response->assertSee('ifalias-filter', escape: false);
    }

    /**
     * Every string of the page has to be translated. A missing key renders as
     * "ifalias::something", which looks like output nobody would trust, so the
     * whole page is checked for those.
     */
    public function test_no_translation_key_reaches_the_page(): void
    {
        $device = Device::factory()->create(['os' => 'generic']);
        $this->port($device, 1, 'Gi0/1', 'transit: provider [10Gbps]');

        $this->fakeSnmp([1 => 'transit: provider [10Gbps]']);

        $response = $this->actingAs($this->admin())->get(route('ifalias.run', [
            'run' => 1,
            'device_spec' => $device->hostname,
            'snmp' => 1,
            'colors' => 0,
            'diff' => 0,
            'override_only' => 0,
            'inactive' => 0,
            'max_devices' => 0,
        ]));

        $response->assertOk();
        $response->assertDontSee('ifalias::', escape: false);
    }

    /**
     * The colours of the report reach the browser as markup, one class per case.
     */
    public function test_run_colours_the_text_window(): void
    {
        $device = Device::factory()->create(['os' => 'generic']);
        $this->port($device, 1, 'Gi0/1', 'transit: provider [10Gbps]');
        $device->setAttrib('ifName:Gi0/1', '1');

        $this->fakeSnmp([1 => 'transit: provider [10Gbps]']);

        $response = $this->actingAs($this->admin())->get(route('ifalias.run', [
            'run' => 1,
            'device_spec' => $device->hostname,
            'snmp' => 1,
            'colors' => 1,
            'diff' => 0,
            'override_only' => 0,
            'inactive' => 0,
            'max_devices' => 0,
        ]));

        $response->assertOk();
        $response->assertSee('class="ifalias-yellow"', escape: false);
        $response->assertSee('class="ifalias-dim"', escape: false);
        // the escape sequences themselves must not survive into the page
        $response->assertDontSee("\033[", escape: false);
    }

    /**
     * The stored ifAlias comes from a device, so it is attacker controlled text:
     * it has to reach the page escaped, colours or not.
     */
    public function test_run_escapes_the_stored_values(): void
    {
        $device = Device::factory()->create(['os' => 'generic']);
        $this->port($device, 1, 'Gi0/1', '<script>alert(1)</script>');

        $this->fakeSnmp([]);

        $response = $this->actingAs($this->admin())->get(route('ifalias.run', [
            'run' => 1,
            'device_spec' => $device->hostname,
            'snmp' => 1,
            'colors' => 1,
            'diff' => 0,
            'override_only' => 0,
            'inactive' => 0,
            'max_devices' => 0,
        ]));

        $response->assertOk();
        $response->assertDontSee('<script>alert(1)</script>', escape: false);
        $response->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', escape: false);
    }

    /**
     * A run that matched nothing says so instead of showing an empty window.
     */
    public function test_run_without_a_matching_device_says_so(): void
    {
        $response = $this->actingAs($this->admin())->get(route('ifalias.run', [
            'run' => 1,
            'device_spec' => 'this-device-does-not-exist-12345',
        ]));

        $response->assertOk();
        $response->assertSee(__('ifalias::command.errors.no_device'));
    }

    /**
     * The device limit must be visible in the report, otherwise a short report
     * looks like a complete one.
     */
    public function test_run_reports_the_device_limit(): void
    {
        Device::factory()->count(3)->create(['os' => 'generic']);

        $response = $this->actingAs($this->admin())->get(route('ifalias.run', [
            'run' => 1,
            'device_spec' => 'all',
            'snmp' => 0,
            'colors' => 0,
            'max_devices' => 1,
        ]));

        $response->assertOk();
        $response->assertSee(__('ifalias::command.truncated', ['devices' => 1]));
    }

    private function admin(): User
    {
        Role::findOrCreate('admin');

        $user = User::factory()->create(['enabled' => 1]);
        $user->assignRole('admin');

        return $user;
    }

    /**
     * A command reads the settings when it is configured, which in production
     * happens once per process. A test shares one process across requests, so
     * the console kernel is dropped to let the next run configure itself again.
     */
    private function reconfigureCommand(): void
    {
        $this->app->forgetInstance(Kernel::class);
        ArtisanFacade::clearResolvedInstance(Kernel::class);
    }

    /**
     * The plugin manager reads the whole plugins table once, while the
     * application boots, which here is before the test switched to its own
     * in memory database. Dropping the cached collection makes it read the
     * database the test writes to, the way a request on a real install does.
     */
    private function forgetCachedPlugins(): void
    {
        $cached = new ReflectionProperty(app(PluginManagerInterface::class), 'plugins');
        $cached->setValue(app(PluginManagerInterface::class), null);
    }

    /**
     * Store settings the way the settings form does, then let the plugin manager
     * read them back.
     *
     * @param  array<string, mixed>  $settings
     */
    private function storeSettings(array $settings): void
    {
        app(PluginManagerInterface::class)->setSettings('ifalias', $settings);
        $this->forgetCachedPlugins();
    }

    private function port(Device $device, int $ifIndex, string $ifName, ?string $ifAlias): Port
    {
        return Port::factory()->create([
            'device_id' => $device->device_id,
            'ifIndex' => $ifIndex,
            'ifName' => $ifName,
            'ifAlias' => $ifAlias,
            'ifDescr' => $ifName,
        ]);
    }

    /**
     * @param  array<int, string>  $alias
     */
    private function fakeSnmp(array $alias): void
    {
        $this->app->bind(SnmpQueryInterface::class, function () use ($alias) {
            $mock = Mockery::mock(SnmpQueryInterface::class);
            $mock->shouldReceive('hideMib')->andReturnSelf();
            $mock->shouldReceive('device')->andReturnSelf();
            $mock->shouldReceive('walk')->andReturnUsing(function (array $oids) use ($alias) {
                $values = [];
                foreach ($oids as $oid) {
                    foreach ($oid === 'ifAlias' ? $alias : [] as $index => $value) {
                        $values["$oid.$index"] = $value;
                    }
                }

                return new SnmpResponse($values);
            });

            return $mock;
        });
    }
}
