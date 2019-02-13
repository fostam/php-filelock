<?php

namespace Fostam\FileLock;

use Fostam\FileLock\Exception\LockFileNotOpenableException;
use Fostam\FileLock\Exception\LockFileOperationFailureException;
use Fostam\FileLock\Exception\StaleLockFileException;

class FileLock {
    const STALE_LOCK_IGNORE = 1;
    const STALE_LOCK_WARN = 2;
    const STALE_LOCK_EXCEPTION = 3;

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
     * @throws LockFileOperationFailureException
     */
    public function __destruct() {
        ## $this->release(); // TODO
    }

    /**
     * @param int $timeout timeout in seconds
     * @param int $staleLockMode
     * @return bool
     * @throws LockFileNotOpenableException
     * @throws LockFileOperationFailureException
     * @throws StaleLockFileException
     */
    public function acquire($timeout = 0, $staleLockMode = self::STALE_LOCK_WARN) {
        $this->fileHandle = fopen($this->filename, 'c+');
        if ($this->fileHandle === false) {
            $errorStr = error_get_last();
            throw new LockFileNotOpenableException($errorStr, 0, null, $this->filename);
        }

        $runningPID = trim(fgets($this->fileHandle));

        if (!empty($runningPID) && !posix_getpgid($runningPID)) {
            if ($staleLockMode === self::STALE_LOCK_WARN) {
                trigger_error("stale lock file {$this->filename} exists with PID {$runningPID}", E_USER_WARNING);
            }
            else if ($staleLockMode === self::STALE_LOCK_EXCEPTION) {
                throw new StaleLockFileException('stale lock file exists', 0, null, $this->filename, $runningPID);
            }
        }

        // TODO timeout
        $flags = LOCK_EX;
        if (!$timeout) {
            $flags |= LOCK_NB;
        }

        if (!flock($this->fileHandle, $flags)) {
            if (!fclose($this->fileHandle)) {
                throw new LockFileOperationFailureException('fclose', 0, null, $this->filename);
            }
            $this->fileHandle = null;
            return false;
        }

        $pid = getmypid();

        if (!ftruncate($this->fileHandle, 0)) {
            throw new LockFileOperationFailureException('ftruncate', 0, null, $this->filename);
        }

        if (!rewind($this->fileHandle)) {
            throw new LockFileOperationFailureException('rewind', 0, null, $this->filename);
        }

        if (!fputs($this->fileHandle, $pid)) {
            throw new LockFileOperationFailureException('fputs', 0, null, $this->filename);
        }

        if (!fflush($this->fileHandle)) {
            throw new LockFileOperationFailureException('fflush', 0, null, $this->filename);
        }

        return true;
    }

    /**
     * @throws LockFileOperationFailureException
     */
    public function release() {
        if (is_null($this->fileHandle)) {
            return;
        }

        if (!flock($this->fileHandle, LOCK_UN)) {
            throw new LockFileOperationFailureException('flock', 0, null, $this->filename);
        }

        if (!fclose($this->fileHandle)) {
            throw new LockFileOperationFailureException('fclose', 0, null, $this->filename);
        }

        if (!unlink($this->filename)) {
            throw new LockFileOperationFailureException('unlink', 0, null, $this->filename);
        }

        $this->fileHandle = null;
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