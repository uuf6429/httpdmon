<?php

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
