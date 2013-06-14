<?php
	
	define('VERSION', '1.0.3');
	define('IS_WIN', strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
	define('CON_WIDTH', IS_WIN ? 80 : max(80, (int)exec('tput cols')));
	
	// no web access pls!
	
	if(!defined('STDIN') || !isset($argv)){
		echo 'This is a shell script, not a web service.'.PHP_EOL;
		exit(1);
	}
	
	// cli option parser helpers
	
	function cli_get($optname, $default = null){
		global $argv;
		if(substr($optname, 0, 1) == '-'){ // --opt=val
			$optname = '-'.$optname.'=';
			$optlen = strlen($optname);
			foreach($argv as $arg)
				if(substr($arg, 0, $optlen) == $optname)
					return substr($arg, $optlen);
		}else { // -opt val
			$pos = array_search((IS_WIN ? '/' : '-').$optname, $argv);
			if($pos !== false && isset($argv[$pos + 1]) && 
			  substr($argv[$pos + 1], 0, 1) != (IS_WIN ? '/' : '-'))
				return $argv[$pos + 1];
		}
		return $default;
	}
	
	function cli_has($option){
		global $argv;
		return in_array($option, $argv);
	}
	
	// handle some particular cli options
	
	if(cli_has('-v') || cli_has('--version') || cli_has('/v')){
		echo 'httpdmon '.VERSION.PHP_EOL;
		echo 'Copyright (c) 2013-'.@date('Y').' Christian Sciberras'.PHP_EOL;
		exit;
	}
	
	if(cli_has('-h') || cli_has('--help') || cli_has('/?')){
		echo 'Usage: httpdmon '.(IS_WIN ? '/?' : '--help').PHP_EOL;
		echo '       httpdmon '.(IS_WIN ? '/v' : '--version').PHP_EOL;
		echo '       httpdmon [options]'.PHP_EOL;
		echo '  '.str_pad(IS_WIN ? '/a FILES' : '-a FILES', 27).' List of semi-colon separated access log files'.PHP_EOL;
		echo '  '.str_pad(IS_WIN ? '/c' : '-c', 27).' Make use of colors, even on Windows'.PHP_EOL;
		echo '  '.str_pad(IS_WIN ? '/d DELAY' : '-d, --delay=DELAY', 27).' Delay between updates in milliseconds (default is 100)'.PHP_EOL;
		echo '  '.str_pad(IS_WIN ? '/e FILES' : '-e FILES', 27).' List of semi-colon separated error log files'.PHP_EOL;
		echo '  '.str_pad(IS_WIN ? '/?' : '-h, --help', 27).' Show this help and exit'.PHP_EOL;
		echo '  '.str_pad(IS_WIN ? '/m' : '-m', 27).' Only show errors (and access entries with status of 400+)'.PHP_EOL;
		echo '  '.str_pad(IS_WIN ? '/t' : '-t', 27).' Force plain text (no colors)'.PHP_EOL;
		echo '  '.str_pad(IS_WIN ? '/v' : '-v, --version', 27).' Show program version and exit'.PHP_EOL;
		exit;
	}
	
	// set up default options
	
	define('REFRESH_INTERVAL', cli_has('--delay') ? cli_get('-delay', 100) : cli_get('d', 100));    // refresh interval in msec
	define('ACCESSLOG_PATHS', cli_get('a', implode(';', array(                  // semicolon-separated list of access_log paths
		'/var/log/httpd/access_log',                                            // linux
		'/var/www/vhosts/*/statistics/logs/access_log',                         // linux + plesk
		'C:\\Program Files\\Zend\\Apache2\\logs\\access.log',                   // windows + zend
		'C:\\wamp\\logs\\access.log',                                           // windows + wamp
		'/usr/local/apache/logs/access_log'                                     // linux + whm/cpanel
		'/home/*/access-logs/*'                                                 // linux + whm/cpanel
	))));
	define('ERRORLOG_PATHS', cli_get('e', implode(';', array(                   // semicolon-separated list of error_log paths
		'/var/log/httpd/error_log',                                             // linux
		'/var/www/vhosts/*/statistics/logs/error_log',                          // linux + plesk
		'C:\\Program Files\\Zend\\Apache2\\logs\\error.log',                    // windows + zend
		'C:\\wamp\\logs\\apache_error.log',                                     // windows + wamp
		'/usr/local/apache/logs/error_log',                                     // linux + whm/cpanel
	))));
	define('SHOW_ERRORS_ONLY', cli_has('-m') || cli_has('/m'));                 // show errors only
	define('FORCE_COLOR', cli_has('-c') || cli_has('/c'));                      // force colors (on Windows)
	define('FORCE_PLAIN', cli_has('-t') || cli_has('/t'));                      // force colors (on Windows)
	
	if(FORCE_COLOR && FORCE_PLAIN){
		echo 'Please decide if you want colors (-c) or not (-t), not both, Dummkopf!';
		exit(1);
	}
	
	// ensure we can run forever
	
	set_time_limit(0);
	while(ob_get_level())ob_end_flush();
	ob_implicit_flush(true);
	
	// utility classes
	
	class LogLines extends ArrayObject {
		public function __toString(){
			$result = array();
			foreach($this as $line)
				$result[] = $line->raw;
			return implode(PHP_EOL, $result);
		}
	}
	
	class FileMonitor {
		protected $file;
		protected $time = 0;
		protected $size = 0;
		protected $data;
		protected $domain = null;
		
		public function __construct($file){
			$this->file = $file;
			$this->time = filemtime($file);
			$this->size = filesize($file);
		}
		
		public function getDomain(){
			if(is_null($this->domain)){
				$this->domain = preg_match(
					'/\\/vhosts\\/(.+?)\\//',
					$this->file,
					$this->domain
				) ? $this->domain[1] : '';
			}
			return $this->domain;
		}
		
		public function getFilename(){
			return $this->file;
		}
		
		public function hasChanges(){
			$time = filemtime($this->file);
			if($this->time != $time){
				$this->time = $time;
				$size = filesize($this->file);
				if($this->size != $size){
					if(($fh = fopen($this->file, 'rb')) === false)
						throw new Exception("Cannot open file `{$this->file}` for reading");
					fseek($fh, $this->size, SEEK_SET);
					if(($this->data = fread($fh, $size - $this->size)) === false)
						throw new Exception("Cannot read from file `{$this->file}`");
					if(fclose($fh) === false)
						throw new Exception("Cannot close file `{$this->file}`");
					$this->size = $size;
					return true;
				}
			}
			$this->data = '';
			return false;
		}

		public function getChanges(){
			return $this->data;
		}
			
		public function getLines(){
			return explode("\r", str_replace(array("\r\n", "\n"), "\r", $this->data));
		}
	}

	class AccesslogFileMonitor extends FileMonitor {
		public function getLines(){
			// 78.136.44.9 - - [09/Jun/2013:04:10:45 +0100] "GET / HTTP/1.0" 200 6836 "-" "the user agent"
			$result = array();
			$regex = '/^(\S+) (\S+) (\S+) \[([^:]+):(\d+:\d+:\d+) ([^\]]+)\] \"(\S+) (.*?) (\S+)\" (\S+) (\S+) "([^"]*)" "([^"]*)"$/';
			foreach(parent::getLines() as $line)if(trim($line)){
				$new_line = $line;
				if(preg_match($regex, $line, $line)){
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
			return new LogLines($result);
		}
	}
	
	class ErrorlogFileMonitor extends FileMonitor {
		public function getLines(){
			// [Tue Feb 28 11:42:31 2012] [notice] message
			// [Tue Feb 28 14:34:41 2012] [error] [client 192.168.50.10] message
			$result = array();
			$regex = '/^\[([^\]]+)\] \[([^\]]+)\] (?:\[client ([^\]]+)\])?\s*(.*)$/i';
			foreach(parent::getLines() as $line)if(trim($line)){
				$new_line = $line;
				if(preg_match($regex, $line, $line)){
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
			return new LogLines($result);
		}
	}
	
	// error handling mechanism
	
	class ErrorHandler {
		/**
		 * Happily convert errors to exceptions.
		 */
		public static function handle_error($code, $mesg, $file = 'unknown', $line = 0){
			self::handle_exception(new ErrorException($mesg, $code, 1, $file, $line));
		}
		
		/**
		 * Uhhuh...something went wrong...
		 * @param Exception $e Das exception.
		 */
		public static function handle_exception(Exception $e){
			echo PHP_EOL.'['.colorize_message('FATAL', 'red').'] '.$e->getMessage().' (code '.$e->getCode().', line '.$e->getLine().')'.PHP_EOL;
			exit(1); // yeah something broke...
		}
	}
	set_error_handler('ErrorHandler::handle_error');
	set_exception_handler('ErrorHandler::handle_exception');
	
	// some misc CLI routines
	
	function write_line($message){
		echo $message.PHP_EOL;
	}
	
	function colorize_message($message, $color){
		static $colors = array(
			'black' => '0;30',
			'dark_gray' => '1;30',
			'blue' => '0;34',
			'light_blue' => '1;34',
			'green' => '0;32',
			'light_green' => '1;32',
			'cyan' => '0;36',
			'light_cyan' => '1;36',
			'red' => '0;31',
			'light_red' => '1;31',
			'purple' => '0;35',
			'light_purple' => '1;35',
			'brown' => '0;33',
			'yellow' => '1;33',
			'light_gray' => '0;37',
			'white' => '1;37',
		);
		static $reset = "\033[0m";
		$color = isset($colors[$color]) ? "\033[".$colors[$color].'m' : '';
		return FORCE_PLAIN ? $message : (
				((!IS_WIN || FORCE_COLOR) ? $color : '')
				. $message .
				((!IS_WIN || FORCE_COLOR) ? $reset : '')
			);
	}
	
	function overwrite_line($message){
		echo "\r".substr(str_pad($message, CON_WIDTH, ' ', STR_PAD_RIGHT), 0, CON_WIDTH);
	}
	
	// load monitors
	
	$monitors = array();
	foreach(explode(';', ACCESSLOG_PATHS) as $path)
		foreach(glob($path) as $file)
			$monitors[] = new AccesslogFileMonitor($file);
	foreach(explode(';', ERRORLOG_PATHS) as $path)
		foreach(glob($path) as $file)
			$monitors[] = new ErrorlogFileMonitor($file);
	
	// program main loop
	
	while(true){
		foreach($monitors as $monitor){
			if($monitor->hasChanges()){
				switch(true){
					case $monitor instanceof AccesslogFileMonitor:
						foreach($monitor->getLines() as $line){
							if(SHOW_ERRORS_ONLY && $line->code < 400)continue; // not an error empty, go to next entry
							echo '['.colorize_message('ACCESS', 'cyan').'] ';
							echo colorize_message(str_pad($line->ip, 16), 'yellow');
							echo colorize_message(str_pad($monitor->getDomain(), 32), 'brown').' ';
							echo colorize_message(str_pad($line->method, 5), 'light_purple');
							echo colorize_message(str_replace('&', colorize_message('&', 'dark_gray'), $line->url), 'white');
							echo colorize_message(' > ', 'dark_gray');
							echo colorize_message($line->code, $line->code < 400 ? 'green' : 'red');
							echo colorize_message(' (', 'dark_gray').colorize_message($line->size, 'white').colorize_message(' bytes)', 'dark_gray');
							echo PHP_EOL;
						}
						break;
					case $monitor instanceof ErrorlogFileMonitor:
						foreach($monitor->getLines() as $line){
							echo '['.colorize_message('ERROR', 'red').']  ';
							echo colorize_message(str_pad($line->ip, 16), 'yellow');
							echo colorize_message(str_pad($monitor->getDomain(), 32), 'brown').' ';
							echo colorize_message($line->message, 'red');
							echo PHP_EOL;
						}
						break;
					default:
						echo colorize_message('Unknown monitor type', 'red').PHP_EOL;
						break;
				}
			}
		}
		usleep(REFRESH_INTERVAL * 1000);
	}
	
?>
