<?php
namespace Christiaan\PhpSandbox\Child;
 
class SandboxObjectProxy
{
    /** @var SandboxParent */
    private $parent;
    private $name;

    public function __construct(SandboxParent $parent, $name)
    {
        $this->parent = $parent;
        $this->name = $name;
    }

    public function __call($name, array $arguments)
    {
        return $this->callParent('call', array($name, $arguments));
    }

    public function __isset($name)
    {
        return $this->callParent('isset', array($name));
    }

    public function __get($name)
    {
        return $this->callParent('get', array($name));
    }

    public function __set($name, $value)
    {
        return $this->callParent('set', array($name, $value));
    }

    /**
     * @param $action
     * @param $arguments
     * @internal param $name
     * @return mixed
     */
    private function callParent($action, $arguments)
    {
        return $this->parent->{'__objectProxy_' . $this->name}($action, $arguments);
    }
}
