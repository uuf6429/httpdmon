<?php
	
	define('VERSION', '1.2.6');
	
	### FUNCTION / CLASS DECLERATIONS ###
	
	// misc functions
	
	function is_windows(){
		static $cache = null;
		if(is_null($cache)){
			$cache = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
		}
		return $cache;
	}
	
	/**
	 * Attempts to update current file from URL.
	 * @param string $update_url Target URL to read updates from.
	 * @param array|object $options Array of options (see docs for more info).
	 */
	function update_script($update_url, $options){
		// initialize
		$options = array_merge(array(
			'current_version' => '0.0.0',															// Version of the current file/script.
			'version_regex' => '/define\\(\\s*[\'"]version[\'"]\\s*,\\s*[\'"](.*?)[\'"]\\s*\\)/i',	// Regular expression for finding version in target file.
			'try_run' => true,																		// Try running downloaded file to ensure it works.
			'on_event' => create_function('', ''),													// Used by updater to notify callee on event changes.
			'target_file' => $_SERVER['SCRIPT_FILENAME'],											// The file to be overwritten by the updater.
			'force_update' => false,																// Force local file to be overwritten by remote file regardless of version.
			'try_run_cmd' => null,																	// Command called to verify the upgrade is fine.
		), (array)$options);
		if(is_null($options['try_run_cmd'])) // build command with the correct target_file
			$options['try_run_cmd'] = 'php -f '.escapeshellarg($options['target_file']);
		$notify = $options['on_event'];
		$rollback = false;
		$next_version = null;
		static $intentions = array(-1=>'fail',0=>'ignore',1=>'update');
		// process
		$notify('start');
		$notify('before_download', array('url'=>$update_url));
		if(!($data = file_get_contents($update_url)))
			return $notify('error', array('reason'=>'File download failed', 'target'=>$update_url)) && false;
		$notify('after_download', array('data'=>&$data));
		if(!preg_match($options['version_regex'], $data, $next_version))
			return $notify('error', array('reason'=>'Could not determine version of target file', 'target'=>$data, 'result'=>$next_version)) && false;
		if(!($next_version = array_pop($next_version)))
			return $notify('error', array('reason'=>'Version of target file is empty', 'target'=>$data, 'result'=>$next_version)) && false;
		$v_diff = version_compare($next_version, $options['current_version']);
		$should_fail = $notify('version_check', array('intention'=>$intentions[$v_diff], 'curr_version'=>$options['current_version'], 'next_version'=>$next_version));
		if($should_fail === false)
			return $notify('error', array('reason'=>'Update cancelled by user code')) && false;
		if($v_diff === 0 && !$options['force_update'])
			return $notify('already_uptodate') && false;
		if($v_diff === -1 && !$options['force_update'])
			return $notify('warn', array('reason'=>'Local file is newer than remote one', 'curr_version'=>$options['current_version'], 'next_version'=>$next_version)) && false;
		if(!copy($options['target_file'], $options['target_file'].'.bak'))
			$notify('warn', array('reason'=>'Backup operation failed', 'target'=>$options['target_file']));
		if(!file_put_contents($options['target_file'], $data)){
			$notify('warn', array('reason'=>'Failed writing to file', 'target'=>$options['target_file']));
			$rollback = true;
		}
		if(!$rollback && $options['try_run']){
			$notify('before_try', array('options'=>$options));
			ob_start();
			$exit = null;
			passthru($options['try_run_cmd'], $exit);
			$out = ob_get_clean();
			$notify('after_try', array('options'=>$options, 'output'=>$out, 'exitcode'=>$exit));
			if($exit != 0){
				$notify('warn', array('reason'=>'Downloaded update seems to be broken', 'output'=>$out, 'exitcode'=>$exit));
				$rollback = true;
			}
		}
		if($rollback){
			$notify('before_rollback', array('options'=>$options));
			if(!rename($options['target_file'].'.bak', $options['target_file']))
				return $notify('error', array('reason'=>'Rollback operation failed', 'target'=>$options['target_file'].'.bak')) && false;
			$notify('after_rollback', array('options'=>$options));
			return;
		}
		if(!unlink($options['target_file'].'.bak'))
			$notify('warn', array('reason'=>'Cleanup operation failed', 'target'=>$options['target_file'].'.bak'));
		$notify('finish', array('new_version'=>$next_version));
	}
	
	// cli functions
	
	function cli_get($optname, $default = null){
		global $argv;
		if(substr($optname, 0, 1) == '-'){ // --opt=val
			$optname = '-'.$optname.'=';
			$optlen = strlen($optname);
			foreach($argv as $arg)
				if(substr($arg, 0, $optlen) == $optname)
					return substr($arg, $optlen);
		}else { // -opt val
			$pos = array_search((is_windows() ? '/' : '-').$optname, $argv);
			if($pos !== false && isset($argv[$pos + 1]) && 
			  substr($argv[$pos + 1], 0, 1) != (is_windows() ? '/' : '-'))
				return $argv[$pos + 1];
		}
		return $default;
	}
	
	function cli_has($option){
		global $argv;
		return in_array($option, $argv);
	}
	
	function cli_width(){
		static $cache = null;
		if(is_null($cache)){
			if(is_windows()){
				$cache = 80; // TODO
			}else{
				$cache = max(20, (int)exec('tput cols'));
			}
		}
		return $cache;
	}
	
	function cli_height(){
		static $cache = null;
		if(is_null($cache)){
			if(is_windows()){
				$cache = 4; // TODO
			}else{
				$cache = max(4, (int)exec('tput lines'));
			}
		}
		return $cache;
	}
	
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
			write_line();
			write_line('['.colorize_message('FATAL', 'red').'] '.$e->getMessage().' (code '.$e->getCode().', line '.$e->getLine().')');
			exit(1); // yeah something broke...
		}
	}
	set_error_handler('ErrorHandler::handle_error');
	set_exception_handler('ErrorHandler::handle_exception');
	
	// ip resolver (and cacher)
	
	function resolve_ip($ip){
		static $cache = array();
		static $ttl = 60000; // live for one minute
		$now = time();
		if(!trim($ip))return 'unknown';
		if(!isset($cache[$ip]) || $cache[$ip][1] < $now - $ttl){
			$cache[$ip] = array(gethostbyaddr($ip), $now);
		}
		return $cache[$ip][0];
	}
	
	// some misc CLI routines
	
	function reset_parts(){
		$GLOBALS['parts'] = 0;
	}
	reset_parts();
	
	function write_line($message = ''){
		reset_parts();
		echo $message.PHP_EOL;
	}
	
	function write_part($parts){
		// find the last line
		$part = explode(PHP_EOL, $parts);
		$part = array_pop($part);
		// count visible chars
		$GLOBALS['parts'] += cli_strlen($part);
		echo $parts;
	}
	
	function cli_strlen($str){
		// strip ansi escape characters from string
		$str = preg_replace('/\033\[[0-9;]*m/', '', $str);
		return strlen($str);
	}
	
	function count_parts(){
		return $GLOBALS['parts'];
	}
	
	function colorize_message($message, $color){
		static $colors = array(
			'black'        => '0;30',
			'dark_gray'    => '1;30',
			'red'          => '0;31',
			'light_red'    => '1;31',
			'green'        => '0;32',
			'light_green'  => '1;32',
			'brown'        => '0;33',
			'yellow'       => '1;33',
			'blue'         => '0;34',
			'light_blue'   => '1;34',
			'purple'       => '0;35',
			'light_purple' => '1;35',
			'cyan'         => '0;36',
			'light_cyan'   => '1;36',
			'light_gray'   => '0;37',
			'white'        => '1;37',
		);
		static $reset    = "\033[0m";
		$color = strtolower(str_replace(array(' ', '-'), '_', trim($color)));
		$color = isset($colors[$color]) ? "\033[".$colors[$color].'m' : '';
		return defined('FORCE_PLAIN') && FORCE_PLAIN ? $message : (
				((!is_windows() || (defined('FORCE_COLOR') && FORCE_COLOR)) ? $color : '')
				. $message .
				((!is_windows() || (defined('FORCE_COLOR') && FORCE_COLOR)) ? $reset : '')
			);
	}
	
	function overwrite_line($message){
		echo "\r".substr(str_pad($message, cli_width(), ' ', STR_PAD_RIGHT), 0, cli_width());
	}
	
	### PROGRAM INITIALIZATION ###
	
	// no web access pls!
	if(isset($_SERVER['SERVER_NAME']) || !isset($argv)){
		write_line('This is a shell script, not a web service.');
		exit(1);
	}
	
	// handle some particular cli options
	if(cli_has('-h') || cli_has('--help') || cli_has('/?')){
		write_line('Usage: httpdmon '.(is_windows() ? '/?' : '--help'));
		write_line('       httpdmon '.(is_windows() ? '/u' : '--update'));
		write_line('       httpdmon '.(is_windows() ? '/v' : '--version'));
		write_line('       httpdmon [options]');
		write_line('  '.str_pad(is_windows() ? '/a FILES' : '-a FILES', 27).' List of semi-colon separated access log files');
		write_line('  '.str_pad(is_windows() ? '/c' : '-c', 27).' Make use of colors, even on Windows');
		write_line('  '.str_pad(is_windows() ? '/d DELAY' : '-d, --delay=DELAY', 27).' Delay between updates in milliseconds (default is 100)');
		write_line('  '.str_pad(is_windows() ? '/e FILES' : '-e FILES', 27).' List of semi-colon separated error log files');
		write_line('  '.str_pad(is_windows() ? '/?' : '-h, --help', 27).' Show this help and exit');
		write_line('  '.str_pad(is_windows() ? '/m' : '-m', 27).' Only show errors (and access entries with status of 400+)');
		write_line('  '.str_pad(is_windows() ? '/r' : '-r', 27).' Resolve IP Addresses to Hostnames');
		write_line('  '.str_pad(is_windows() ? '/t' : '-t', 27).' Force plain text (no colors)');
		write_line('  '.str_pad(is_windows() ? '/u' : '-u, --update', 27).' Attempt program update and exit');
		write_line('  '.str_pad(is_windows() ? '/v' : '-v, --version', 27).' Show program version and exit');
		exit;
	}
	if(cli_has('/u') || cli_has('--update') || cli_has('-u')){
		function event_handler($event, $args=array()){
			switch($event){
				case 'error':
					write_line('['.colorize_message('FATAL', 'red').'] '.$args['reason']);
					break;
				case 'warn':
					write_line('['.colorize_message('WARNING', 'yellow').'] '.$args['reason']);
					break;
				case 'version_check':
					switch($args['intention']){
						case 'update':
							write_line('Updating to '.$args['next_version'].'...');
							break;
						case 'ignore':
							write_line('Already up to date');
							break;
						case 'fail':
							write_line('Your version is newer');
							break;
					}
					break;
				case 'after_download':
					// prepends to downloaded data if current file currently uses it
					if(substr(file_get_contents(__FILE__), 0, 14) == '#!/usr/bin/php')
						$args['data'] = '#!/usr/bin/php -q'.PHP_EOL.$args['data'];
					break;
				case 'before_try':
					write_line('Testing downloaded update...');
					break;
				case 'finish':
					write_line('Update completed successfully.');
					write_line('Welcome to '.basename(__FILE__, '.php').' '.$args['new_version'].'!');
					break;
			}
		}
		update_script(
			'https://raw.github.com/uuf6429/httpdmon/master/httpdmon.php?nc='.mt_rand(),
			array(
				'current_version' => VERSION,
				'try_run' => true,
				'try_run_cmd' => 'php -f '.escapeshellarg(__FILE__).' -- /v',
				'on_event' => 'event_handler',
			)
		);
		exit(defined('IS_ERROR') ? 1 : 0);
	}
	if(cli_has('-v') || cli_has('--version') || cli_has('/v')){
		date_default_timezone_set('Europe/Malta');
		write_line('httpdmon '.VERSION);
		write_line('Copyright (c) 2013-'.date('Y').' Christian Sciberras');
		exit(0);
	}
	
	// set up default options
	define('REFRESH_INTERVAL', cli_has('--delay') ? cli_get('-delay', 100) : cli_get('d', 100));    // refresh interval in msec
	define('ACCESSLOG_PATHS', cli_get('a', implode(';', array(                  // semicolon-separated list of access_log paths
		'/var/log/httpd/access_log',                                            // linux
		'/var/www/vhosts/*/statistics/logs/access_log',                         // linux + plesk
		getenv('ProgramFiles').'\\Zend\\Apache2\\logs\\access.log',             // windows + zend
		'C:\\wamp\\logs\\access.log',                                           // windows + wamp
		'/usr/local/apache/logs/access_log',                                    // linux + whm/cpanel
		'/home/*/access-logs/*',                                                // linux + whm/cpanel
	))));
	define('ERRORLOG_PATHS', cli_get('e', implode(';', array(                   // semicolon-separated list of error_log paths
		'/var/log/httpd/error_log',                                             // linux
		'/var/www/vhosts/*/statistics/logs/error_log',                          // linux + plesk
		getenv('ProgramFiles').'\\Zend\\Apache2\\logs\\error.log',              // windows + zend
		'C:\\wamp\\logs\\apache_error.log',                                     // windows + wamp
		'/usr/local/apache/logs/error_log',                                     // linux + whm/cpanel
	))));
	define('SHOW_ERRORS_ONLY', cli_has('-m') || cli_has('/m'));                 // show errors only
	define('FORCE_COLOR', cli_has('-c') || cli_has('/c'));                      // force colors (on Windows)
	define('FORCE_PLAIN', cli_has('-t') || cli_has('/t'));                      // force colors (on Windows)
	define('RESOLVE_IPS', cli_has('-r') || cli_has('/r'));                      // resolve ip addresses
	
	// try being smarter than the user :D
	if(FORCE_COLOR && FORCE_PLAIN){
		write_line('Please decide if you want colors (-c) or not (-t), not both, Dummkopf!');
		exit(1);
	}
	
	// ensure we can run forever and flush asap
	set_time_limit(0);
	while(ob_get_level())ob_end_flush();
	ob_implicit_flush(true);
	
	### LOAD FILE MONITORS ###
	
	$monitors = array();
	foreach(explode(';', ACCESSLOG_PATHS) as $path)
		if(!!($files = glob($path)))
			foreach($files as $file)
				$monitors[] = new AccesslogFileMonitor($file);
	foreach(explode(';', ERRORLOG_PATHS) as $path)
		if(!!($files = glob($path)))
			foreach($files as $file)
				$monitors[] = new ErrorlogFileMonitor($file);
	
	### MAIN PROGRAM LOOP ###
	
	while(true){
		foreach($monitors as $monitor){
			if($monitor->hasChanges()){
				switch(true){
					case $monitor instanceof AccesslogFileMonitor:
						foreach($monitor->getLines() as $line){
							if(SHOW_ERRORS_ONLY && $line->code < 400)continue; // not an error empty, go to next entry
							write_part('['.colorize_message('ACCESS', 'cyan').'] ');
							write_part(colorize_message(RESOLVE_IPS ? substr(str_pad(resolve_ip($line->ip), 48), 0, 48) : str_pad($line->ip, 16), 'yellow').' ');
							write_part(colorize_message(str_pad($monitor->getDomain(), 32), 'brown').' ');
							write_part(colorize_message(str_pad($line->method, 5), 'light_purple'));
							$long_mesg = ''
								. colorize_message(str_replace('&', colorize_message('&', 'dark_gray'), $line->url), 'white')
								. colorize_message(' > ', 'dark_gray')
								. colorize_message($line->code, $line->code < 400 ? 'green' : 'red')
								. colorize_message(' (', 'dark_gray').colorize_message($line->size, 'white').colorize_message(' bytes)', 'dark_gray')
							;
							//write_line(implode(str_pad(PHP_EOL, count_parts()), str_split($long_mesg, cli_width() - count_parts())));
							write_line($long_mesg);
						}
						break;
					case $monitor instanceof ErrorlogFileMonitor:
						foreach($monitor->getLines() as $line){
							write_part('['.colorize_message('ERROR', 'red').']  ');
							write_part(colorize_message(RESOLVE_IPS ? substr(str_pad(resolve_ip($line->ip), 48), 0, 48) : str_pad($line->ip, 16), 'yellow').' ');
							write_part(colorize_message(str_pad($monitor->getDomain(), 32), 'brown').' ');
							$long_mesg = colorize_message($line->message, 'red');
							//write_line(implode(str_pad(PHP_EOL, count_parts()), str_split($long_mesg, cli_width() - count_parts())));
							write_line($long_mesg);
						}
						break;
					default:
						throw new Exception('Unknown monitor type "'.get_class($monitor).'"');
				}
			}
		}
		usleep(REFRESH_INTERVAL * 1000);
	}
	
?>