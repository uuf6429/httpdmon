<?php

return array(
        array(
            'type' => 'access',
            'path' => '/var/www/vhosts/*/statistics/logs/access_log',
            'host_parser' => '$parts = explode(\'/\', $fileName); return $parts[4];',
        ),
        array(
            'type' => 'access',
            'path' => '/var/www/vhosts/*/logs/access_log',
            'host_parser' => '$parts = explode(\'/\', $fileName); return $parts[4];',
        ),
        array(
            'type' => 'error',
            'path' => '/var/www/vhosts/*/statistics/logs/error_log',
            'host_parser' => '$parts = explode(\'/\', $fileName); return $parts[4];',
        ),
        array(
            'type' => 'error',
            'path' => '/var/www/vhosts/*/logs/error_log',
            'host_parser' => '$parts = explode(\'/\', $fileName); return $parts[4];',
        ),
    );
