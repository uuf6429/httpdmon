<?php

class HttpdMon
{
    /**
     * @var Config
     */
    protected $config;
    
    /**
     * @var Console
     */
    protected $console;

    public function __construct($cfg, $con)
    {
        $this->config = $cfg;
        $this->console = $con;
    }
    
    protected function ValidateCliOptions()
    {
        // no web access pls!
        if (isset($_SERVER['SERVER_NAME']) || !isset($GLOBALS['argv'])) {
            throw new Exception('This is a shell script, not a web service.');
        }
        
        // try being smarter than the user :D
        if ($this->console->HasArg('c') && $this->console->HasArg('t')) {
            throw new Exception('Please decide if you want colors (-c) or not (-t), not both, Dummkopf!');
        }
    }
    
    protected function EnableCliMode()
    {
        // ensure we can run forever
        set_time_limit(0);
        
        // flush any output and disable buffering
        while (ob_get_level()) {
            ob_end_flush();
        }
        ob_implicit_flush(1);
    }

    protected $resolveIps = false;
    protected $errorsOnly = false;
    protected $enabled = true;

    /**
     * @var AbstractFileMonitor[]
     */
    protected $monitors = array();
    protected $interval = 0;

    protected function CreateMonitors()
    {
        $result = array();

        foreach ($this->config->GetMonitorConfig() as $cfg) {
            $cls = $cfg['class'];
            $host_parser = isset($cfg['host_parser']) ? $cfg['host_parser'] : null; // $file, $line => string
            $file_parser = isset($cfg['file_parser']) ? $cfg['file_parser'] : null; // $file, $lines => object[]
            $file_regexp = isset($cfg['file_regexp']) ? $cfg['file_regexp'] : null;
            foreach (glob($cfg['path']) as $file) {
                $result[] = new $cls($file, $host_parser, $file_parser, $file_regexp);
            }
        }
        
        return $result;
    }

    protected function PrintHelp()
    {
        $con = $this->console;
        $con->WriteLine('Usage: httpdmon ' . (IS_WINDOWS ? '/?' : '--help'));
        $con->WriteLine('       httpdmon ' . (IS_WINDOWS ? '/u' : '--update'));
        $con->WriteLine('       httpdmon ' . (IS_WINDOWS ? '/v' : '--version'));
        $con->WriteLine('       httpdmon [options]');
        $con->WriteLine('  ' . str_pad(IS_WINDOWS ? '/c' : '-c', 27) . ' Make use of colors, even on Windows (requires ansicon or similar)');
        $con->WriteLine('  ' . str_pad(IS_WINDOWS ? '/d DELAY' : '-d, --delay=DELAY', 27) . ' Delay between updates in milliseconds (default is 100)');
        $con->WriteLine('  ' . str_pad(IS_WINDOWS ? '/?' : '-h, --help', 27) . ' Show this help and exit');
        $con->WriteLine('  ' . str_pad(IS_WINDOWS ? '/i PATH' : '-i PATH', 27) . ' Specify wildcard path to load config from');
        $con->WriteLine('  ' . str_pad(IS_WINDOWS ? '/m' : '-m', 27) . ' Only show errors (and access entries with status of 400+)');
        $con->WriteLine('  ' . str_pad(IS_WINDOWS ? '/r' : '-r', 27) . ' Resolve IP Addresses to Hostnames');
        $con->WriteLine('  ' . str_pad(IS_WINDOWS ? '/t' : '-t', 27) . ' Force plain text (no colors)');
        $con->WriteLine('  ' . str_pad(IS_WINDOWS ? '/u' : '-u, --update', 27) . ' Attempt program update and exit');
        $con->WriteLine('  ' . str_pad(IS_WINDOWS ? '/v' : '-v, --version', 27) . ' Show program version and exit');
    }

    protected function PrintVersion()
    {
        date_default_timezone_set('Europe/Berlin');
        $this->console->WriteLine('httpdmon ' . VERSION);
        $this->console->WriteLine('Copyright (c) 2013-' . date('Y') . ' Christian Sciberras');
    }

    protected function RunMainLoop()
    {
        if ($this->config->IsEmpty()) {
            throw new Exception('No configuration loaded.');
        }

        $this->console->Clear();
        
        $this->errorsOnly = $this->console->HasArg('m');
        $this->resolveIps = $this->console->HasArg('r');
        $this->interval = intval(($this->console->HasArg('-delay')
                ? $this->console->GetArg('-delay', 100)
                : $this->console->GetArg('d', 100)
            ) * 1000);
        $this->monitors = $this->CreateMonitors();
        
        while ($this->enabled) {
            // check for file changes
            foreach ($this->monitors as $monitor) {
                if ($monitor->HasChanges()) {
                    $monitor->Display($this->console, $this->resolveIps, $this->errorsOnly);
                }
            }

            // did user decide to quit?
            /*if (non_block_read() == 'q') {
            exit();
			}*/
            
            // give the cpu some time to breath
            usleep($this->interval);
        }
    }

    protected function RunUpdater()
    {
        $updater = new Updater();
        $updater->UpdateUrl = 'https://raw.github.com/uuf6429/httpdmon/master/build/httpdmon.php?nc=' . mt_rand();
        $updater->LocalVersion = VERSION;
        $updater->TryRunCmd = 'php -f ' . escapeshellarg(__FILE__) . ' -- ' . (IS_WINDOWS ? '/' : '-') . 'v';
        $updater->EventHandler = array($this, 'HandleUpdateScriptEvent');
        $updater->Run();
    }

    /**
     * Event handler for script updater.
     */
    public function HandleUpdateScriptEvent($event, $args = array())
    {
        $con = $this->console;

        switch ($event) {
            case 'error':
                $con->WriteLine('[' . $con->Colorize('FATAL', Console::C_RED) . '] ' . $args['reason']);
                break;

            case 'warn':
                $con->WriteLine('[' . $con->Colorize('WARNING', Console::C_YELLOW) . '] ' . $args['reason']);
                break;

            case 'version_check':
                switch ($args['intention']) {
                    case 'update':
                        $con->WriteLine('Updating to ' . $args['next_version'] . '...');
                        break;

                    case 'ignore':
                        $con->WriteLine('Already up to date');
                        break;

                    case 'fail':
                        $con->WriteLine('Your version is newer');
                        break;

                }
                break;

            case 'start':
                $con->WriteLine('Checking for updates...');
                break;

            case 'before_download':
                $con->Write('Downloading...');
                break;

            case 'download_progress':
                $con->WriteLine('Downloading... ' . round($args['current'] / max($args['total'], 1) * 100, 2) . '%');
                break;

            case 'after_download':
                // prepends to downloaded data if current file currently uses it
                if (substr(file_get_contents(__FILE__), 0, 14) == '#!/usr/bin/php') {
                    $args['data'] = '#!/usr/bin/php -q' . PHP_EOL . $args['data'];
                }
                break;

            case 'before_try':
                $con->WriteLine('Testing downloaded update...');
                break;

            case 'finish':
                $con->WriteLine('Update completed successfully.');
                $con->WriteLine('Welcome to ' . basename(__FILE__, '.php') . ' ' . $args['new_version'] . '!');
                break;
        }
    }

    public function Run()
    {
        $this->EnableCliMode();
        $this->ValidateCliOptions();

        switch (true) {
            case $this->console->HasArg(array('h', '-help', '?')):
                $this->PrintHelp();
                break;

            case $this->console->HasArg(array('v', '-version')):
                $this->PrintVersion();
                break;

            case $this->console->HasArg(array('u', '-update')):
                $this->RunUpdater();
                break;

            default:
                $this->RunMainLoop();
                break;
        }

        exit(0);
    }
}
