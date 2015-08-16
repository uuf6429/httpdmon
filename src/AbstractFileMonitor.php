<?php

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
     * @return object[]|ArrayObject Parsed data objects.
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
    
    /**
     * @return string
     */
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
