<?php
namespace Christiaan\PhpSandbox;

use React\EventLoop\LoopInterface;

class RpcProtocol
{
    private $writeStream;
    private $loop;
    private $lastReturn;
    private $lastError;
    private $callbacks;
    private $closureCounter = 1;

    public function __construct($readStream, $writeStream, $errorStream,
        LoopInterface $loop)
    {
        $this->assertResource($readStream);
        $this->assertResource($writeStream);
        $this->assertResource($errorStream);

        $this->writeStream = $writeStream;
        $this->loop = $loop;

        $this->loop->addReadStream(
            $readStream, array($this, 'receive')
        );
        $this->loop->addReadStream(
            $errorStream, array($this, 'receiveError')
        );
        $this->callbacks = array();
    }

    public function registerCallback($name, $callable)
    {
        if (!is_callable($callable))
            throw new \InvalidArgumentException();

        $this->callbacks[$name] = $callable;
    }

    public function sendReturn($value)
    {
        if ($value instanceof \Closure) {
            $name = '__closure_' . $this->closureCounter;
            $this->registerCallback($name, $value);
            $this->closureCounter += 1;
            $this->send('returnClosure', $name);
            return;
        }
        $this->send('return', $value);
    }

    public function sendError($message)
    {
        $this->send('error', $message);
    }

    public function sendCall($name, array $args)
    {
        return $this->send('call', $name, $args);
    }

    public function receive($stream)
    {
        $message = $this->readMessage($stream);
        $action = array_shift($message);
        if ($action === 'return') {
            $this->lastReturn = array_shift($message);
            $this->loop->stop();
        }
        if ($action === 'returnClosure') {
            $this->lastReturn = new SandboxClosure($this, array_shift($message));
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
                $this->sendError('Invalid Method');

            try {
                $ret = $this->callCallback($method, $args);
                $this->sendReturn($ret);
            } catch (\Exception $e) {
                $this->sendError($e->getMessage());
            }
        }
    }

    public function receiveError($stream)
    {
        $error = fgets($stream);
        $this->lastError = $error;
        $this->loop->stop();
    }

    private function send()
    {
        $args = func_get_args();
        $this->lastError = null;
        $this->lastReturn = null;
        $this->writeMessage($args);
        $this->loop->run();
        if ($this->lastError)
            throw new Exception($this->lastError);

        return $this->lastReturn;
    }

    /**
     * @param resource $stream
     * @throws \InvalidArgumentException
     * @return array
     */
    private function readMessage($stream)
    {
        $this->assertResource($stream);

        $message = fgets($stream);
        if ($message)
            $message = json_decode($message, true);

        if (!$message || !is_array($message))
            $message = array();

        return $message;
    }

    private function writeMessage(array $message)
    {
        $message = json_encode($message).PHP_EOL;
        fputs($this->writeStream, $message);
    }

    /**
     * @param $method
     * @param $args
     * @throws \ErrorException
     * @return mixed
     */
    private function callCallback($method, $args)
    {
        $error = null;
        set_error_handler(function($code, $message, $file, $line) use(&$error) {
                $error = new \ErrorException($message, $code, 0, $file, $line);
            });
        $ret = call_user_func_array($this->callbacks[$method], $args);
        restore_error_handler();
        if ($error instanceof \ErrorException)
            throw $error;

        return $ret;
    }

    /**
     * @param $stream
     * @throws \InvalidArgumentException
     */
    private function assertResource($stream)
    {
        if (!is_resource($stream))
            throw new \InvalidArgumentException();
    }
}
