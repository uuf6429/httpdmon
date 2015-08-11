<?php

return array(
        array(
            'class' => 'AccessLogFileMonitor',
            'path' => getenv('ProgramFiles').'\\Zend\\Apache2\\logs\\access.log',
        ),
        array(
            'class' => 'ErrorLogFileMonitor',
            'path' => getenv('ProgramFiles').'\\Zend\\Apache2\\logs\\error.log',
        ),
    );
