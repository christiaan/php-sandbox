<?php
namespace Christiaan\PhpSandbox\Tests;

use Christiaan\PhpSandbox\PhpSandbox;

class PhpSandboxTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var PhpSandbox
     */
    private $sandbox;

    protected function setUp()
    {
        parent::setUp();
        $this->sandbox = PhpSandbox::builder()->build();
    }


    function testBasicSandboxUsage()
    {
        $this->sandbox->assignVar('iets', 10);
        $res = $this->sandbox->execute('return $iets;');
        $this->assertEquals(10, $res);
    }

    function testCallback()
    {
        $this->sandbox->assignCallback('multiply', function($a) { return $a * $a; });
        $res = $this->sandbox->execute('return $parent->multiply(2);');
        $this->assertEquals(4, $res);
    }

    function testOutputHandler()
    {
        $this->sandbox->execute('echo "hoi";');
        $this->assertEquals('hoi', $this->sandbox->getOutput());
    }

    function testErrorHandler()
    {
        try {
            $this->sandbox->execute('some syntax error');
            $thrown = false;
        } catch (\Exception $e) {
            $thrown = true;
            $this->assertTrue(false !== strpos($e->getMessage(), 'PHP Parse error:  syntax error'));
        }
        $this->assertTrue($thrown);
    }

    function testReturnedFunction()
    {
        /** @var $closure \Christiaan\PhpSandbox\SandboxClosure */
        $closure = $this->sandbox->execute(<<<CODE
return function() { return 1337; };
CODE
        );

        $this->assertInstanceOf('Christiaan\PhpSandbox\SandboxClosure', $closure);
        $this->assertEquals(1337, $closure());
    }

    function testAssignedObject()
    {
        $object = new \ArrayObject(array());
        $object->setProperty = 12;
        $this->sandbox->assignObject('object', $object);
        $this->sandbox->execute(<<<CODE
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

    function testErrorInParentCanBeCaught()
    {
        $this->sandbox->assignCallback('callbackWithError', function() {
                trigger_error('error in this callback', E_USER_ERROR);
            });

        $res = $this->sandbox->execute(<<<CODE
            try {
                \$parent->callbackWithError('test');
            } catch(\Exception \$e) {
                return \$e->getMessage();
            }
CODE
        );

        $this->assertEquals('error in this callback', $res);
    }
}
