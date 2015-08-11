@echo off
mode CON COLS=30 LINES=5
php -r "echo PHP_EOL.PHP_EOL; $i = 0; while(true){ usleep(100000); @file_get_contents('http://localhost/?'.mt_rand().'='.mt_rand()); $i++; if ($i > 4) $i = 0; echo '    Running' . str_pad('', $i, '.') . '    ' . chr(13); }"
pause