<?php

return array(
        array(
            'class' => 'AccessLogFileMonitor',
            'path' => '/usr/local/apache/logs/access_log',
        ),
        array(
            'class' => 'AccessLogFileMonitor',
            'path' => '/home/*/access-logs/*',
            'host_parser' => '$parts = explode(\'/\', $fileName); return $parts[2];',
        ),
        array(
            'class' => 'ErrorLogFileMonitor',
            'path' => '/usr/local/apache/logs/error_log',
        ),
    );
