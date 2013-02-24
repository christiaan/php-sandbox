<?php
namespace Christiaan\PhpSandbox\Tests;

use Christiaan\PhpSandbox\SandboxBuilder;

class BuilderOptionsTest extends \PHPUnit_Framework_TestCase
{
    /** @var SandboxBuilder */
    private $builder;

    protected function setUp()
    {
        parent::setUp();
        $this->builder = new SandboxBuilder();
    }


    public function testPhpPath()
    {
        $phpPath = exec('which php');
        $sandbox = $this->builder->phpPath($phpPath)->build();
        $res = $sandbox->execute('return true;');
        $this->assertTrue($res);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testOpenBasedirThrowsWhenDirDoesNotExist()
    {
        $this->builder->openBasedir(array('nonExistingDir'));
    }
}