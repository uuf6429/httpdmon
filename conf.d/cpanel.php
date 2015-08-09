<?php

return array(
        array(
            'type' => 'access',
            'path' => '/usr/local/apache/logs/access_log',
        ),
        array(
            'type' => 'access',
            'path' => '/home/*/access-logs/*',
            'host_parser' => '$parts = explode(\'/\', $fileName); return $parts[2];',
        ),
        array(
            'type' => 'error',
            'path' => '/usr/local/apache/logs/error_log',
        ),
    );
