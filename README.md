PhpSandbox
==========
[![Build Status](https://travis-ci.org/christiaan/php-sandbox.png?branch=master)](https://travis-ci.org/christiaan/php-sandbox)

Php Sandbox in a child process which should make it possible to run user supplied code safely.

Installation
------------
    php composer.phar require christiaan/php-sandbox

Usage
-----
See the various [tests](https://github.com/christiaan/php-sandbox/blob/master/tests/Christiaan/PhpSandbox/Tests)

Known issues
------------
Does not work on Windows, most likely due to this [bug](https://bugs.php.net/bug.php?id=47918)
