<?php
namespace Christiaan\PhpSandbox;

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
        set_error_handler(array($this, 'errorHandler'));
        set_exception_handler(array($this, 'exceptionHandler'));
    }

    public function __call($name, $args)
    {
        if (ob_get_level()) ob_end_clean();
        ob_start(array($this, 'output'));
        $ret = $this->protocol->call($name, $args);
        ob_end_flush();
        return $ret;
    }

    public function output($output)
    {
        $this->protocol->call('output', array($output));
    }

    public function execute($code)
    {
        if (ob_get_level()) ob_end_clean();
        ob_start(array($this, 'output'));
        $ret = eval($code);
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

    public function errorHandler($errno, $errstr, $errfile, $errline)
    {
        $this->exceptionHandler(new \ErrorException($errstr, $errno, 0, $errfile, $errline));
    }

    /**
     * @param \Exception $exception
     */
    public function exceptionHandler($exception)
    {
        $this->protocol->error($exception->getMessage());
    }
}

