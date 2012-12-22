<?php
namespace Christiaan\PhpSandbox;

use React\EventLoop\Factory;

class PhpSandbox
{
    /** @var Process */
    private $child;
    /** @var RpcProtocol */
    private $protocol;
    private $output;
    private $iniSettings;

    public function __construct(array $iniSettings = array())
    {
        $this->iniSettings = $iniSettings;
        $this->output = '';
        $output = &$this->output;
        $this->assignCallback('output', function($out) use(&$output) {
            $output .= $out;
        });
    }

    /**
     * @param string $name
     * @param callable $callable
     */
    public function assignCallback($name, $callable)
    {
        $this->getRpcProtocol()->registerCallback($name, $callable);
    }

    /**
     * @param string $name
     * @param mixed $value
     * @return mixed
     */
    public function assignVar($name, $value)
    {
        return $this->call('assignVar', array($name, $value));
    }

    /**
     * @param string $code
     * @return mixed
     */
    public function execute($code)
    {
        return $this->call('execute', array($code));
    }

    /**
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
        return $child->sendCall($name, $args);
    }

    /**
     * @api
     * @return string the output generated by the child
     */
    public function getOutput()
    {
        return $this->output;
    }

    private function getRpcProtocol()
    {
        if (!$this->protocol) {
            $childBin = __DIR__.'/../../../bin/child.php';
            if (!is_file($childBin))
                throw new Exception('child.php not found generate it using bin/generateChild.php');

            $cmd = sprintf(
                'php %s %s',
                $this->getChildArgs(),
                escapeshellarg(realpath($childBin))
            );
            $this->child = new Process($cmd);
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

    private function getChildArgs()
    {
        $args = array();
        foreach ($this->iniSettings as $key => $value) {
            $args .= sprintf('-d %s=%s', escapeshellarg($key), escapeshellarg($value));
        }
        return implode(' ', $args);
    }
}
