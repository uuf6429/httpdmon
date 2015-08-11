<?php

return array(
        array(
            'class' => 'AccessLogFileMonitor',
            'path' => '/var/www/vhosts/*/statistics/logs/access_log',
            'host_parser' => '$parts = explode(\'/\', $fileName); return $parts[4];',
        ),
        array(
            'class' => 'AccessLogFileMonitor',
            'path' => '/var/www/vhosts/*/logs/access_log',
            'host_parser' => '$parts = explode(\'/\', $fileName); return $parts[4];',
        ),
        array(
            'class' => 'ErrorLogFileMonitor',
            'path' => '/var/www/vhosts/*/statistics/logs/error_log',
            'host_parser' => '$parts = explode(\'/\', $fileName); return $parts[4];',
        ),
        array(
            'class' => 'ErrorLogFileMonitor',
            'path' => '/var/www/vhosts/*/logs/error_log',
            'host_parser' => '$parts = explode(\'/\', $fileName); return $parts[4];',
        ),
    );
