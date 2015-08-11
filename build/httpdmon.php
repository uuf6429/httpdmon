<?php

### boot.php

// define some base constants
define('VERSION', '2.0.2');
define('IS_WINDOWS', strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');

// define our (very simplistic) autoloader
function __autoload($class)
{
    require_once('src/' . $class . '.php');
}

### AbstractFileMonitor.php

abstract class AbstractFileMonitor
{
    protected $file;
    protected $time = 0;
    protected $size = 0;
    protected $data;
    protected $hostParser;
    protected $fileParser;
    protected $fileRegexp;
    
    public function __construct($file, $host_parser, $file_parser, $file_regexp)
    {
        $this->file = $file;
        $this->time = filemtime($file);
        $this->size = filesize($file);
        $this->hostParser = $host_parser;
        $this->fileParser = $file_parser;
        $this->fileRegexp = $file_regexp;
    }

    /**
     * Should parse each line into an object, returning an array of these objects.
     * @param string[] $lines Each raw line from log file.
     * @return object[] Parsed data objects.
     */
    abstract protected function ParseChanges($lines);

    /**
     * Displays any new changes on screen (should iterate on ParseChanges() and print out each line accordingly).
     * @param Console $con Screen instance.
     * @param boolean $resIps Resolve hosts from IPs.
     * @param boolean $resIps Display error log entries only.
     */
    abstract public function Display(Console $con, $resIps, $errorsOnly);

    /**
     * Returns domain for specified log entry.
     * @param string $file The current file name.
     * @param object $line A parsed log line.
     * @return mixed The domain or an empty string on failure.
     */
    protected function GetDomain($file, $line)
    {
        $fn = $this->hostParser;
        return $fn ? $fn($file, $line) : $line->domain;
    }

    /**
     * Returns the full path to the file.
     * @return string
     */
    protected function GetFilename()
    {
        return $this->file;
    }
    
    /**
     * Detect if the log file has changed (and if it has, read the new data).
     * @return bool True if log file changed, false otherwise.
     */
    public function HasChanges()
    {
        $time = filemtime($this->file);

        if ($this->time != $time) {
            $this->time = $time;
            $size = filesize($this->file);

            if ($this->size != $size) {
                if ($this->size > $size) {
                    $this->size = 0; // file has been truncated, reset read pointer
                }

                if (($fh = fopen($this->file, 'rb')) === false) {
                    throw new Exception("Cannot open file `{$this->file}` for reading");
                }

                fseek($fh, $this->size, SEEK_SET);

                if (($this->data = fread($fh, $size - $this->size)) === false) {
                    throw new Exception("Cannot read from file `{$this->file}`");
                }

                if (fclose($fh) === false) {
                    throw new Exception("Cannot close file `{$this->file}`");
                }

                $this->size = $size;

                return true;
            }
        }
        
        $this->data = '';
        
        return false;
    }
    
    /**
     * Retrieve modified content in file.
     * @return string
     */
    protected function GetChanges()
    {
        return $this->data;
    }
    
    /**
     * Retrieves lines from file data in a platform-independent manner.
     * @return array Array of lines.
     */
    protected function GetChangedLines()
    {
        $fn = $this->fileParser;
        $lines = explode("\r", str_replace(array("\r\n", "\n"), "\r", $this->GetChanges()));
        return $fn ? $fn($this->file, $lines) : $this->ParseChanges($lines);
    }
    
    protected function ResolveIP($ip)
    {
        static $cache = array();
        static $ttl = 60000; // live for one minute
        $now = time();
        if (!trim($ip)) {
            return 'unknown';
        }
        if (!isset($cache[$ip]) || $cache[$ip][1] < $now - $ttl) {
            $cache[$ip] = array(gethostbyaddr($ip), $now);
        }
        return $cache[$ip][0];
    }
}

### AccesslogFileMonitor.php

class AccesslogFileMonitor extends AbstractFileMonitor
{
    protected function ParseChanges($lines)
    {
        // 78.136.44.9 - - [09/Jun/2013:04:10:45 +0100] "GET / HTTP/1.0" 200 6836 "-" "the user agent"
        $result = array();
        $regexp = $this->fileRegexp? $this->fileRegexp: '/^(\S+) (\S+) (\S+) \[([^:]+):(\d+:\d+:\d+) ([^\]]+)\] \"(\S+) (.*?) (\S+)\" (\S+) (\S+) "([^"]*)" "([^"]*)"$/';

        foreach ($lines as $line) {
            if (trim($line)) {
                $new_line = $line;
                if (preg_match($regexp, $line, $line)) {
                    $new_line = array(
                        'raw'       => $new_line,
                        'ip'        => $line[1],
                        'ident'     => $line[2],
                        'auth'      => $line[3],
                        'date'      => $line[4],
                        'time'      => $line[5],
                        'zone'      => $line[6],
                        'method'    => $line[7],
                        'url'       => $line[8],
                        'proto'     => $line[9],
                        'code'      => $line[10],
                        'size'      => $line[11],
                        'referer'   => $line[12],
                        'agent'     => $line[13],
                        'domain'    => '',
                    );
                    $result[] = (object)$new_line;
                }
            }
        }

        return new LogLines($result);
    }
    
    public function Display(Console $con, $resIps, $errorsOnly)
    {
        foreach ($this->GetChangedLines() as $line) {
            if ($errorsOnly && $line->code < 400) {
                continue; // not an error entry, go to next one
            }
            $con->WritePart('['.$con->Colorize('ACCESS', Console::C_CYAN).'] ');
            $con->WritePart($con->Colorize($resIps ? substr(str_pad(resolve_ip($line->ip), 48), 0, 48) : str_pad($line->ip, 16), Console::C_YELLOW).' ');
            $con->WritePart($con->Colorize(str_pad($line->domain, 32), Console::C_BROWN).' ');
            $con->WritePart($con->Colorize(str_pad($line->method, 8), Console::C_LIGHT_PURPLE));
            $long_mesg = ''
                . $con->Colorize(str_replace('&', $con->Colorize('&', Console::C_DARK_GRAY), $line->url), Console::C_WHITE)
                . $con->Colorize(' > ', Console::C_DARK_GRAY)
                . $con->Colorize($line->code, $line->code < 400 ? Console::C_GREEN : Console::C_RED)
                . $con->Colorize(' (', Console::C_DARK_GRAY).$con->Colorize($line->size, Console::C_WHITE).$con->Colorize(' bytes)', Console::C_DARK_GRAY)
            ;
            //write_line(implode(str_pad(PHP_EOL, count_parts()), str_split($long_mesg, cli_width() - count_parts())));
            $con->WriteLine($long_mesg);
        }
    }
}

### Config.php

class Config
{
    protected $config = array();

    public function __construct($files = array())
    {
        foreach ($files as $file) {
            $this->Load($file);
        }
    }

    protected function GetRealFile($file)
    {
        return realpath($file);
    }

    public function Load($file)
    {
        $file = $this->GetRealFile($file);
        $this->config[$file] = include($file);
    }

    public function Save($file)
    {
        $file = $this->GetRealFile($file);
        file_put_contents($file, implode(
            PHP_EOL,
            array(
                    '<?php',
                    PHP_EOL,
                    'return '.var_export($this->config[$file], true).';',
                    PHP_EOL,
                    '?>'
                )
        ));
    }

    public function GetFullConfig()
    {
        $result = array();
        foreach ($this->config as $config) {
            $result = array_merge($result, $config);
        }
        return $result;
    }

    public function GetMonitorConfig()
    {
        return array_filter($this->GetFullConfig(), 'is_array');
    }
}

### Console.php

class Console
{
    public function GetArg($optname, $default = null)
    {
        global $argv;

        if (substr($optname, 0, 1) == '-') {
            // --opt=val
            $optname = '-' . $optname . '=';
            $optlen = strlen($optname);
            foreach ($argv as $arg) {
                if (substr($arg, 0, $optlen) == $optname) {
                    return substr($arg, $optlen);
                }
            }
        } else {
            // -opt val
            $pos = array_search((IS_WINDOWS ? '/' : '-').$optname, $argv);
            if ($pos !== false && isset($argv[$pos + 1]) && substr($argv[$pos + 1], 0, 1) != (IS_WINDOWS ? '/' : '-')) {
                return $argv[$pos + 1];
            }
        }
        return $default;
    }

    public function Clear()
    {
        if (IS_WINDOWS) {
            // since calling 'cls' doesn't work, we use the following hack...
            for ($l=0; $l<$this->GetHeight(); $l++) {
                $this->WriteLine(str_pad('', $this->GetWidth(), ' '));
            }
        } else {
            passthru('clear');
        }
    }

    public function HasArg($option)
    {
        global $argv;

        if (!is_array($option)) {
            return $this->HasArg(array($option));
        }

        foreach ($option as $opt) {
            if (in_array((IS_WINDOWS ? '/' : '-') . $opt, $argv)) {
                return true;
            }
        }

        return false;
    }

    public function GetWidth()
    {
        static $cache = null;
        if (is_null($cache)) {
            if (IS_WINDOWS) {
                $lines = array();
                exec('mode', $lines);
                foreach ($lines as $line) {
                    if (strpos($line, 'Columns') !== false) {
                        $cache = explode(':', $line);
                        $cache = (int)trim($cache[1]);
                        break;
                    }
                }
            } else {
                $cache = (int)exec('tput cols');
            }
            $cache = max($cache, 20);
        }
        return $cache;
    }

    public function GetHeight()
    {
        static $cache = null;
        if (is_null($cache)) {
            if (IS_WINDOWS) {
                $lines = array();
                exec('mode', $lines);
                foreach ($lines as $line) {
                    if (strpos($line, 'Lines') !== false) {
                        $cache = explode(':', $line);
                        $cache = (int)trim($cache[1]);
                        break;
                    }
                }
            } else {
                $cache = max(4, (int)exec('tput lines'));
            }
        }
        return $cache;
    }


    protected $parts = 0;
    
    public function ResetParts()
    {
        $this->parts = 0;
    }
    
    public function WriteLine($message = '')
    {
        $this->ResetParts();
        echo $message.PHP_EOL;
    }
    
    public function WritePart($parts)
    {
        // find the last line
        $part = explode(PHP_EOL, $parts);
        $part = array_pop($part);

        // count visible chars
        $this->parts += $this->StrLen($part);
        echo $parts;
    }
    
    /**
     * Strip ansi escape characters from string and return length.
     * @param string $str
     * @return int
     */
    public function StrLen($str)
    {
        $str = preg_replace('/\033\[[0-9;]*m/', '', $str);
        return strlen($str);
    }
    
    public function CountParts()
    {
        return $this->parts;
    }
    
    const C_BLACK      = '0;30'
    ,   C_DARK_GRAY    = '1;30'
    ,   C_RED          = '0;31'
    ,   C_LIGHT_RED    = '1;31'
    ,   C_GREEN        = '0;32'
    ,   C_LIGHT_GREEN  = '1;32'
    ,   C_BROWN        = '0;33'
    ,   C_YELLOW       = '1;33'
    ,   C_BLUE         = '0;34'
    ,   C_LIGHT_BLUE   = '1;34'
    ,   C_PURPLE       = '0;35'
    ,   C_LIGHT_PURPLE = '1;35'
    ,   C_CYAN         = '0;36'
    ,   C_LIGHT_CYAN   = '1;36'
    ,   C_LIGHT_GRAY   = '0;37'
    ,   C_WHITE        = '1;37'
    ,   C_RESET        = '0'
    ;

    public function Colorize($message, $color)
    {
        $colorize = !(defined('FORCE_PLAIN') && FORCE_PLAIN) && (!IS_WINDOWS || (defined('FORCE_COLOR') && FORCE_COLOR));
        $color = "\033[" . $color . 'm';
        $reset = "\033[" . self::C_RESET . 'm';
        return !$colorize ? $message : $color . $message . $reset;
    }
    
    public function OverwriteLine($message)
    {
        echo "\r" . substr(str_pad($message, cli_width(), ' ', STR_PAD_RIGHT), 0, cli_width());
    }

    public function ReadLine()
    {
        return fgets(STDIN);
    }
}

### ErrorHandler.php

/**
 * ErrorHandler short summary.
 *
 * ErrorHandler description.
 *
 * @version 1.0
 * @author Christian
 */
class ErrorHandler
{
    /**
     * @var Console
     */
    protected $console;
    
    protected $handled = false;

    public function __construct($console)
    {
        $this->console = $console;
    }
    
    /**
     * Happily convert errors to exceptions.
     */
    public function HandleError($code, $mesg, $file = 'unknown', $line = 0)
    {
        $this->handled=true;
        $this->HandleException(new ErrorException($mesg, $code, 1, $file, $line));
    }
    
    /**
     * Uhhuh...something went wrong...
     * @param Exception $e Das exception.
     */
    public function HandleException(Exception $e)
    {
        $this->handled=true;
        $con = $this->console;
        $con->WriteLine();
        $con->WriteLine('[' . $con->Colorize('FATAL', 'red') . '] ' . $e->getMessage() . ' (error ' . $e->getCode() . ', '.basename($e->getFile()).':' . $e->getLine() . ')');
        
        $con->WriteLine('Press [ENTER] to continue...');
        $con->ReadLine();

        exit(1); // yeah something broke...
    }

    /**
     * Handle shutdown errors.
     */
    public function HandleShutdown()
    {
        $err = error_get_last();
        if ($err && !$this->handled) {
            $this->HandleError($err['type'], $err['message'], $err['file'], $err['line']);
        }
    }
    
    /**
     * Attach to PHP events of interest.
     */
    public function Attach()
    {
        set_error_handler(array($this, 'HandleError'));
        set_exception_handler(array($this, 'HandleException'));
        register_shutdown_function(array($this, 'HandleShutdown'));
    }
}

### ErrorlogFileMonitor.php

class ErrorlogFileMonitor extends AbstractFileMonitor
{
    protected function ParseChanges($lines)
    {
        // [Tue Feb 28 11:42:31 2012] [notice] message
        // [Tue Feb 28 14:34:41 2012] [error] [client 192.168.50.10] message
        $result = array();
        $regexp = $this->fileRegexp? $this->fileRegexp: '/^\[([^\]]+)\] \[([^\]]+)\] (?:\[client ([^\]]+)\])?\s*(.*)$/i';

        foreach ($lines as $line) {
            if (trim($line)) {
                $new_line = $line;
                if (preg_match($regexp, $line, $line)) {
                    $new_line = array(
                        'raw'       => $new_line,
                        'date'      => $line[1],
                        'type'      => $line[2],
                        'ip'        => $line[3],
                        'message'   => $line[4],
                        'domain'    => '',
                    );
                    $result[] = (object)$new_line;
                }
            }
        }
        
        return new LogLines($result);
    }
    
    public function Display(Console $con, $resIps, $errorsOnly)
    {
        foreach ($this->GetChangedLines() as $line) {
            $con->WritePart('['.$con->Colorize('ERROR', Console::C_RED).']  ');
            $con->WritePart($con->Colorize($resIps ? substr(str_pad($this->ResolveIP($line->ip), 48), 0, 48) : str_pad($line->ip, 16), Console::C_YELLOW).' ');
            $con->WritePart($con->Colorize(str_pad($line->domain, 32), Console::C_BROWN).' ');
            $long_mesg = $con->Colorize($line->message, Console::C_RED);
            //$con->WriteLine(implode(str_pad(PHP_EOL, count_parts()), str_split($long_mesg, cli_width() - count_parts())));
            $con->WriteLine($long_mesg);
        }
    }
}

### HttpdMon.php

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
            $host_parser = isset($cfg['host_parser']) ? create_function('$file,$line', $cfg['host_parser']) : null;
            $file_parser = isset($cfg['file_parser']) ? create_function('$file,$lines', $cfg['file_parser']) : null;
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
        $con->WriteLine('Usage: httpdmon '.(IS_WINDOWS ? '/?' : '--help'));
        $con->WriteLine('       httpdmon '.(IS_WINDOWS ? '/u' : '--update'));
        $con->WriteLine('       httpdmon '.(IS_WINDOWS ? '/v' : '--version'));
        $con->WriteLine('       httpdmon [options]');
        $con->WriteLine('  '.str_pad(IS_WINDOWS ? '/c' : '-c', 27).' Make use of colors, even on Windows (requires ansicon or similar)');
        $con->WriteLine('  '.str_pad(IS_WINDOWS ? '/d DELAY' : '-d, --delay=DELAY', 27).' Delay between updates in milliseconds (default is 100)');
        $con->WriteLine('  '.str_pad(IS_WINDOWS ? '/?' : '-h, --help', 27).' Show this help and exit');
        $con->WriteLine('  '.str_pad(IS_WINDOWS ? '/m' : '-m', 27).' Only show errors (and access entries with status of 400+)');
        $con->WriteLine('  '.str_pad(IS_WINDOWS ? '/r' : '-r', 27).' Resolve IP Addresses to Hostnames');
        $con->WriteLine('  '.str_pad(IS_WINDOWS ? '/t' : '-t', 27).' Force plain text (no colors)');
        $con->WriteLine('  '.str_pad(IS_WINDOWS ? '/u' : '-u, --update', 27).' Attempt program update and exit');
        $con->WriteLine('  '.str_pad(IS_WINDOWS ? '/v' : '-v, --version', 27).' Show program version and exit');
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
        $this->interval = ($this->console->HasArg('-delay')
                ? $this->console->GetArg('-delay', 100)
                : $this->console->GetArg('d', 100)
            ) * 1000;
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
            'current_version' => '0.0.0',                                                           // Version of the current file/script.
            'version_regex' => '/define\\(\\s*[\'"]version[\'"]\\s*,\\s*[\'"](.*?)[\'"]\\s*\\)/i',  // Regular expression for finding version in target file.
            'try_run' => true,                                                                      // Try running downloaded file to ensure it works.
            'on_event' => create_function('', ''),                                                  // Used by updater to notify callee on event changes.
            'target_file' => $_SERVER['SCRIPT_FILENAME'],                                           // The file to be overwritten by the updater.
            'force_update' => false,                                                                // Force local file to be overwritten by remote file regardless of version.
            'try_run_cmd' => null,                                                                  // Command called to verify the upgrade is fine.
        ), (array)$options);
        if (is_null($options['try_run_cmd'])) { // build command with the correct target_file
            $options['try_run_cmd'] = 'php -f '.escapeshellarg($options['target_file']);
        }
        $notify = $options['on_event'];
        $rollback = false;
        $next_version = null;
        static $intentions = array(-1=>'fail',0=>'ignore',1=>'update');
        
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
        
        if (!copy($options['target_file'], $options['target_file'].'.bak')) {
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
            if ($exit != 0) {
                $notify('warn', array('reason'=>'Downloaded update seems to be broken', 'output'=>$out, 'exitcode'=>$exit));
                $rollback = true;
            }
        }
        
        if ($rollback) {
            $notify('before_rollback', array('options'=>$options));
            if (!rename($options['target_file'].'.bak', $options['target_file'])) {
                return $notify('error', array('reason'=>'Rollback operation failed', 'target'=>$options['target_file'].'.bak')) && false;
            }
            $notify('after_rollback', array('options'=>$options));
        } else {
            if (!unlink($options['target_file'].'.bak')) {
                $notify('warn', array('reason'=>'Cleanup operation failed', 'target'=>$options['target_file'].'.bak'));
            }
            $notify('finish', array('new_version'=>$next_version));
        }

        return null;
    }

    /**
     * Event handler for script updater.
     */
    function HandleUpdateScriptEvent($event, $args = array())
    {
        $con = $this->console;
        switch ($event) {
            case 'error':
                $con->WriteLine('['.$con->Colorize('FATAL', Console::C_RED).'] '.$args['reason']);
                break;
            case 'warn':
                $con->WriteLine('['.$con->Colorize('WARNING', Console::C_YELLOW).'] '.$args['reason']);
                break;
            case 'version_check':
                switch ($args['intention']) {
                    case 'update':
                        $con->WriteLine('Updating to '.$args['next_version'].'...');
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
                    $args['data'] = '#!/usr/bin/php -q'.PHP_EOL.$args['data'];
                }
                break;
            case 'before_try':
                $con->WriteLine('Testing downloaded update...');
                break;
            case 'finish':
                $con->WriteLine('Update completed successfully.');
                $con->WriteLine('Welcome to '.basename(__FILE__, '.php').' '.$args['new_version'].'!');
                break;
        }
    }

    protected function RunUpdater()
    {
        $this->UpdateScript(
            'https://raw.github.com/uuf6429/httpdmon/master/build/httpdmon.php?nc='.mt_rand(),
            array(
                'current_version' => VERSION,
                'try_run' => true,
                'try_run_cmd' => 'php -f ' . escapeshellarg(__FILE__) . ' -- ' . (IS_WINDOWS ? '-' : '/') . 'v',
                'on_event' => array($this, 'HandleUpdateScriptEvent'),
            )
        );
    }

    public function Run()
    {
        $this->EnableCliMode();
        $this->ValidateCliOptions();

        switch (true) {
            case $this->console->HasArg(array('h', '-help','?')):
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

### LogLines.php

class LogLines extends ArrayObject
{
    public function __toString()
    {
        $result = array();
        
        foreach ($this as $line) {
            $result[] = $line->raw;
        }

        return implode(PHP_EOL, $result);
    }
}

### init.php

// ensure we have booted up
if (!defined('VERSION')) {
    require_once('boot.php');
}

// wrap the console
$con = new Console();

// do error handling
$err = new ErrorHandler($con);
$err->Attach();

// load configuration
$cfg = new Config(glob('httpdmon.d/*.php'));

// run application
$app = new HttpdMon($cfg, $con);
$app->Run();
