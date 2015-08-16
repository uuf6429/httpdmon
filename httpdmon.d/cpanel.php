<?php

function cpanel_parse_host($fileName){
    $parts = explode('/', $fileName);
    return $parts[2];
}

return array(
        array(
            'class' => 'AccessLogFileMonitor',
            'path' => '/usr/local/apache/logs/access_log',
        ),
        array(
            'class' => 'AccessLogFileMonitor',
            'path' => '/home/*/access-logs/*',
            'host_parser' => 'cpanel_parse_host',
        ),
        array(
            'class' => 'ErrorLogFileMonitor',
            'path' => '/usr/local/apache/logs/error_log',
        ),
    );
