<?php

namespace Dercol1\LibrenmsIfAlias\Console;

use Dercol1\LibrenmsIfAlias\Report\IfAliasReport;
use Dercol1\LibrenmsIfAlias\Report\ReportOptions;
use Dercol1\LibrenmsIfAlias\Settings\PluginSettings;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * Report where the ifAlias stored for each port comes from.
 *
 * The value in the ports table is not necessarily what the device reports. It
 * can come from the device, from a user override, or from the fallback
 * LibreNMS applies when a device reports no ifAlias. Only the web ui hints at
 * which one is in use, so auditing which ifAlias values are user maintained
 * otherwise means writing SQL against devices_attribs by hand.
 *
 * Every option defaults to the plugin settings, so what is set in the web ui
 * (Plugins -> ifalias -> Settings) is what a plain "lnms port:ifAlias all"
 * does, and every option can still be overridden per run with its --no- twin:
 * --diff|--no-diff, --snmp|--no-snmp and so on.
 *
 * Read only: nothing is polled and nothing is written.
 */
class PortIfAliasCommand extends Command
{
    protected $name = 'port:ifAlias';

    protected function configure(): void
    {
        // -h, -q, -v, -V, -n and -e belong to the Symfony console itself
        $settings = PluginSettings::current();

        $this->setDescription(__('ifalias::command.description'))
            ->addArgument('device spec', InputArgument::OPTIONAL, __('ifalias::command.arguments.device spec'), $settings->deviceSpec())
            ->addOption('diff', 'd', InputOption::VALUE_NEGATABLE, __('ifalias::command.options.diff'), $settings->diffOnly())
            ->addOption('override-only', null, InputOption::VALUE_NEGATABLE, __('ifalias::command.options.override-only'), $settings->overrideOnly())
            ->addOption('inactive', 'o', InputOption::VALUE_NEGATABLE, __('ifalias::command.options.inactive'), $settings->includeInactive())
            ->addOption('snmp', null, InputOption::VALUE_NEGATABLE, __('ifalias::command.options.snmp'), $settings->snmp())
            ->addOption('max-devices', null, InputOption::VALUE_REQUIRED, __('ifalias::command.options.max-devices'), $settings->maxDevices())
            ->addOption('pager', null, InputOption::VALUE_REQUIRED, __('ifalias::command.options.pager'), $settings->pager())
            ->addOption('lines', null, InputOption::VALUE_REQUIRED, __('ifalias::command.options.lines'), $settings->lines());
    }

    public function handle(): int
    {
        $options = $this->reportOptions();

        // Colours are only emitted when the output supports them, so a report
        // piped to a file or to grep stays free of escape sequences. They are
        // emitted as raw codes because the pager receives the text directly,
        // bypassing the Symfony formatter, and less -R passes them through.
        $pager = new ConsolePager(
            $options->pager,
            $options->lines,
            fn (string $text) => $this->output->write($text)
        );

        $mode = $pager->open();

        $result = (new IfAliasReport($options))->render($pager, $this->pagerNote($mode));

        $pager->close();

        return $result->matched ? self::SUCCESS : self::FAILURE;
    }

    /** Command::options() is taken by the framework, hence the name. */
    private function reportOptions(): ReportOptions
    {
        $pager = $this->option('pager');

        return new ReportOptions(
            deviceSpec: (string) $this->argument('device spec'),
            snmp: (bool) $this->option('snmp'),
            diffOnly: (bool) $this->option('diff'),
            overrideOnly: (bool) $this->option('override-only'),
            includeInactive: (bool) $this->option('inactive'),
            colors: $this->output->isDecorated(),
            maxDevices: max(0, (int) $this->option('max-devices')),
            lines: max(1, (int) $this->option('lines')),
            pager: $pager === null ? null : (string) $pager, // null auto detects, "cat" and "" mean no pager
        );
    }

    /** The line under the header telling the reader how the output is paged. */
    private function pagerNote(string $mode): string
    {
        return match ($mode) {
            ConsolePager::MODE_LESS => __('ifalias::command.pager') . ' ' . (string) ($this->option('pager') ?: __('ifalias::command.default_pager')),
            ConsolePager::MODE_ENTER => __('ifalias::command.pager') . ' ' . __('ifalias::command.pager_fallback', ['lines' => $this->option('lines')]),
            default => __('ifalias::command.pager') . ' ' . __('ifalias::command.pager_off'),
        };
    }
}
