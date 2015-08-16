<?php

class AccesslogFileMonitor extends AbstractFileMonitor
{
    protected function ParseChanges($lines)
    {
        $result = array();
        // Example: 78.136.44.9 - - [09/Jun/2013:04:10:45 +0100] "GET / HTTP/1.0" 200 6836 "-" "the user agent"
        $regexp = $this->fileRegexp ? $this->fileRegexp : '/^(\S+) (\S+) (\S+) \[([^:]+):(\d+:\d+:\d+) ([^\]]+)\] \"(\S+) (.*?) (\S+)\" (\S+) (\S+) "([^"]*)" "([^"]*)"$/';

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
            $con->WritePart('[' . $con->Colorize('ACCESS', Console::C_CYAN) . '] ');
            $con->WritePart($con->Colorize($resIps ? substr(str_pad(resolve_ip($line->ip), 48), 0, 48) : str_pad($line->ip, 16), Console::C_YELLOW) . ' ');
            $con->WritePart($con->Colorize(str_pad($line->domain, 32), Console::C_BROWN) . ' ');
            $con->WritePart($con->Colorize(str_pad($line->method, 8), Console::C_LIGHT_PURPLE));
            $long_mesg = ''
                . $con->Colorize(str_replace('&', $con->Colorize('&', Console::C_DARK_GRAY), $line->url), Console::C_WHITE)
                . $con->Colorize(' > ', Console::C_DARK_GRAY)
                . $con->Colorize($line->code, $line->code < 400 ? Console::C_GREEN : Console::C_RED)
                . $con->Colorize(' (', Console::C_DARK_GRAY) . $con->Colorize($line->size, Console::C_WHITE) . $con->Colorize(' bytes)', Console::C_DARK_GRAY)
            ;
            //write_line(implode(str_pad(PHP_EOL, count_parts()), str_split($long_mesg, cli_width() - count_parts())));
            $con->WriteLine($long_mesg);
        }
    }
}
