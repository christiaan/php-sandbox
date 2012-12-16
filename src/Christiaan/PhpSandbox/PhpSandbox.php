<?php
namespace Christiaan\PhpSandbox;

use React\EventLoop\Factory;

class PhpSandbox
{
    /** @var Process */
    private $child;
    /** @var RpcProtocol */
    private $protocol;
    private $disabledFunctions;
    private $disabledClasses;
    private $callbacks;
    private $output;

    public function __construct(array $disabledClasses = array('SplFileInfo'),
        array $disabledFunctions = array('dl'))
    {
        $this->disabledClasses = $disabledClasses;
        $this->disabledFunctions = $disabledFunctions;
        $this->callbacks = array();
        $this->output = '';
        $this->assignCallback('output', array($this, 'addOutput'));
    }

    /**
     * @param string $name
     * @param mixed $value
     */
    public function assignVar($name, $value)
    {
        $this->call('assignVar', array($name, $value));
    }

    /**
     * @api
     * @param string $name
     * @param mixed $callable
     */
    public function assignCallback($name, $callable)
    {
        $child = $this->getRpcProtocol();
        $child->addCallback($name, $callable);
        $this->callbacks[$name] = $callable;
    }

    /**
     * @api
     * @param string $name
     * @param $args
     * @return mixed
     * @throws Exception
     */
    public function call($name, array $args)
    {
        $child = $this->getRpcProtocol();
        if (!$this->child->isRunning()) {
            throw new Exception('Child Process died');
        }
        return $child->call($name, $args);
    }

    /**
     * @api
     * @param string $php
     * @return mixed
     */
    public function execute($php)
    {
        return $this->call('execute', array($php));
    }

    /**
     * @api
     * @return string
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * @param $output
     */
    public function addOutput($output)
    {
        $this->output .= $output;
    }

    private function getRpcProtocol()
    {
        if (!$this->protocol) {
            $childBin = __DIR__.'/../../../bin/child.php';
            if (!is_file($childBin))
                throw new Exception('child.php not found generate it using bin/generateChild.php');
                $this->child = new Process(
                sprintf(
                    '/usr/bin/php -d disabled_functions=%s -d disabled_classes=%s %s',
                    escapeshellarg(implode(',', $this->disabledFunctions)),
                    escapeshellarg(implode(',', $this->disabledClasses)),
                    escapeshellarg(realpath($childBin))
                )
            );
            if (!$this->child->isOpen() || !$this->child->isRunning())
                throw new Exception('Failed to spawn child process');

            $this->protocol = new RpcProtocol(
                $this->child->getReadStream(),
                $this->child->getWriteStream(),
                $this->child->getErrorStream(),
                Factory::create()
            );
        }
        return $this->protocol;
    }
}
