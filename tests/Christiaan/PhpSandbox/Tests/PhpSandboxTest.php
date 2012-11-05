<?php
namespace Christiaan\PhpSandbox\Tests;

class PhpSandboxTest extends \PHPUnit_Framework_TestCase
{
    function testSandboxRun()
    {
        $sandbox = new PhpSandbox();
        $res =
        $sandbox->run('return 10;');
        $this->assertEquals(10, $res);
    }
}
