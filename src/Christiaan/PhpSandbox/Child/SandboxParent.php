<?php
namespace Christiaan\PhpSandbox\Child;

use Christiaan\PhpSandbox\RpcProtocol;

class SandboxParent
{
    private $protocol;
    public $output;

    public function __construct(RpcProtocol $protocol)
    {
        $this->protocol = $protocol;
        $self = $this;
        $this->protocol->registerOutputCallback(function($string) use($self) {
                $self->output .= $string;
            });
    }

    public function __call($name, $args)
    {
        $ret = $this->protocol->sendCall($name, $args);

        return $ret;
    }
}

