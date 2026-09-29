<?php

/**
 * Tells phpstan where to find the LibreNMS classes this plugin builds on.
 *
 * A plugin is developed inside a LibreNMS install, so the host application is
 * not a composer dependency. Point LIBRENMS_PATH at the checkout (it defaults
 * to /opt/librenms) and the model and console classes become analysable.
 */
$host = getenv('LIBRENMS_PATH') ?: '/opt/librenms';

if (is_file($host . '/vendor/autoload.php')) {
    require_once $host . '/vendor/autoload.php';
} else {
    fwrite(STDERR, "LibreNMS not found at $host, set LIBRENMS_PATH to your checkout. "
        . "Classes from App\\ and SnmpQuery will be reported as unknown.\n");
}
