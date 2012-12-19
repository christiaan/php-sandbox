<?php
namespace Christiaan\PhpSandbox;

class PhpSandboxClient
{
    private $protocol;
    private $data;
    private $obStarted;

    public function __construct(RpcProtocol $protocol)
    {
        $this->protocol = $protocol;
        $this->protocol->addCallback('execute', array($this, 'execute'));
        $this->protocol->addCallback('assignVar', array($this, 'assignVar'));
        $this->data = array();
        $this->obStarted = false;
        set_error_handler(array($this, 'errorHandler'));
        set_exception_handler(array($this, 'exceptionHandler'));
        // see php.net/ob_start about chunksize
        ob_start(array($this, 'output'), version_compare(PHP_VERSION, '5.4', '>=') ? 1 : 2);
    }

    public function __call($name, $args)
    {
        $ret = $this->protocol->call($name, $args);
        return $ret;
    }

    public function output($output)
    {
        $this->protocol->call('output', array($output));
        return '';
    }

    public function execute($code)
    {
        $ret = eval($code);
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

