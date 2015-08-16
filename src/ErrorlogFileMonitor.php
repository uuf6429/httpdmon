<?php

class ErrorlogFileMonitor extends AbstractFileMonitor
{
    protected function ParseChanges($lines)
    {
        // [Tue Feb 28 11:42:31 2012] [notice] message
        // [Tue Feb 28 14:34:41 2012] [error] [client 192.168.50.10] message
        $result = array();
        $regexp = $this->fileRegexp ? $this->fileRegexp : '/^\[([^\]]+)\] \[([^\]]+)\] (?:\[client ([^\]]+)\])?\s*(.*)$/i';

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
            $con->WritePart('[' . $con->Colorize('ERROR', Console::C_RED) . ']  ');
            $con->WritePart($con->Colorize($resIps ? substr(str_pad($this->ResolveIP($line->ip), 48), 0, 48) : str_pad($line->ip, 16), Console::C_YELLOW) . ' ');
            $con->WritePart($con->Colorize(str_pad($line->domain, 32), Console::C_BROWN) . ' ');
            $long_mesg = $con->Colorize($line->message, Console::C_RED);
            //$con->WriteLine(implode(str_pad(PHP_EOL, count_parts()), str_split($long_mesg, cli_width() - count_parts())));
            $con->WriteLine($long_mesg);
        }
    }
}
