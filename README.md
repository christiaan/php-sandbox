PhpSandbox
==========
[![Build Status](https://travis-ci.org/christiaan/php-sandbox.png?branch=master)](https://travis-ci.org/christiaan/php-sandbox)

Php Sandbox in a child process which should make it possible to run user supplied PHP code safely.

This is a proof of concept, it works but as far as I know does not run in production anywhere.
Because it runs a process for each sandbox it is also much slower than real sandbox solutions.

If you want to offer a scripting solution to your end users you are most likely better off
using either the [LUA][1] or [JavaScript V8][2] php extensions.

[1]: https://github.com/laruence/php-lua
[2]: https://github.com/preillyme/v8js

Installation
------------
    php composer.phar require christiaan/php-sandbox

Usage
-----
See the various [tests](https://github.com/christiaan/php-sandbox/blob/master/tests/Christiaan/PhpSandbox/Tests)

Known issues
------------
Does not work on Windows, most likely due to this [bug](https://bugs.php.net/bug.php?id=47918)
