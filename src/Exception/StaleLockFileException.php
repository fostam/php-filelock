<?php

namespace FileLock\Exception;

use Throwable;

class StaleLockFileException extends \Exception {
    private $filename;
    private $pid;

    /**
     * StaleLockFileException constructor.
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     * @param string $filename
     * @param int $pid
     */
    public function __construct($message = '', $code = 0, Throwable $previous = null, $filename = '', $pid = 0) {
        parent::__construct($message, $code, $previous);
        $this->filename = $filename;
        $this->pid = $pid;
    }

    /**
     * @return string
     */
    public function getFilename() {
        return $this->filename;
    }

    /**
     * @return int
     */
    public function getPID() {
        return $this->pid;
    }
}