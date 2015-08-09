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

    /**
     * @var FileMonitor[]
     */
    protected $monitors = array();
    protected $interval = 0;

    protected function CreateMonitors()
    {
        $result = array();

        foreach ($this->config->GetMonitorConfig() as $cfg) {
            $cls = ucfirst($cfg['type'] . 'logFileMonitor');
            foreach (glob($cfg['path']) as $file) {
                $result[] = new $cls($file);
            }
        }
        
        return $result;
    }

    protected function DoMainLoop()
    {
		$con = $this->console;
        while (true) {
            // check for file changes
            foreach ($this->monitors as $monitor) {
                if ($monitor->hasChanges()) {
                    $domain = $monitor->getDomain();
                    $domain = trim($domain) ? $domain : 'cannot resolve vhost';
                    switch (true) {
                        case $monitor instanceof AccesslogFileMonitor:
                            foreach ($monitor->getLines() as $line) {
                                if ($this->errorsOnly && $line->code < 400) {
                                    continue; // not an error empty, go to next entry
                                }
                                $con->WritePart('['.$con->Colorize('ACCESS', Console::C_CYAN).'] ');
                                $con->WritePart($con->Colorize($this->resolveIps ? substr(str_pad(resolve_ip($line->ip), 48), 0, 48) : str_pad($line->ip, 16), Console::C_YELLOW).' ');
                                $con->WritePart($con->Colorize(str_pad($domain, 32), Console::C_BROWN).' ');
                                $con->WritePart($con->Colorize(str_pad($line->method, 8), Console::C_LIGHT_PURPLE));
                                $long_mesg = ''
                                    . $con->Colorize(str_replace('&', $con->Colorize('&', Console::C_DARK_GRAY), $line->url), Console::C_WHITE)
                                    . $con->Colorize(' > ', Console::C_DARK_GRAY)
                                    . $con->Colorize($line->code, $line->code < 400 ? Console::C_GREEN : Console::C_RED)
                                    . $con->Colorize(' (', Console::C_DARK_GRAY).$con->Colorize($line->size, Console::C_WHITE).$con->Colorize(' bytes)', Console::C_DARK_GRAY)
                                ;
                                //write_line(implode(str_pad(PHP_EOL, count_parts()), str_split($long_mesg, cli_width() - count_parts())));
                                write_line($long_mesg);
                            }
                            break;
                        case $monitor instanceof ErrorlogFileMonitor:
                            foreach ($monitor->getLines() as $line) {
                                $con->WritePart('['.$con->Colorize('ERROR', Console::C_RED).']  ');
                                $con->WritePart($con->Colorize($this->resolveIps ? substr(str_pad(resolve_ip($line->ip), 48), 0, 48) : str_pad($line->ip, 16), Console::C_YELLOW).' ');
                                $con->WritePart($con->Colorize(str_pad($domain, 32), Console::C_BROWN).' ');
                                $long_mesg = $con->Colorize($line->message, Console::C_RED);
                                //write_line(implode(str_pad(PHP_EOL, count_parts()), str_split($long_mesg, cli_width() - count_parts())));
                                write_line($long_mesg);
                            }
                            break;
                        default:
                            throw new Exception('Unknown monitor type "'.get_class($monitor).'"');
                    }
                }
            }
            // did user decide to quit?
            /*if (non_block_read() == 'q') {
				exit();
			}*/
            // give the cpu time to breath
            usleep($this->interval);
        }
    }
    
    public function Run()
    {
        $this->EnableCliMode();
        $this->ValidateCliOptions();

        switch (true) {
            case $this->console->HasArg(array('h', '-help','?')):
                write_line('Usage: httpdmon '.(IS_WINDOWS ? '/?' : '--help'));
                write_line('       httpdmon '.(IS_WINDOWS ? '/u' : '--update'));
                write_line('       httpdmon '.(IS_WINDOWS ? '/v' : '--version'));
                write_line('       httpdmon [options]');
                write_line('  '.str_pad(IS_WINDOWS ? '/a FILES' : '-a FILES', 27).' List of semi-colon separated access log files');
                write_line('  '.str_pad(IS_WINDOWS ? '/c' : '-c', 27).' Make use of colors, even on Windows');
                write_line('  '.str_pad(IS_WINDOWS ? '/d DELAY' : '-d, --delay=DELAY', 27).' Delay between updates in milliseconds (default is 100)');
                write_line('  '.str_pad(IS_WINDOWS ? '/e FILES' : '-e FILES', 27).' List of semi-colon separated error log files');
                write_line('  '.str_pad(IS_WINDOWS ? '/?' : '-h, --help', 27).' Show this help and exit');
                write_line('  '.str_pad(IS_WINDOWS ? '/m' : '-m', 27).' Only show errors (and access entries with status of 400+)');
                write_line('  '.str_pad(IS_WINDOWS ? '/r' : '-r', 27).' Resolve IP Addresses to Hostnames');
                write_line('  '.str_pad(IS_WINDOWS ? '/t' : '-t', 27).' Force plain text (no colors)');
                write_line('  '.str_pad(IS_WINDOWS ? '/u' : '-u, --update', 27).' Attempt program update and exit');
                write_line('  '.str_pad(IS_WINDOWS ? '/v' : '-v, --version', 27).' Show program version and exit');
                break;
            default:
                $this->console->Clear();
                
                $this->errorsOnly = $this->console->HasArg('m');
                $this->resolveIps = $this->console->HasArg('r');
                $this->interval = ($this->console->HasArg('-delay')
                        ? $this->console->GetArg('-delay', 100)
                        : $this->console->GetArg('d', 100)
                    ) * 1000;
                $this->monitors = $this->CreateMonitors();

                $this->DoMainLoop();
                break;
        }
    }
}
