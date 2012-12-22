<?php
namespace Christiaan\PhpSandbox\Child;

use Christiaan\PhpSandbox\RpcProtocol;

class SandboxParent
{
    private $protocol;

    public function __construct(RpcProtocol $protocol)
    {
        $this->protocol = $protocol;
    }

    public function __call($name, $args)
    {
        $ret = $this->protocol->sendCall($name, $args);

        return $ret;
    }
}

