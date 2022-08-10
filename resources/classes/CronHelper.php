<?php
class cronHelper {

    private $pid;
    private $script;
    const LOCK_DIR = '/tmp/';
    const LOCK_SUFFIX = '.lock';

    function __construct($script) {
        $this->script = $script;
    }
    function __clone() {}

    public function lock() {
        $lock_file = $this->getLockFile();
        if(file_exists($lock_file)) {
            $this->pid = file_get_contents($lock_file);
            if($this->isrunning()) {
                error_log("==".$this->pid."== Already in progress...");
                return FALSE;
            }
            else {
                error_log("==".$this->pid."== Previous job died abruptly...");
            }
        }
        $this->pid = getmypid();
        file_put_contents($lock_file, $this->pid);
        error_log("==".$this->pid."== Lock acquired, processing the job...");
        return $this->pid;
    }

    public function unlock() {
        $lock_file = $this->getLockFile();
        if(file_exists($lock_file))
            unlink($lock_file);
        error_log("==".$this->pid."== Releasing lock...");
        return TRUE;
    }

    private function isrunning() {
        $pids = explode(PHP_EOL, `ps -e | awk '{print $1}'`);
        if(in_array($this->pid, $pids)) {
            return TRUE;
        }
        return FALSE;
    }

    private function getLockFile(){
        return self::LOCK_DIR.$this->script.self::LOCK_SUFFIX;
    }
}
