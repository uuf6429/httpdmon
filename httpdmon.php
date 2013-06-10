<?php
	
	// default settings
	
	define('REFRESH_INTERVAL', 50);                                                                        // refresh interval in msec
	define('ACCESSLOG_PATHS', '/var/www/vhosts/*/statistics/logs/access_log;/var/log/httpd/access_log');   // semicolon-separated list of error_log paths
	define('ERRORLOG_PATHS', '/var/www/vhosts/*/statistics/logs/error_log;/var/log/httpd/error_log');      // semicolon-separated list of access_log paths
	define('CON_WIDTH', max(80, (int)exec('tput cols')));                                                  // (automatically) detect console width
	
	// no web access pls!
	
	if(!defined('STDIN') || !isset($argv)){
		echo 'This is a shell script, not a web service.'.PHP_EOL;
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
		return $color . $message . $reset;
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