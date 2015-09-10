<?php

class Console
{
    /**
     * @var boolean
     */
    public $UseColor = true;

    /**
     * @param string $optname
     * @param mixed $default
     */
    public function GetArg($optname, $default = null)
    {
        global $argv;

        if (substr($optname, 0, 1) == '-') {
            // --opt=val
            $optname = '-' . $optname . '=';
            $optlen = strlen($optname);
            foreach ($argv as $arg) {
                if (substr($arg, 0, $optlen) === $optname) {
                    return substr($arg, $optlen);
                }
            }
        } else {
            // -opt val
            $pos = array_search((IS_WINDOWS ? '/' : '-') . $optname, $argv);
            if ($pos !== false                                                  // if argument exists
                && isset($argv[$pos + 1])                                       // .. and there is something after it
                && substr($argv[$pos + 1], 0, 1) != (IS_WINDOWS ? '/' : '-')    // .. and it is not another argument
            ) {
                return $argv[$pos + 1];
            }

        }
        return $default;
    }

    public function Clear()
    {
        if (IS_WINDOWS) {
            // since calling 'cls' doesn't work, we use the following hack...
            for ($l = 0; $l < $this->GetHeight(); $l++) {
                $this->WriteLine(str_pad('', $this->GetWidth(), ' '));
            }
        } else {
            passthru('clear');
        }
    }

    /**
     * Returns whether any of the passed options has been set in CLI or not.
     * @param string|array $option
     * @return boolean
     */
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

    protected $size_cache = null;

    protected function GetSize()
    {
        if (is_null($this->size_cache)) {
            $this->size_cache = array(
                'c' => 20,
                'l' => 4,
            );

            if (IS_WINDOWS) {
                $lines = array();
                exec('mode', $lines);
                foreach ((array)$lines as $line) {
                    foreach (array('Columns' => 'c', 'Lines' => 'l') as $word => $key) {
                        if (strpos($line, $word) !== false) {
                            $tmp = explode(':', $line);
                            $this->size_cache[$key] = max($this->size_cache[$key], (int)trim($tmp[1]));
                        }
                    }
                }
            } else {
                $this->size_cache = array(
                    'c' => max($this->size_cache['c'], (int)exec('tput cols')),
                    'l' => max($this->size_cache['l'], (int)exec('tput lines')),
                );
            }

            $this->size_cache = (object)$this->size_cache;
        }

        return $this->size_cache;
    }

    /**
     * @return integer Number of characters on one line in console window.
     */
    public function GetWidth()
    {
        return $this->GetSize()->c;
    }

    /**
     * @return integer Number of lines in console window.
     */
    public function GetHeight()
    {
        return $this->GetSize()->l;
    }

    protected $parts = 0;
    
    public function ResetParts()
    {
        $this->parts = 0;
    }
    
    public function Write($message = '')
    {
        fwrite(STDOUT, $message);
    }
    
    public function WriteLine($message = '')
    {
        $this->ResetParts();
        $this->Write($message . PHP_EOL);
    }
    
    public function OverwriteLine($message)
    {
        $width = $this->GetWidth();
        $this->WriteLine("\r" . substr(str_pad($message, $width, ' ', STR_PAD_RIGHT), 0, $width));
    }
    
    public function WritePart($parts)
    {
        // find the last line
        $part = explode(PHP_EOL, $parts);
        $part = array_pop($part);

        // count visible chars
        $this->parts += $this->StrLen($part);
        $this->Write($parts);
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
        $color = "\033[" . $color . 'm';
        $reset = "\033[" . self::C_RESET . 'm';
        return !$this->UseColor ? $message : $color . $message . $reset;
    }

    
    /**
     * @param string $url
     * @return string
     */
    protected function ColorizeUrl($url)
    {
        $url = explode('?', $url, 2);

        // colorize file path
        $url[0] = str_replace('/', $this->Colorize('/', Console::C_DARK_GRAY), $url[0]);

        // colorize query
        if (isset($url[1])) {
            $url[1] = explode('&', $url[1]);

            foreach ($url[1] as $i => $kv) {
                $kv = explode('=', $kv, 2);

                // colorize key/value
                $kv[0]=$this->Colorize($kv[0], Console::C_WHITE);
                if (isset($kv[1])) {
                    $kv[1]=$this->Colorize($kv[1], Console::C_LIGHT_GRAY);
                }

                $url[1][$i] = implode($this->Colorize('=', Console::C_DARK_GRAY), $kv);
            }

            $url[1] = implode($this->Colorize('&', Console::C_DARK_GRAY), $url[1]);
        }

        return $this->Colorize(implode($this->Colorize('?', Console::C_DARK_GRAY), $url), Console::C_DARK_GRAY);
    }

    public function ReadLine()
    {
        return fgets(STDIN);
    }
}
