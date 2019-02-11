<?php

namespace FileLock;

use FileLock\Exception\LockFileNotOpenableException;
use FileLock\Exception\StaleLockFileException;

class FileLock {
    /**
     * @var string
     */
    private $name;

    /** @var string */
    private $filename;

    /**
     * @var resource
     */
    private $fileHandle;

    /**
     * FileLock constructor.
     * @param string $name
     * @param string $path
     */
    public function __construct($name, $path = '') {
        if (!$path) {
            $path = sys_get_temp_dir();
        }

        $this->name = $name;
        $this->filename = $this->buildLockFilePath($path, $name);
    }

    /**
     *
     */
    public function __destruct() {
        $this->release();
    }

    /**
     * @param int $timeoutMS
     * @throws \Exception
     * @return bool
     */
    public function acquire($timeoutMS = 0) {
        $this->fileHandle = fopen($this->filename, 'c+');
        if ($this->fileHandle === false) {
            $errorStr = error_get_last();
            throw new LockFileNotOpenableException($errorStr, 0, null, $this->filename);
        }

        $runningPID = trim(fgets($this->fileHandle));
        if (empty($runningPID)) {
            $runningState = false;
        }
        else {
            $runningState = posix_getpgid($runningPID);
        }

        if (!flock($this->fileHandle, LOCK_EX | LOCK_NB)) {
            if ($runningState === false) {
                throw new StaleLockFileException('stale lock file exists', 0, null, $this->filename, $runningPID);
            }
            return false;
        }

        $pid = getmypid();
        ftruncate($this->fileHandle, 0);
        rewind($this->fileHandle);
        fputs($this->fileHandle, $pid);
        fflush($this->fileHandle);

        return true;
    }

    /**
     *
     */
    public function release() {
        flock($this->fileHandle, LOCK_UN);
        fclose($this->fileHandle);
        unlink($this->filename);
    }

    /**
     * @return string
     */
    public function getLockFilePath() {
        return $this->filename;
    }

    /**
     * @param string $path
     * @param string $name
     * @return string
     */
    private function buildLockFilePath($path, $name) {
        return $path . DIRECTORY_SEPARATOR . $name . '.lock';
    }
}