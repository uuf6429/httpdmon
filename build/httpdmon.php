<?php

### AccesslogFileMonitor.php

class AccesslogFileMonitor extends FileMonitor
{
    public function getLines()
    {
        // 78.136.44.9 - - [09/Jun/2013:04:10:45 +0100] "GET / HTTP/1.0" 200 6836 "-" "the user agent"
        $result = array();
        $regex = '/^(\S+) (\S+) (\S+) \[([^:]+):(\d+:\d+:\d+) ([^\]]+)\] \"(\S+) (.*?) (\S+)\" (\S+) (\S+) "([^"]*)" "([^"]*)"$/';
        foreach (parent::getLines() as $line) {
            if (trim($line)) {
                $new_line = $line;
                if (preg_match($regex, $line, $line)) {
                    $new_line = array(
                    'raw' => $new_line,
                    'ip' => $line[1],
                    'ident' => $line[2],
                    'auth' => $line[3],
                    'date' => $line[4],
                    'time' => $line[5],
                    'zone' => $line[6],
                    'method' => $line[7],
                    'url' => $line[8],
                    'proto' => $line[9],
                    'code' => $line[10],
                    'size' => $line[11],
                    'referer' => $line[12],
                    'agent' => $line[13],
                    );
                    $result[] = (object)$new_line;
                }
            }
        }
        return new LogLines($result);
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
        $this->parts += cli_strlen($part);
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

class ErrorlogFileMonitor extends FileMonitor
{
    public function getLines()
    {
        // [Tue Feb 28 11:42:31 2012] [notice] message
        // [Tue Feb 28 14:34:41 2012] [error] [client 192.168.50.10] message
        $result = array();
        $regex = '/^\[([^\]]+)\] \[([^\]]+)\] (?:\[client ([^\]]+)\])?\s*(.*)$/i';
        foreach (parent::getLines() as $line) {
            if (trim($line)) {
                $new_line = $line;
                if (preg_match($regex, $line, $line)) {
                    $new_line = array(
                    'raw' => $new_line,
                    'date' => $line[1],
                    'type' => $line[2],
                    'ip' => $line[3],
                    'message' => $line[4],
                    );
                    $result[] = (object)$new_line;
                }
            }
        }
        return new LogLines($result);
    }
}

### FileMonitor.php

class FileMonitor
{
    protected $file;
    protected $time = 0;
    protected $size = 0;
    protected $data;
    protected $domain = null;
    
    public function __construct($file)
    {
        $this->file = $file;
        $this->time = filemtime($file);
        $this->size = filesize($file);
    }
    
    /**
     * Parse domain out of file path.
     * @todo This only works with systems like plesk, where log files are stored in directories named for the respective vhost domain.
     * Ideally, there should be some sort of strategy system to cater for other scenarios.
     * @return mixed The domain or an empty string on failure.
     */
    public function getDomain()
    {
        if (is_null($this->domain)) {
            $this->domain = preg_match(
                '/\\/vhosts\\/(.+?)\\//',
                $this->file,
                $this->domain
            ) ? $this->domain[1] : '';
        }
        return $this->domain;
    }
    
    /**
     * Returns the full path to the file.
     * @return string
     */
    public function getFilename()
    {
        return $this->file;
    }
    
    /**
     * Detect if the log file has changed (and if it has, read the new data).
     * @return bool True if log file changed, false otherwise.
     */
    public function hasChanges()
    {
        $time = filemtime($this->file);
        if ($this->time != $time) {
            $this->time = $time;
            $size = filesize($this->file);
            if ($this->size != $size) {
                if ($this->size > $size) {
                    $this->size = 0; // file has been truncated, reset read pointer
                }                if (($fh = fopen($this->file, 'rb')) === false) {
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
    public function getChanges()
    {
        return $this->data;
    }
    
    /**
     * Retrieves lines from file data in a platform-independent manner.
     * @return array Array of lines.
     */
    public function getLines()
    {
        return explode("\r", str_replace(array("\r\n", "\n"), "\r", $this->data));
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

### boot.php

// define some base constants
define('IS_WINDOWS', strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');

// define autoloader if not loaded
if (!class_exists('HttpdMon')) {
    function __autoload($class)
    {
        require_once('src/' . $class . '.php');
    }
}

// wrap the console
$con = new Console();

// do error handling
$err = new ErrorHandler($con);
$err->Attach();

// load configuration
$cfg = new Config(glob('conf.d/*.php'));

// run application
$app = new HttpdMon($cfg, $con);
$app->Run();
