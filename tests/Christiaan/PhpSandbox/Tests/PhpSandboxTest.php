<?php
namespace Christiaan\PhpSandbox\Tests;

use Christiaan\PhpSandbox\PhpSandbox;

class PhpSandboxTest extends \PHPUnit_Framework_TestCase
{
    function testBasicSandboxUsage()
    {
        $sandbox = new PhpSandbox();
        $sandbox->assignVar('iets', 10);
        $res = $sandbox->execute('return $iets;');
        $this->assertEquals(10, $res);
    }

    function testCallback()
    {
        $sandbox = new PhpSandbox();
        $sandbox->assignCallback('multiply', function($a) { return $a * $a; });
        $res = $sandbox->execute('return $parent->multiply(2);');
        $this->assertEquals(4, $res);
    }

    function testOutputHandler()
    {
        $sandbox = new PhpSandbox();
        $sandbox->execute('echo "hoi";');
        $this->assertEquals('hoi', $sandbox->getOutput());
    }

    function testErrorHandler()
    {
        $sandbox = new PhpSandbox();

        try {
            $sandbox->execute('some syntax error');
            $thrown = false;
        } catch (\Exception $e) {
            $thrown = true;
            $this->assertTrue(false !== strpos($e->getMessage(), 'PHP Parse error:  syntax error'));
        }
        $this->assertTrue($thrown);
    }

    function testReturnedFunction()
    {
        $sandbox = new PhpSandbox();

        /** @var $closure \Christiaan\PhpSandbox\SandboxClosure */
        $closure = $sandbox->execute(<<<CODE
return function() { return 1337; };
CODE
        );

        $this->assertInstanceOf('Christiaan\PhpSandbox\SandboxClosure', $closure);
        $this->assertEquals(1337, $closure());
    }

    function testAssignedObject()
    {
        $sandbox = new PhpSandbox();

        $object = new \ArrayObject(array());
        $object->setProperty = 12;
        $sandbox->assignObject('object', $object);
        $sandbox->execute(<<<CODE
            \$object->append(10);
            if (isset(\$object->setProperty)) {
                \$object->testSet = \$object->setProperty;
            }
CODE
        );
        $this->assertEquals(1, count($object));
        $this->assertEquals(10, $object[0]);
        $this->assertEquals(12, $object->testSet);
    }
}
