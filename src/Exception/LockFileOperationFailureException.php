<?php

namespace Fostam\FileLock\Exception;

use Throwable;

class LockFileOperationFailureException extends \Exception {
    private $filename;

    /**
     * LockFileOpenException constructor.
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     * @param string $filename
     */
    public function __construct($message = '', $code = 0, Throwable $previous = null, $filename = '') {
        parent::__construct($message, $code, $previous);
        $this->filename = $filename;
    }

    /**
     * @return string
     */
    public function getFilename() {
        return $this->filename;
    }
}