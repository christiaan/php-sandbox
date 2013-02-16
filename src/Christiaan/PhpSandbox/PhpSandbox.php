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

    public function __construct(Process $child)
    {
        $this->child = $child;
        $this->protocol = new RpcProtocol(
            $this->child->getReadStream(),
            $this->child->getWriteStream(),
            $this->child->getErrorStream(),
            Factory::create()
        );
        $this->output = '';
        $output = &$this->output;
        $this->assignCallback('output', function($out) use(&$output) {
            $output .= $out;
        });
    }

    /**
     * @return SandboxBuilder
     */
    public static function builder()
    {
        return new SandboxBuilder();
    }

    /**
     * @param string $name
     * @param callable $callable
     */
    public function assignCallback($name, $callable)
    {
        $this->protocol->registerCallback($name, $callable);
    }

    /**
     * @param string $name
     * @param object $object
     */
    public function assignObject($name, $object)
    {
        $proxy = new SandboxProxyObject($object);
        $proxy->assignInSandbox($this, $name);
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
        if (!$this->child->isRunning()) {
            throw new Exception('Child Process died');
        }
        return $this->protocol->sendCall($name, $args);
    }

    /**
     * @api
     * @return string the output generated by the child
     */
    public function getOutput()
    {
        return $this->output;
    }
}
