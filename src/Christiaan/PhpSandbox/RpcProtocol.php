<?php
namespace Christiaan\PhpSandbox;

use React\EventLoop\LoopInterface;

class RpcProtocol
{
    private $readStream;
    private $writeStream;
    private $errorStream;
    private $loop;
    private $lastReturn;
    private $lastError;
    private $callbacks;
    private $listener;

    function __construct($readStream, $writeStream, $errorStream,
        LoopInterface $loop)
    {
        if (!is_resource($readStream))
            throw new \InvalidArgumentException();
        if (!is_resource($writeStream))
            throw new \InvalidArgumentException();
        if (!is_resource($errorStream))
            throw new \InvalidArgumentException();

        $this->readStream = $readStream;
        $this->writeStream = $writeStream;
        $this->errorStream = $errorStream;
        $this->loop = $loop;

        $this->loop->addReadStream(
            $this->readStream, array($this, 'receive')
        );
        $this->loop->addReadStream(
            $this->errorStream, array($this, 'receiveError')
        );
        $this->callbacks = array();
        $this->listener = false;
    }

    public function returnValue($value)
    {
        $this->send('return', $value);
    }

    public function error($message)
    {
        $this->send('error', $message);
    }

    public function call($name, array $args)
    {
        return $this->send('call', $name, $args);
    }

    public function receive($stream)
    {
        $message = $this->read($stream);
        $action = array_shift($message);
        if ($action === 'return') {
            $this->lastReturn = array_shift($message);
            $this->loop->stop();
        }
        if ($action === 'error') {
            $this->lastError = array_shift($message);
            $this->loop->stop();
        }
        if ($action === 'call') {
            $method = array_shift($message);
            $args = array_shift($message);
            if (!array_key_exists($method, $this->callbacks))
                $this->error('Invalid Method');

            $ret = call_user_func_array($this->callbacks[$method], $args);
            $this->returnValue($ret);
        }
    }

    public function receiveError($stream)
    {
        $error = fgets($stream);
        $this->lastError = $error;
        $this->loop->stop();
    }

    public function addCallback($name, $callable)
    {
        if (!is_callable($callable))
            throw new \InvalidArgumentException();

        $this->callbacks[$name] = $callable;
    }

    public function listen()
    {
        $this->listener = true;
        $this->loop->run();
    }

    private function send()
    {
        $args = func_get_args();
        $this->lastError = null;
        $this->lastReturn = null;
        $this->write($args);
        $this->loop->run();
        if ($this->lastError)
            throw new Exception($this->lastError);

        return $this->lastReturn;
    }

    /**
     * @param resource $stream
     * @return array
     */
    private function read($stream)
    {
        if (is_resource($stream))
            $message = fgets($stream);

        if ($message)
            $message = @unserialize($message);

        if (!$message || !is_array($message))
            $message = array();

        return $message;
    }

    private function write(array $message)
    {
        $message = serialize($message)."\n";
        fputs($this->writeStream, $message);
    }
}
