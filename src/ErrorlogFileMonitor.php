<?php

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
