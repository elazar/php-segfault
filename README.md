The code in this repository causes a segmentation fault when run with PHP 8.0.6 on Fedora 33.

## Installation

1. Install dependencies.

    ```sh
    composer install
    ```

2. Run the script.

    ```sh
    php index.php
    ```

## Root Cause

1. The reference to `$c['a']` on line 24 invokes `$getImplementations`.
2. While iterating over `$c->keys()`, `$getImplementations` tries to access `$c['a']` on line 16.
3. The reference to `$ca['a']` on line 16 invokes `$getImplementations`.
4. Go to step 2.

## GDB Backtrace

The gdb backtrace I get is in `gdb.txt` and is rather large for such a short script: over 62K lines.

```
$ gdb php
GNU gdb (GDB) Fedora 10.1-4.fc33
Copyright (C) 2020 Free Software Foundation, Inc.
License GPLv3+: GNU GPL version 3 or later <http://gnu.org/licenses/gpl.html>
This is free software: you are free to change and redistribute it.
There is NO WARRANTY, to the extent permitted by law.
Type "show copying" and "show warranty" for details.
This GDB was configured as "x86_64-redhat-linux-gnu".
Type "show configuration" for configuration details.
For bug reporting instructions, please see:
<https://www.gnu.org/software/gdb/bugs/>.
Find the GDB manual and other documentation resources online at:
    <http://www.gnu.org/software/gdb/documentation/>.

For help, type "help".
Type "apropos word" to search for commands related to "word"...
Reading symbols from php...
Reading symbols from /usr/lib/debug/usr/bin/php-8.0.6-1.fc33.remi.x86_64.debug...
(gdb) set logging on
Copying output to gdb.txt.
Copying debug output to gdb.txt.
(gdb) run index.php
Starting program: /usr/bin/php index.php
[Thread debugging using libthread_db enabled]
Using host libthread_db library "/lib64/libthread_db.so.1".

Program received signal SIGSEGV, Segmentation fault.
0x00005555557f3c83 in zend_hash_str_find_bucket (h=15567155185462650028, len=12, str=0x7fffff7ff030 "offsetexists", ht=0x555555f6be30)
    at /usr/src/debug/php-8.0.6-1.fc33.remi.x86_64/Zend/zend_hash.c:695
695				 && !memcmp(ZSTR_VAL(p->key), str, len)) {
```

The first few frames of the stack trace point to an `offsetExists` method being invoked shortly before the segmentation fault, presumably on the instance of `\Pimple\Container` on line 24 or 16 in `index.php`.

```c++
#0  0x00005555557d2d3a in zend_call_function (fci=0x7fffff7ff050, fci_cache=0x7fffff7ff030)
    at /usr/src/debug/php-8.0.6-1.fc33.remi.x86_64/Zend/zend_execute_API.c:664
#1  0x00005555557d3995 in zend_call_known_function (fn=0x555555f6bc30, object=object@entry=0x7ffff727d4d8,
    called_scope=called_scope@entry=0x555555f6a940, retval_ptr=retval_ptr@entry=0x7fffff7ff160, param_count=param_count@entry=1,
    params=params@entry=0x7fffff7ff0d0, named_params=0x0) at /usr/src/debug/php-8.0.6-1.fc33.remi.x86_64/Zend/zend_execute_API.c:985
#2  0x000055555584fa1d in zend_call_method (object=0x7ffff727d4d8, obj_ce=<optimized out>, fn_proxy=0x0,
    function_name=0x555555a20d86 "offsetexists", function_name_len=<optimized out>, retval_ptr=0x7fffff7ff160, param_count=1,
    arg1=0x7fffff7ff170, arg2=0x0) at /usr/src/debug/php-8.0.6-1.fc33.remi.x86_64/Zend/zend_interfaces.c:82
```

## Xdebug Trace

Running the same script with Xdebug tracing enabled output the contents of `xdebug.txt`.

```sh
php -d xdebug.auto_trace=ON -d xdebug.trace_output_dir=`pwd` index.php 2>&1 | tee xdebug.txt
```

Here are the interesting bits.

Stack frames 2-5 repeat until the call to `keys()` in frame 256, at which point Xdebug's infinite loop detection kicks in.

```
PHP Warning:  Uncaught Error in exception handling during call to Error::__toString() in /home/matt/Code/Personal/sandbox/vendor/pimple/pimple/src/Pimple/Container.php on line 277
PHP Stack trace:
PHP   1. {main}() /home/matt/Code/Personal/sandbox/index.php:0
PHP   2. Pimple\Container->offsetGet($id = 'a') /home/matt/Code/Personal/sandbox/index.php:24
PHP   3. {closure:/home/matt/Code/Personal/sandbox/index.php:14-20}($c = ...) /home/matt/Code/Personal/sandbox/vendor/pimple/pimple/src/Pimple/Container.php:118
PHP   4. array_reduce($array = [0 => 'HasMarker', 1 => 'a'], $callback = class Closure {  }, $initial = []) /home/matt/Code/Personal/sandbox/index.php:19
PHP   5. {closure:/home/matt/Code/Personal/sandbox/index.php:16-18}($implementations = [0 => class HasMarker {  }], $key = 'a') /home/matt/Code/Personal/sandbox/index.php:19
<snip>
PHP 251. {closure:/home/matt/Code/Personal/sandbox/index.php:14-20}($c = ...) /home/matt/Code/Personal/sandbox/vendor/pimple/pimple/src/Pimple/Container.php:118
PHP 252. array_reduce($array = [0 => 'HasMarker', 1 => 'a'], $callback = class Closure {  }, $initial = []) /home/matt/Code/Personal/sandbox/index.php:19
PHP 253. {closure:/home/matt/Code/Personal/sandbox/index.php:16-18}($implementations = [0 => class HasMarker {  }], $key = 'a') /home/matt/Code/Personal/sandbox/index.php:19
PHP 254. Pimple\Container->offsetGet($id = 'a') /home/matt/Code/Personal/sandbox/index.php:16
PHP 255. {closure:/home/matt/Code/Personal/sandbox/index.php:14-20}($c = ...) /home/matt/Code/Personal/sandbox/vendor/pimple/pimple/src/Pimple/Container.php:118
PHP 256. Pimple\Container->keys() /home/matt/Code/Personal/sandbox/index.php:15
PHP Warning:  Uncaught Error: Xdebug has detected a possible infinite loop, and aborted your script with a stack depth of '256' frames in /home/matt/Code/Personal/sandbox/vendor/pimple/pimple/src/Pimple/Container.php:277
```
