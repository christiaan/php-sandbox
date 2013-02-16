<?php
namespace Christiaan\PhpSandbox;
 
class SandboxProxyObject
{
    private $object;

    public function __construct($object)
    {
        if (!is_object($object))
            throw new \InvalidArgumentException('Object expected');
        $this->object = $object;
    }

    public function assignInSandbox(PhpSandbox $sandbox, $name)
    {
        $sandbox->assignCallback('__objectProxy_' . $name, array($this, 'sandboxCallback'));
        $sandbox->call('assignObject', array($name));
    }

    public function sandboxCallback($action, array $arguments)
    {
        if ($action === 'call') {
            list($name, $args) = $arguments;
            return call_user_func_array(array($this->object, $name), $args);
        } else if ($action === 'isset') {
            list($name) = $arguments;
            return isset($this->object->{$name});
        } else if ($action === 'get') {
            list($name) = $arguments;
            return $this->object->{$name};
        } else if ($action === 'set') {
            list($name, $value) = $arguments;
            $this->object->{$name} = $value;
        }
        return null;
    }
}
