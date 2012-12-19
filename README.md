PhpSandbox
==========
[![Build Status](https://travis-ci.org/christiaan/php-sandbox.png?branch=master)](https://travis-ci.org/christiaan/php-sandbox)

Php Sandbox in a child process which should make it possible to run user supplied code safely.

Installation
------------
    php composer.phar require christiaan/php-sandbox

Usage
-----
For now check the tests folder

Known issues
------------
Should work on Windows but doesn't for some reason.

Only json encodable values can be exchanged between the host and the sandbox process.