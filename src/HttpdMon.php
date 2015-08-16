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

    protected $UpdateScriptExitCode = 0;

    /**
     * Attempts to update current file from URL.
     * @param string $update_url Target URL to read updates from.
     * @param array|object $options Array of options (see docs for more info).
     */
    protected function UpdateScript($update_url, $options)
    {
        // initialize
        $options = array_merge(array(
            // Version of the current file/script.
            'current_version' => '0.0.0',
            // Regular expression for finding version in target file.
            'version_regex' => '/define\\(\\s*[\'"]version[\'"]\\s*,\\s*[\'"](.*?)[\'"]\\s*\\)/i',
            // Try running downloaded file to ensure it works.
            'try_run' => true,
            // Used by updater to notify callee on event changes.
            'on_event' => 'pi',
            // The file to be overwritten by the updater.
            'target_file' => $_SERVER['SCRIPT_FILENAME'],
            // Force local file to be overwritten by remote file regardless of version.
            'force_update' => false,
            // Command called to verify the upgrade is fine.
            'try_run_cmd' => null,
        ), (array)$options);

        if (is_null($options['try_run_cmd'])) { // build command with the correct target_file
            $options['try_run_cmd'] = 'php -f ' . escapeshellarg($options['target_file']);
        }

        $notify = $options['on_event'];
        $rollback = false;
        $next_version = null;
        static $intentions = array(-1=>'fail', 0=>'ignore', 1=>'update');
        
        // process
        $notify('start');
        $notify('before_download', array('url'=>$update_url));
        
        if (!($data = file_get_contents($update_url))) {
            return $notify('error', array('reason'=>'File download failed', 'target'=>$update_url)) && false;
        }
        
        $notify('after_download', array('data'=>&$data));
        
        if (!preg_match($options['version_regex'], $data, $next_version)) {
            return $notify('error', array('reason'=>'Could not determine version of target file', 'target'=>$data, 'result'=>$next_version)) && false;
        }
        
        if (!($next_version = array_pop($next_version))) {
            return $notify('error', array('reason'=>'Version of target file is empty', 'target'=>$data, 'result'=>$next_version)) && false;
        }
        
        $v_diff = version_compare($next_version, $options['current_version']);
        $should_fail = $notify('version_check', array('intention'=>$intentions[$v_diff], 'curr_version'=>$options['current_version'], 'next_version'=>$next_version));
        
        if ($should_fail === false) {
            return $notify('error', array('reason'=>'Update cancelled by user code')) && false;
        }
        
        if ($v_diff === 0 && !$options['force_update']) {
            return $notify('already_uptodate') && false;
        }
        
        if ($v_diff === -1 && !$options['force_update']) {
            return $notify('warn', array('reason'=>'Local file is newer than remote one', 'curr_version'=>$options['current_version'], 'next_version'=>$next_version)) && false;
        }
        
        if (!copy($options['target_file'], $options['target_file'] . '.bak')) {
            $notify('warn', array('reason'=>'Backup operation failed', 'target'=>$options['target_file']));
        }
        
        if (!file_put_contents($options['target_file'], $data)) {
            $notify('warn', array('reason'=>'Failed writing to file', 'target'=>$options['target_file']));
            $rollback = true;
        }
        
        if (!$rollback && $options['try_run']) {
            $notify('before_try', array('options'=>$options));
            ob_start();
            $exit = 0;
            passthru($options['try_run_cmd'], $exit);
            $out = ob_get_clean();
            $notify('after_try', array('options'=>$options, 'output'=>$out, 'exitcode'=>$exit));
            if ($exit !== 0) {
                $notify('warn', array('reason'=>'Downloaded update seems to be broken', 'output'=>$out, 'exitcode'=>$exit));
                $rollback = true;
            }
        }
        
        if ($rollback) {
            $notify('before_rollback', array('options'=>$options));
            if (!rename($options['target_file'] . '.bak', $options['target_file'])) {
                return $notify('error', array('reason'=>'Rollback operation failed', 'target'=>$options['target_file'] . '.bak')) && false;
            }
            $notify('after_rollback', array('options'=>$options));
        } else {
            if (!unlink($options['target_file'] . '.bak')) {
                $notify('warn', array('reason'=>'Cleanup operation failed', 'target'=>$options['target_file'] . '.bak'));
            }
            $notify('finish', array('new_version'=>$next_version));
        }

        return null;
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

    protected function RunUpdater()
    {
        $this->UpdateScript(
            'https://raw.github.com/uuf6429/httpdmon/master/build/httpdmon.php?nc=' . mt_rand(),
            array(
                'current_version' => VERSION,
                'try_run' => true,
                'try_run_cmd' => 'php -f ' . escapeshellarg(__FILE__) . ' -- ' . (IS_WINDOWS ? '/' : '-') . 'v',
                'on_event' => array($this, 'HandleUpdateScriptEvent'),
            )
        );
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
