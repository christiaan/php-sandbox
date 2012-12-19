<?php
namespace Christiaan\PhpSandbox\Tests;

use Christiaan\PhpSandbox\PhpSandbox;

class PhpSandboxTest extends \PHPUnit_Framework_TestCase
{
    function testBasicSandboxUsage()
    {
        $sandbox = new PhpSandbox();
        $sandbox->assignVar('iets', 10);
        $res = $sandbox->execute('return $this->data[\'iets\'];');
        $this->assertEquals(10, $res);
    }

    function testCallback()
    {
        $sandbox = new PhpSandbox();
        $sandbox->assignCallback('multiply', function($a) { return $a * $a; });
        $res = $sandbox->execute('return $this->multiply(2);');
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
}
