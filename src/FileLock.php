<?php

namespace Fostam\FileLock;

use Fostam\FileLock\Exception\LockFileNotOpenableException;
use Fostam\FileLock\Exception\LockFileOperationFailedException;
use Fostam\FileLock\Exception\LockFileVanishedException;

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
     * @throws LockFileOperationFailedException
     */
    public function __destruct() {
        $this->release();
    }

    /**
     * @param int $timeout timeout in seconds
     * @return bool
     * @throws LockFileNotOpenableException
     * @throws LockFileOperationFailedException
     * @throws LockFileVanishedException
     */
    public function acquire($timeout = 0) {
        $maxTS = time() + $timeout;
        do {
            if ($this->lock()) {
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
     * @return bool
     * @throws LockFileNotOpenableException
     * @throws LockFileOperationFailedException
     * @throws LockFileVanishedException
     */
    private function lock() {
        $this->openFile();
        if (!$this->lockFile()) {
            return false;
        }
        $this->validateFile();
        $this->writePID();

        return true;
    }

    /**
     * @throws LockFileNotOpenableException
     */
    private function openFile() {
        $this->fileHandle = @fopen($this->filename, 'c+');
        if ($this->fileHandle === false) {
            $errorStr = error_get_last()['message'];
            throw new LockFileNotOpenableException($errorStr, 0, null, $this->filename);
        }
    }

    /**
     * @return bool
     * @throws LockFileOperationFailedException
     */
    private function lockFile() {
        if (!flock($this->fileHandle, LOCK_EX | LOCK_NB)) {
            if (!fclose($this->fileHandle)) {
                throw new LockFileOperationFailedException('fclose({$this->filename})', 0, null, $this->filename);
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
     * @throws LockFileOperationFailedException
     */
    private function writePID() {
        $pid = getmypid();

        if (!ftruncate($this->fileHandle, 0)) {
            throw new LockFileOperationFailedException('ftruncate({$this->filename})', 0, null, $this->filename);
        }

        if (!rewind($this->fileHandle)) {
            throw new LockFileOperationFailedException('rewind({$this->filename})', 0, null, $this->filename);
        }

        if (!fputs($this->fileHandle, $pid)) {
            throw new LockFileOperationFailedException('fputs({$this->filename})', 0, null, $this->filename);
        }

        if (!fflush($this->fileHandle)) {
            throw new LockFileOperationFailedException('fflush({$this->filename})', 0, null, $this->filename);
        }
    }

    /**
     * @throws LockFileOperationFailedException
     */
    public function release() {
        if (!$this->fileHandle) {
            return;
        }

        if (!flock($this->fileHandle, LOCK_UN)) {
            throw new LockFileOperationFailedException('flock({$this->filename})', 0, null, $this->filename);
        }

        // ignore errors on closing, as they are not relevant
        fclose($this->fileHandle);

        // do not delete lock file to avoid race conditions

        $this->fileHandle = null;
    }

    /**
     * @return string
     */
    public function getLockFileName() {
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