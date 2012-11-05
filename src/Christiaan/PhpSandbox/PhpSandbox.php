<?php
namespace Christiaan\PhpSandbox;

class PhpSandbox
{
    private $childBin = '../../../bin/child.php';
    private $child;
    private $loop;
    private $disabledFunctions;
    private $disabledClasses;
    private $returnValue;
    private $returnError;
    private $callbacks;

    public function __construct(array $disabledClasses = array(), array $disabledFunctions = array())
    {
        $this->disabledClasses = $disabledClasses;
        $this->disabledFunctions = $disabledFunctions;
        $this->callbacks = array();
    }

    private function sendAndReceiveAnswer($action, $args)
    {
        $this->returnValue = null;
        $child = $this->getChildProcess();
        $loop = \React\EventLoop\Factory::create();
        $loop->addReadStream($child->getReadStream(), array($this, 'receive'));
        $this->send(array($action, $args));
        $loop->run();
        return $this->returnValue;
    }

    private function send(array $message)
    {
        $child = $this->getChildProcess();
        $message = serialize($message)."\n";
        fputs($child->getWriteStream(), $message);
    }

    public function receive($stream, \React\EventLoop\LoopInterface $loop)
    {
        $message = fgets($stream);
        $message = unserialize($message);
        if (is_array($message)) {
            $action = array_shift($message);
            if ($action === 'return') {
                $this->returnValue = array_shift($message);
                $loop->stop();
            }
            if ($action === 'error') {
                $this->returnError = array_shift($message);
                $loop->stop();
            }
            if ($action === 'callParent') {
                $method = array_shift($message);
                if (!array_key_exists($method, $this->callbacks))
                    $this->send(array());

            }
        }
    }

    private function getChildProcess()
    {
        if (!$this->child) {
            $this->child = new Process(
                sprintf(
                    '/usr/bin/php -d disabled_functions=%s -d disabled_classes=%s %s',
                    escapeshellarg(implode(',', $this->disabledFunctions)),
                    escapeshellarg(implode(',', $this->disabledClasses)),
                    escapeshellarg(realpath($this->childBin))
                )
            );
        }
        return $this->child;
    }
}
