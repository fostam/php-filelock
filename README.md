# fostam/filelock

Simple file based locking.

## Features
- file based locking with flock()
- works on Unix-like systems as well as Windows
- supports timeout for acquiring lock
- no third-party dependencies

## Install
The easiest way to install FileLock is by using [composer](https://getcomposer.org/): 

```
$> composer require fostam/filelock
```

## Usage

```php
<?php

require "vendor/autoload.php";

$fl = new \Fostam\FileLock\FileLock('mylock');
if (!$fl->acquire()) {
    exit;
}

// do exclusive stuff

$fl->release();
````

## Locking
The name of the lock is passed as first argument to the constructor:
````
$fl = FileLock('mylock');
````

Acquiring the lock is done with the `acquire()` method:
````
$fl->acquire();
````

It will return `true` on success and `false` if the lock is already
present. If an error occurs, an exception is thrown (see below).

Optionally, a timeout in seconds can be passed:
````
$fl->acquire(10);
````

The `acquire()` method will try to get the lock once per second until the timeout
has been reached. If the lock could not be acquired, `false` will be returned.

A value of `0` will immediately return `false` if the locking fails.
This is also the default behaviour if no timeout is given.


## Unlocking
Explicitly releasing the lock:
````
$fl->release();
````

The lock is also implicitly released in the desctructor of the
`FileLock` class if still present on destruction of the object.


## Lock File
The process id (PID) of the locking process is written into the
lock file after acquiring the lock.

By default, the lock file is created in the system's temporary
directory as returned by `sys_get_temp_dir()`. An alternative
directory can be passed to the constructor as second parameter:
````
$fl = FileLock('mylock', '/var/run');
````

In this example, the lock file would be `/var/run/mylock.lock`.

If required, the full lock file name can be retrieved from the `FileLock` object:
````
$filename = $fl->getLockFileName();
````

If the lock file is kept in a place that is subject to periodic
cleanups (e.g. `/tmp` on most Linux systems),
it can be refreshed with the `refresh()` method:
````
$fl->refresh();
````

This will update the file's modification time to the current timestamp.

**NOTE:** To avoid race conditions, the lock file is *not* deleted
when released. 


## Exceptions

All exceptions inherit the common `LockFileException` class.

The `acquire()` method might throw one of the following exceptions:
- `LockFileNotOpenableException`: the lock file can not be created or opened
- `LockFileOperationFailedException`: a file system operation on the lock file has failed
- `LockFileVanishedException`: the lock file has vanished after acquiring the lock

The `release()` method will throw the `LockFileOperationFailureException` exception
in case the unlock file system operation fails.
