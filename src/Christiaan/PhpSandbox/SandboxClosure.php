<?php
namespace Christiaan\PhpSandbox;
 
class SandboxClosure
{
    private $protocol;
    private $name;

    public function __construct(RpcProtocol $protocol, $name)
    {
        $this->protocol = $protocol;
        $this->name = $name;
    }

    public function __invoke()
    {
        $args = func_get_args();
        return $this->protocol->call($this->name, $args);
    }
}
