<?php
include __DIR__.'/../vendor/autoload.php';

Symfony\Component\ClassLoader\ClassCollectionLoader::load(
    array(
        'Christiaan\\PhpSandbox\\RpcProtocol',
        'Christiaan\\PhpSandbox\\Child\\SandboxParent',
        'Christiaan\\PhpSandbox\\Child\\SandboxChild',
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
    \$loop = \React\EventLoop\Factory::create();
    \$child = new Christiaan\PhpSandbox\Child\SandboxChild(
        new \Christiaan\PhpSandbox\RpcProtocol(
            STDIN, STDOUT, STDERR, \$loop
        )
    );
    while (\$loop->run()) {
        // NOOP
    }
}
CODE
, FILE_APPEND
);
