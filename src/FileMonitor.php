<?php

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
