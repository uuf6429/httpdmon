<?php

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
                    'return ' . var_export($this->config[$file], true) . ';',
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
