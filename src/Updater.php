<?php

class Updater
{
    /**
     * Target URL to get updates from.
     * @var string
     */
    public $UpdateUrl = '';

    /**
     * Current local version.
     * @var string
     */
    public $LocalVersion = '0.0.0';

    /**
     * Whether to try running update or not.
     * @var boolean
     */
    public $TryRun = true;

    /**
     * Shell command used to verify downloaded update.
     * @var string
     */
    public $TryRunCmd = '';

    /**
     * Force using updated even when $TryRunCmd fails.
     * @var boolean
     */
    public $ForceUpdate = false;

    /**
     * Regular expression used to parse version from remote update.
     * @var string
     */
    public $VersionRegex = '/define\\(\\s*[\'"]version[\'"]\\s*,\\s*[\'"](.*?)[\'"]\\s*\\)/i';

    /**
     * Function/method that is called for each event triggered by updater.
     * @var callable|null
     */
    public $EventHandler = null;

    /**
     * The file to overwrite with downloaded patch (defaults to SCRIPT_FILENAME)
     * @var string|null
     */
    public $TargetFile = null;

    /**
     * Attempts to update current file from URL.
     */
    public function Run()
    {
        // preconditions
        if (!$this->UpdateUrl) {
            throw new Exception('Update URL not specified.');
        }
        if (!$this->LocalVersion) {
            $this->LocalVersion = '0.0.0';
        }
        if (!$this->TargetFile) {
            $this->TargetFile = $_SERVER['SCRIPT_FILENAME'];
        }

        if (!$this->TryRunCmd) {
            $this->TryRunCmd = 'php -f ' . escapeshellarg($this->TargetFile);
        }

        if (!$this->EventHandler) {
            $this->EventHandler = 'pi'; // NOOP
        }


        // initialization
        $notify = $this->EventHandler;
        $rollback = false;
        $next_version = null;
        static $intentions = array(-1=> 'fail', 0=> 'ignore', 1=> 'update');

        // process
        $notify('start', array('this' => $this));
        $notify('before_download', array('this' => $this));
        if (!($data = $this->DownloadFile($this->UpdateUrl))) {
            $notify('error', array('this' => $this, 'reason' => 'File download failed', 'target' => $this->UpdateUrl));
            return;
        }
        
        $notify('after_download', array('this' => $this, 'data' => &$data));
        if (!preg_match($this->VersionRegex, $data, $next_version)) {
            $notify('error', array('this' => $this, 'reason' => 'Could not determine version of target file', 'target' => $data, 'result' => $next_version));
            return;
        }
        
        if (!($next_version = array_pop($next_version))) {
            $notify('error', array('this' => $this, 'reason' => 'Version of target file is empty', 'target' => $data, 'result' => $next_version));
            return;
        }
        
        $v_diff = version_compare($next_version, $this->LocalVersion);
        $should_fail = $notify('version_check', array('this' => $this, 'intention' => $intentions[$v_diff], 'curr_version' => $this->LocalVersion, 'next_version' => $next_version));
        if ($should_fail === false) {
            $notify('error', array('this' => $this, 'reason' => 'Update cancelled by user code'));
            return;
        }
        
        if ($v_diff === 0 && !$this->ForceUpdate) {
            $notify('already_uptodate', array('this' => $this));
            return;
        }
        
        if ($v_diff === -1 && !$this->ForceUpdate) {
            $notify('warn', array('this' => $this, 'reason' => 'Local file is newer than remote one', 'curr_version' => $this->LocalVersion, 'next_version' => $next_version));
            return;
        }
        
        if (!copy($this->TargetFile, $this->TargetFile . '.bak')) {
            $notify('warn', array('this' => $this, 'reason' => 'Backup operation failed', 'target' => $this->TargetFile));
        }
        
        if (!file_put_contents($this->TargetFile, $data)) {
            $notify('warn', array('this' => $this, 'reason' => 'Failed writing to file', 'target' => $this->TargetFile));
            $rollback = true;
        }
        
        if (!$rollback && $this->TryRun) {
            $notify('before_try', array('this' => $this));
            ob_start();
            $exit = 0;
            passthru($this->TryRunCmd, $exit);
            $out = ob_get_clean();
            $notify('after_try', array('this' => $this, 'output' => $out, 'exitcode' => $exit));
            if ($exit !== 0) {
                $notify('warn', array('this' => $this, 'reason' => 'Downloaded update seems to be broken', 'output' => $out, 'exitcode' => $exit));
                $rollback = true;
            }
        }
        
        if ($rollback) {
            $notify('before_rollback', array('this' => $this));
            if (!rename($this->TargetFile . '.bak', $this->TargetFile)) {
                $notify('error', array('this' => $this, 'reason' => 'Rollback operation failed', 'target' => $this->TargetFile . '.bak'));
                return;
            }
            $notify('after_rollback', array('this' => $this));
        } else {
            if (!unlink($this->TargetFile . '.bak')) {
                $notify('warn', array('this' => $this, 'reason' => 'Cleanup operation failed', 'target' => $this->TargetFile . '.bak'));
            }
            $notify('finish', array('this' => $this, 'new_version' => $next_version));
        }
    }

    /**
     * @param string $url
     */
    protected function DownloadFile($url)
    {
        $this->fileData = '';

        if (!($ch = curl_init($url))) {
            throw new Exception('curl_init failed.');
        }
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_NOPROGRESS, false);
        curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, array($this, 'DownloadProgress'));
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, array($this, 'DownloadWrite'));
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 50);

        if (!curl_exec($ch)) {
            throw new Exception('Curl error ' . curl_errno($ch) . ': ' . curl_error($ch));
        }
        
        return $this->fileData;
    }

    public function DownloadProgress($download_size, $downloaded_size)
    {
        $notify = $this->EventHandler;
        $notify('download_progress', array('this' => $this, 'total' => $download_size, 'current' => $downloaded_size));
    }

    public function DownloadWrite($curl, $data)
    {
        $this->fileData .= $data;
        return strlen($data);
    }
}
