<?php

return array(
        array(
            'class' => 'AccessLogFileMonitor',
            'path' => '/var/log/httpd/access_log',
        ),
        array(
            'class' => 'ErrorLogFileMonitor',
            'path' => '/var/log/httpd/error_log',
        ),
        
        array(
            'class' => 'AccessLogFileMonitor',
            'path' => '/var/log/apache2/access.log',
        ),
        array(
            'class' => 'ErrorLogFileMonitor',
            'path' => '/var/log/apache2/error.log',
        ),
        
        array(
            'class' => 'AccessLogFileMonitor',
            'path' => '/var/log/nginx/*access.log',
        ),
        array(
            'class' => 'ErrorLogFileMonitor',
            'path' => '/var/log/nginx/*error.log',
        ),
    );
