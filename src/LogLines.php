<?php

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
