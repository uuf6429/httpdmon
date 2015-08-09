<?php

return array(
        array(
            'type' => 'access',
            'path' => getenv('ProgramFiles').'\\Zend\\Apache2\\logs\\access.log',
        ),
        array(
            'type' => 'error',
            'path' => getenv('ProgramFiles').'\\Zend\\Apache2\\logs\\error.log',
        ),
    );
