<?php
include __DIR__.'/../vendor/autoload.php';

\Symfony\Component\ClassLoader\ClassCollectionLoader::load(
    array(
        'Christiaan\\PhpSandbox\\RpcProtocol',
        'Christiaan\\PhpSandbox\\PhpSandboxClient',
        'Christiaan\\PhpSandbox\\Exception',
        'React\\EventLoop\\Factory',
        'React\\EventLoop\\Timer\\Timers',
        'React\\EventLoop\\StreamSelectLoop',
        'React\\EventLoop\\LibEventLoop',
    ),
    __DIR__,
    'child',
    false
);

file_put_contents(__DIR__.'/child.php', <<<CODE

namespace {
    \$parent = new \Christiaan\PhpSandbox\PhpSandboxClient(
        new \Christiaan\PhpSandbox\RpcProtocol(
            STDIN, STDOUT, STDERR, \React\EventLoop\Factory::create()
        )
    );
    while (\$parent->listen()) {
        // NOOP
    }
}
CODE
, FILE_APPEND
);
