<?php

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
        $this->handled = true;
        $this->HandleException(new ErrorException($mesg, $code, 1, $file, $line));
    }
    
    /**
     * Uhhuh...something went wrong...
     * @param Exception $e Das exception.
     */
    public function HandleException(Exception $e)
    {
        $this->handled = true;
        $con = $this->console;
        $con->WriteLine();
        $con->WriteLine('[' . $con->Colorize('FATAL', 'red') . '] ' . $e->getMessage() . ' (error ' . $e->getCode() . ', ' . basename($e->getFile()) . ':' . $e->getLine() . ')');
        
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
