<?php

namespace Fostam\FileLock;

use Fostam\FileLock\Exception\LockFileNotOpenableException;
use PHPUnit\Framework\TestCase;

final class FileLockTest extends TestCase {
    /**
     * @throws Exception\LockFileOperationFailedException
     * @throws Exception\LockFileVanishedException
     * @throws LockFileNotOpenableException
     */
    public function testCreateLockFile() {
        $fl = new FileLock('test');
        $success = $fl->acquire();
        $this->assertTrue($success);
        $this->assertFileExists($fl->getLockFileName());
        $fl->release();
    }

    /**
     * @throws Exception\LockFileOperationFailedException
     * @throws Exception\LockFileVanishedException
     * @throws LockFileNotOpenableException
     */
    public function testPID() {
        $fl = new FileLock('test');
        $success = $fl->acquire();
        $this->assertTrue($success);
        $isPID = intval(trim(file_get_contents($fl->getLockFileName())));
        $shouldPID = getmypid();
        $this->assertEquals($shouldPID, $isPID);
        $fl->release();
    }

    /**
     * @throws Exception\LockFileOperationFailedException
     * @throws Exception\LockFileVanishedException
     * @throws LockFileNotOpenableException
     */
    public function testSetCustomLockDirectory() {
        $subDir = substr(uniqid(rand(), true), 0, 8);
        $testDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $subDir;
        mkdir($testDir);

        $fl = new FileLock('test', $testDir);
        $success = $fl->acquire();
        $this->assertTrue($success);
        $lockFile = $fl->getLockFileName();
        $this->assertFileExists($lockFile);
        $fl->release();

        unlink($lockFile);
        rmdir($testDir);
    }

    /**
     * @throws Exception\LockFileOperationFailedException
     * @throws Exception\LockFileVanishedException
     * @throws LockFileNotOpenableException
     */
    public function testLockMutualExclusion() {
        $lockName = 'test';

        $fl1 = new FileLock($lockName);
        $success = $fl1->acquire();
        $this->assertTrue($success);

        $fl2 = new FileLock($lockName);
        $success = $fl2->acquire();
        $this->assertFalse($success);

        $fl1->release();

        $fl2 = new FileLock($lockName);
        $success = $fl2->acquire();
        $this->assertTrue($success);

        $fl2->release();
    }

    /**
     * @throws Exception\LockFileOperationFailedException
     * @throws Exception\LockFileVanishedException
     * @throws LockFileNotOpenableException
     */
    public function testTimeout() {
        $lockName = 'test';
        $timeout = 3;

        $fl1 = new FileLock($lockName);
        $success = $fl1->acquire();
        $this->assertTrue($success);

        $startTS = time();
        $fl2 = new FileLock($lockName);
        $success = $fl2->acquire($timeout);
        $this->assertFalse($success);
        $this->assertEquals($timeout, time() - $startTS);

        $fl1->release();
    }

    /**
     * @throws Exception\LockFileOperationFailedException
     * @throws Exception\LockFileVanishedException
     * @throws LockFileNotOpenableException
     */
    public function testReleaseOnDestruct() {
        $lockName = 'test';

        $fl1 = new FileLock($lockName);
        $success = $fl1->acquire();
        $this->assertTrue($success);

        $fl2 = new FileLock($lockName);
        $success = $fl2->acquire();
        $this->assertFalse($success);

        unset($fl1);

        $fl2 = new FileLock($lockName);
        $success = $fl2->acquire();
        $this->assertTrue($success);

        $fl2->release();
    }

    /**
     * @throws Exception\LockFileOperationFailedException
     * @throws Exception\LockFileVanishedException
     * @throws LockFileNotOpenableException
     */
    public function testLockFileNotOpenable() {
        $this->expectException(LockFileNotOpenableException::class);

        $subDir = substr(uniqid(rand(), true), 0, 8);
        $testDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $subDir;

        $fl = new FileLock('test', $testDir);
        $fl->acquire();
    }
}