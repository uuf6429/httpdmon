<?php

function plesk_parse_host($fileName) {
    $parts = explode('/', $fileName);
    return $parts[4];
}

return array(
        array(
            'class' => 'AccessLogFileMonitor',
            'path' => '/var/www/vhosts/*/statistics/logs/access_log',
            'host_parser' => 'plesk_parse_host',
        ),
        array(
            'class' => 'AccessLogFileMonitor',
            'path' => '/var/www/vhosts/*/logs/access_log',
            'host_parser' => 'plesk_parse_host',
        ),
        array(
            'class' => 'ErrorLogFileMonitor',
            'path' => '/var/www/vhosts/*/statistics/logs/error_log',
            'host_parser' => 'plesk_parse_host',
        ),
        array(
            'class' => 'ErrorLogFileMonitor',
            'path' => '/var/www/vhosts/*/logs/error_log',
            'host_parser' => 'plesk_parse_host',
        ),
    );
