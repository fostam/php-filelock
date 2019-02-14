<?php

namespace Fostam\FileLock;

use Fostam\FileLock\Exception\LockFileNotOpenableException;
use Fostam\FileLock\Exception\LockFileOperationFailureException;
use Fostam\FileLock\Exception\LockFileVanishedException;
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
        $this->release();
    }

    /**
     * @param int $timeout timeout in seconds
     * @param int $staleLockMode
     * @return bool
     * @throws LockFileNotOpenableException
     * @throws LockFileOperationFailureException
     * @throws StaleLockFileException
     * @throws LockFileVanishedException
     */
    public function acquire($timeout = 0, $staleLockMode = self::STALE_LOCK_WARN) {
        $maxTS = time() + $timeout;
        do {
            if ($this->lock($staleLockMode)) {
                return true;
            }

            if ($timeout === 0) {
                return false;
            }

            sleep(1);
        }
        while(time() < $maxTS);

        return false;
    }

    /**
     * @param int $staleLockMode
     * @return bool
     * @throws LockFileNotOpenableException
     * @throws LockFileOperationFailureException
     * @throws StaleLockFileException
     * @throws LockFileVanishedException
     */
    private function lock($staleLockMode = self::STALE_LOCK_WARN) {
        $this->openFile($staleLockMode);
        if (!$this->lockFile()) {
            return false;
        }
        $this->validateFile();
        $this->writePID();

        return true;
    }

    /**
     * @param int $staleLockMode
     * @throws LockFileNotOpenableException
     * @throws StaleLockFileException
     */
    private function openFile($staleLockMode) {
        $this->fileHandle = fopen($this->filename, 'c+');
        if ($this->fileHandle === false) {
            $errorStr = error_get_last()['message'];
            throw new LockFileNotOpenableException($errorStr, 0, null, $this->filename);
        }

        if (!function_exists('posix_getpgid')) {
            return;
        }

        $runningPID = trim(fgets($this->fileHandle));

        if (!empty($runningPID) && !posix_getpgid($runningPID)) {
            $msg = "stale lock file {$this->filename} exists with PID {$runningPID}";
            if ($staleLockMode === self::STALE_LOCK_WARN) {
                trigger_error($msg, E_USER_WARNING);
            }
            else if ($staleLockMode === self::STALE_LOCK_EXCEPTION) {
                throw new StaleLockFileException($msg, 0, null, $this->filename, $runningPID);
            }
        }
    }

    /**
     * @return bool
     * @throws LockFileOperationFailureException
     */
    private function lockFile() {
        if (!flock($this->fileHandle, LOCK_EX | LOCK_NB)) {
            if (!fclose($this->fileHandle)) {
                throw new LockFileOperationFailureException('fclose({$this->filename})', 0, null, $this->filename);
            }
            $this->fileHandle = null;
            return false;
        }
        return true;
    }

    /**
     * @throws LockFileVanishedException
     */
    private function validateFile() {
        if (!file_exists($this->filename)) {
            throw new LockFileVanishedException();
        }
    }

    /**
     * @throws LockFileOperationFailureException
     */
    private function writePID() {
        $pid = getmypid();

        if (!ftruncate($this->fileHandle, 0)) {
            throw new LockFileOperationFailureException('ftruncate({$this->filename})', 0, null, $this->filename);
        }

        if (!rewind($this->fileHandle)) {
            throw new LockFileOperationFailureException('rewind({$this->filename})', 0, null, $this->filename);
        }

        if (!fputs($this->fileHandle, $pid)) {
            throw new LockFileOperationFailureException('fputs({$this->filename})', 0, null, $this->filename);
        }

        if (!fflush($this->fileHandle)) {
            throw new LockFileOperationFailureException('fflush({$this->filename})', 0, null, $this->filename);
        }
    }

    /**
     * @throws LockFileOperationFailureException
     */
    public function release() {
        if (is_null($this->fileHandle)) {
            return;
        }

        if (!flock($this->fileHandle, LOCK_UN)) {
            throw new LockFileOperationFailureException('flock({$this->filename})', 0, null, $this->filename);
        }

        // ignore errors on closing, as they are not relevant
        fclose($this->fileHandle);

        // do not delete lock file to avoid race conditions

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