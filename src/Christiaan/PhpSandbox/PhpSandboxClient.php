<?php
namespace Christiaan\PhpSandbox;

use React\EventLoop\LoopInterface;

class PhpSandboxClient
{
    private $protocol;
    private $data;

    public function __construct(RpcProtocol $protocol)
    {
        $this->protocol = $protocol;
        $this->protocol->addCallback('execute', array($this, 'execute'));
        $this->protocol->addCallback('assignVar', array($this, 'assignVar'));
        $this->data = array();
    }

    public function __call($name, $args)
    {
        return $this->protocol->call($name, $args);
    }

    public function output($output)
    {
        $this->protocol->call('output', array($output));
    }

    public function execute($php)
    {
        ob_start(array($this, 'output'));
        $ret = eval($php);
        ob_end_flush();
        return $ret;
    }

    public function assignVar($name, $value)
    {
        $this->data[$name] = $value;
    }

    public function listen()
    {
        $this->protocol->listen();
    }
}

