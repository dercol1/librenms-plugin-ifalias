# librenms-plugin-ifalias

LibreNMS plugin that adds `lnms port:ifAlias`, a read only report of where the
`ifAlias` stored for each port actually comes from, plus a settings page that
runs it and explains it without a terminal.

## Why

The `ifAlias` in the `ports` table is not necessarily what the device reports.
It can come from three different places:

1. **the device** reports `IF-MIB::ifAlias`
2. **a user override**, stored as a `devices_attribs` entry `ifName:<ifName>`,
   written by the web ui (Device -> Edit -> Ports) or the api
3. **an automatic fallback**: `port_fill_missing_and_trim()` in
   `includes/functions.php` copies `ifDescr` into `ifAlias`, and `ifName` when
   the device has no `ifDescr` either

Case 3 is easy to mistake for case 2: the value differs from the device, but
nobody asked for it. Until now the only hint was a pencil icon in the web ui,
and there was no command line way to tell them apart, so auditing which
`ifAlias` values are user maintained meant writing SQL against `devices_attribs`
by hand.

## What you get

- `lnms port:ifAlias`, the report on a terminal, with paging and colours
- a settings page (**Settings -> Plugins -> ifalias -> Settings**) with the
  settings that are worth setting, the full manual, and the same report run from
  the browser into a text window on the page

## Install

`lnms plugin:add` runs a plain `composer require`, so it can only find the
package in a **configured composer repository**. Pick the section that matches
your situation.

### From Packagist (stable release)

Nothing to configure, this is the normal path:

```bash
lnms plugin:add dercol1/librenms-plugin-ifalias
```

### From GitHub (development, before it reaches Packagist)

Register a `vcs` repository. Do it in Composer's **global** configuration, not
in the LibreNMS `composer.json`: see
[why](#making-the-install-persistent-across-daily-updates) below, a repository
added to `composer.json` is wiped every night by `daily.sh`.

Always go through LibreNMS' own composer wrapper, it sets `COMPOSER_HOME` to
the same place the nightly cron uses:

```bash
sudo -u librenms php /opt/librenms/scripts/composer_wrapper.php \
  config --global repositories.ifalias \
  '{"type":"vcs","url":"https://github.com/dercol1/librenms-plugin-ifalias"}'
```

Then install it. The `:@dev` suffix is required until a tagged release exists:

```bash
lnms plugin:add dercol1/librenms-plugin-ifalias:@dev
```

To follow a branch, pin it explicitly:

```bash
lnms plugin:add "dercol1/librenms-plugin-ifalias:dev-main"
```

### From a local checkout (working on the plugin)

Same idea with a `path` repository, which symlinks or mirrors the directory, so
your edits take effect without reinstalling. The `url` must be an absolute path
to the folder holding the plugin's own `composer.json`, not its parent:

```bash
sudo -u librenms php /opt/librenms/scripts/composer_wrapper.php \
  config --global repositories.ifalias \
  '{"type":"path","url":"/opt/librenms-plugin-ifalias","options":{"symlink":true}}'
```

```bash
lnms plugin:add dercol1/librenms-plugin-ifalias:@dev
```

With `"symlink": true` composer symlinks the directory instead of copying it,
which is the most convenient while developing.

After any of these, check that the provider was discovered and the command is
available:

```bash
php artisan package:discover   # only if the command does not show up
lnms port:ifAlias --help
```

If the plugin is installed but does not appear in the web ui under Plugins,
enable it there.

### Making the install persistent across daily updates

LibreNMS updates itself once a day through `daily.sh` (run by cron). Before
pulling new code it restores Composer's files from git, silently discarding
every local modification:

```bash
# daily.sh, "Restore composer files if user installed plugins"
git checkout --quiet -- composer.json composer.lock
```

Afterwards it re-requires the packages recorded in the untracked,
update-safe `composer.plugins.json` and runs `composer install`. That mechanism
is what keeps plugins installed across updates, but **package resolution
happens after that reset**. A `repositories` entry added by hand to
`composer.json` is therefore wiped every night: the package can no longer be
resolved, and the following morning both the entry and the plugin are gone from
`vendor/`. The nightly run fails with the misleading message:

```text
Problem 1
  - Root composer.json requires dercol1/librenms-plugin-ifalias, it could not be
    found in any version, there may be a typo in the package name.
```

It is not a typo, it is a missing repository.

The fix is to register the repository in Composer's **global** configuration
instead, which lives outside the tracked Composer files and survives the reset.
Use the wrapper so the entry lands in the same `COMPOSER_HOME` that the nightly
cron reads, and check where that is:

```bash
sudo -u librenms php /opt/librenms/scripts/composer_wrapper.php config --global home
```

It prints the directory holding `config.json`, typically
`/opt/librenms/.config/composer` or `/opt/librenms/.composer`, depending on
whether the `librenms` user has a writable `HOME`. LibreNMS' gitignore covers
dot directories, so the file does not show up in `git status` and is not touched
by `git pull`; only a `git clean -x` would remove it.

Verify the repository is registered:

```bash
sudo -u librenms php /opt/librenms/scripts/composer_wrapper.php config --global repositories
# must show "ifalias"
```

From then on the nightly flow resolves the package through the global
repository and reinstalls the plugin automatically. Nothing else needs redoing:
the enabled state lives in the database and the plugin settings live in
`config/ifalias.php`, both untouched by updates.

To confirm the persistence end to end, check the first daily run after
installing:

```bash
grep 'Updating Composer packages' logs/daily.log   # must end in OK
ls vendor/dercol1/librenms-plugin-ifalias          # must still exist tomorrow
```

### Other local plugins and multiple repositories

`composer config --global repositories.<name>` keeps each repository under its
own key, so several local plugins coexist. If you hit a *"cannot overwrite
existing repository"* error, pick another name, for example `ifalias2`. Existing
entries are listed with:

```bash
sudo -u librenms php /opt/librenms/scripts/composer_wrapper.php config --global repositories
```

## Usage from the web ui

Everything is in the plugin settings page: **Settings -> Plugins -> ifalias ->
Settings**. The button core renders for the plugin leads there, and the page
holds three things:

- the **settings form**, the defaults of the report
- the **manual**, the full reference, right on the page
- the **run form**, and the report in a scrollable text window below it

No terminal involved:

```text
Plugins -> Settings -> ifalias -> Settings
  [Settings]   device spec, ask the devices, filters, colours, limits, pager
  [Run the report]   pick what to look at, press Run, read it below
  [Manual]     what the report means, every setting, the command line equivalent
```

The run is a plain GET, so the address bar holds the whole run: it can be
reloaded, bookmarked and sent to a colleague. The report arrives in a text
window you can scroll, filter, copy out of, and read with or without colours.

Asking the devices walks `IF-MIB::ifAlias` and `IF-MIB::ifDescr` on every
selected device. On a large install that takes a while, so the settings carry a
device limit, and you can turn the device walk off for a database only report.

The page needs the `plugin.admin` permission, the same one that guards the
plugin list.

## Settings

The settings are the defaults of both the run form and `lnms port:ifAlias`, and
each one can be flipped per run with its `--no-` twin.

| Setting | Default | What it does |
| ------- | ------- | ------------ |
| `device_spec` | `all` | Which devices to examine: id, hostname, group, `core*`, `odd`, `even`, `all` |
| `snmp` | on | Walk the devices. Off reports the database values only |
| `diff_only` | off | Only the ports whose stored ifAlias differs from the device |
| `override_only` | off | Only the ports a user overrode |
| `include_inactive` | off | Include deleted and disabled ports |
| `colors` | on | Colour the report |
| `max_devices` | `0` | Devices at most, `0` for no limit. Stops a run from walking away |
| `lines` | `30` | Terminal only: lines per page when no pager is available |
| `pager` | auto | Terminal only: pager command, `cat` for none |

They are stored in the database, so a LibreNMS update does not touch them.
`config/ifalias.php` carries the defaults and can be published with:

```bash
php artisan vendor:publish --provider="Dercol1\LibrenmsIfAlias\IfAliasPluginProvider" --tag=config
```

A value saved from the browser wins over the file; anything left untouched falls
back to it.

## Usage from the command line

```bash
lnms port:ifAlias all                      # every active device
lnms port:ifAlias router01                 # one device
lnms port:ifAlias 'core*'                  # a group
lnms port:ifAlias all --override-only      # only the ports a user overrode
lnms port:ifAlias all --diff               # only the ones differing from the device
lnms port:ifAlias router01 --no-snmp       # database only, no device contact
lnms port:ifAlias router01 --inactive      # include deleted and disabled ports
lnms port:ifAlias all --max-devices=50     # stop after 50 devices
lnms port:ifAlias all --no-diff --no-inactive   # override the saved settings
```

The command never polls and never writes anything.

## Example output

```text
Sources: override = set by a user, fill ifDescr / fill ifName = automatic fallback, device = reported by the device, db only = not polled
Pager: less -R -S -X -F
Device core1.example.com (os: ios, ports: 4)
ifIndex ifName               db (ports.ifAlias)             snmp (IF-MIB::ifAlias)         source        status
1       Gi0/1                transit: provider [10Gbps]     transit: provider [10Gbps]     device        same
2       Gi0/2                cust: Acme [1Gbps] {C-1}       transit: other [1Gbps]         override      DIFFERENT
           override uses the value stored in ports.ifAlias
3       Gi0/3                GigabitEthernet0/3 desc                                       fill ifDescr  DIFFERENT
           the device reports no ifAlias and LibreNMS copied ifDescr into it. This is an automatic fallback, not your override.
4       Gi0/4                Gi0/4                                                         fill ifName   DIFFERENT
           the device reports no ifAlias and LibreNMS copied ifName into it. This is an automatic fallback, not your override.


Stored ifAlias differs from the device: 3
  of which a user override: 1
  of which an automatic fallback (device reports no ifAlias): 2
Stored ifAlias equals the device value: 1
```

The counters always cover every port examined, not only the ones printed, so
the summary does not change depending on the filters used.

## Paging

When the output is a terminal the report is streamed into
`less -R -S -X -F`, so colours are kept, long lines are chopped instead of
folded, and the screen is not cleared on exit. Search with `/`, move with
`space`, `b`, `g`, `G`, quit with `q`. A page break always lands between two
ports, never in the middle of a row.

Redirection (a pipe, a file, `grep`) turns paging off by itself so the output
stays capturable. Page size comes from `--lines`, then the `lines` setting.

```bash
lnms port:ifAlias all > ifalias.txt          # no paging, whole report captured
IFALIAS_NO_PAGER=1 lnms port:ifAlias all     # straight to the terminal scrollback
IFALIAS_PAGER='most -R' lnms port:ifAlias all
lnms port:ifAlias all --pager=cat            # no pager at all
```

## Colours

One colour per case, so a long report can be skimmed:

| Colour | Meaning |
| ------ | ------- |
| green | the stored value matches the device |
| yellow | a user override, the value survives every poll |
| blue | the automatic fallback, nobody asked for this value |
| red | differs from the device and no override is set |
| bold | the device and table headers |
| dim | the explanatory notes under a row |

Colours are emitted only when the output supports them, following the usual
`--ansi` / `--no-ansi` and terminal detection, so a report redirected to a file
or a pipe contains no escape sequences. When the report is paged the codes
reach `less` untouched and `-R` passes them through.

In the web ui the same colours arrive as markup: the escape sequences are turned
into `<span class="ifalias-green">` and so on, and the stored values are escaped
on the way, since an `ifAlias` comes from a device.

If you see no colour at all, check in this order:

```bash
echo $TERM                    # empty or "dumb" means no colour support
lnms port:ifAlias all --ansi  # force it, useful to isolate the cause
```

## Routes and the route cache

The run form needs one url, `plugin/ifalias/run`, which the plugin registers
itself behind the same auth and `plugin.admin` gate as the settings page. It is
skipped when the routes are cached, because the cached collection already holds
it and adding to a compiled collection throws.

That means the cache has to be built **after** the plugin was installed. If the
run button gives a 404, clear it:

```bash
php artisan optimize:clear    # or php artisan route:cache
```

`daily.sh` starts with the same command, so an update fixes it on its own.

## Uninstall

Three steps, in this order. Removing only the plugin leaves the repository
behind, which is harmless but confusing later; removing the repository first
makes the uninstall fail, because composer can no longer resolve the package.

```bash
# 1. the plugin
lnms plugin:remove dercol1/librenms-plugin-ifalias

# 2. the repository entry, only if you registered one by hand
sudo -u librenms php /opt/librenms/scripts/composer_wrapper.php \
  config --global --unset repositories.ifalias

# 3. optional: the published config, if you ever published it
rm -f config/ifalias.php
```

There is nothing to remove from the LibreNMS `composer.json`, because the
repository was never added there.

Confirm that nothing is left:

```bash
sudo -u librenms php /opt/librenms/scripts/composer_wrapper.php config --global repositories
# "ifalias" must be gone
ls vendor/dercol1 2>/dev/null   # must not exist
lnms port:ifAlias all           # must report the command as not found
```

## Reinstall

Installing again is the same as the first time. If you removed the repository
in the previous step, register it again first:

```bash
# from Packagist, nothing to register
lnms plugin:add dercol1/librenms-plugin-ifalias

# from a local checkout
sudo -u librenms php /opt/librenms/scripts/composer_wrapper.php \
  config --global repositories.ifalias \
  '{"type":"path","url":"/opt/librenms-plugin-ifalias","options":{"symlink":true}}'
lnms plugin:add dercol1/librenms-plugin-ifalias:@dev
```

```bash
php artisan package:discover   # if the command does not show up
lnms port:ifAlias --help
```

The enabled state lives in the database, so a plugin that is still listed as
enabled lights up again on its own, there is nothing else to restore.

## Settings

`config/ifalias.php` can be published with:

```bash
php artisan vendor:publish --provider="Dercol1\LibrenmsIfAlias\IfAliasPluginProvider" --tag=config
```

## Development

The plugin is developed inside a LibreNMS install, because it builds on the
core models and console. The checks use the toolchain of that install, so keep
the two checkouts next to each other:

```
/opt/librenms
/opt/librenms-plugin-ifalias
```

Write a phpunit config that points the host test bootstrap at this plugin's
tests:

```bash
cat > /opt/librenms-plugin-ifalias/phpunit-host.xml <<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="/opt/librenms/tests/bootstrap.php" colors="true"
         cacheDirectory="/opt/librenms-plugin-ifalias/.phpunit.cache">
    <testsuites>
        <testsuite name="ifalias">
            <directory>/opt/librenms-plugin-ifalias/tests</directory>
        </testsuite>
    </testsuites>
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="CACHE_STORE" value="array"/>
        <env name="QUEUE_CONNECTION" value="sync"/>
        <env name="DB_CONNECTION" value="testing"/>
        <const name="PHPUNIT_RUNNING" value="true"/>
    </php>
</phpunit>
XML
```

Then, from the plugin directory, except phpunit which has to run from the
LibreNMS directory because its bootstrap uses relative paths:

```bash
/opt/librenms/vendor/bin/pint --test src tests
/opt/librenms/vendor/bin/phpstan analyse -c phpstan.neon
cd /opt/librenms && ./vendor/bin/phpunit -c /opt/librenms-plugin-ifalias/phpunit-host.xml
```

If your LibreNMS install is somewhere else, set `LIBRENMS_PATH` for phpstan
and adjust the two paths in the config above:

```bash
LIBRENMS_PATH=/srv/librenms /opt/librenms/vendor/bin/phpstan analyse -c phpstan.neon
```

Note that `lnms plugin:add` and `lnms plugin:remove` run composer with
`--update-no-dev`, so they drop phpunit, phpstan and rector. Run
`php scripts/composer_wrapper.php install` in the LibreNMS directory after
either command if you need the toolchain back.

The tests need no MySQL and no snmpsim: they use LibreNMS `InMemoryDbTestCase`
and a faked `SnmpQueryInterface`, so the device, override and fallback cases
are covered deterministically.

The settings page tests go through the real http kernel and hit the core routes,
so they cover what a browser actually gets: the settings form, the manual, the
run form, the escaping of stored values and the permissions. Two details are
worth knowing if a test ever surprises you:

- a command reads the settings when it is configured, once per process, so a test
  that changes the settings drops the console kernel before calling artisan
- the plugin manager caches the plugins table while the application boots, before
  a test switched to its own database, so the tests clear that cache too

## Layout

```
src/IfAliasPluginProvider.php   views, translations, config defaults, routes, command
src/Console/                    the command and the pager
src/Report/                     the report itself, written to a sink
src/Settings/PluginSettings.php stored settings over config defaults, coerced
src/Web/                        the run route, the settings page data, ansi to html
src/Hooks/Settings.php          the settings hook core renders the page through
resources/views/settings.blade.php   the page: settings, run form, text window
resources/views/manual.blade.php      the manual shown on the same page
```

The report does not know who is reading it: it writes to a `ReportSink`, which
is the pager on a terminal and a buffer in the browser. That is why the web ui and
the command always agree.

## License

GPL-3.0-or-later, the same license as LibreNMS.
