<?php
namespace Christiaan\PhpSandbox\Tests;

use Christiaan\PhpSandbox\Exception;
use Christiaan\PhpSandbox\PhpSandbox;

class SecureTest extends \PHPUnit_Framework_TestCase
{
    private $jail;
    /**
     * @var PhpSandbox
     */
    private $sandbox;

    protected function setUp()
    {
        parent::setUp();
        $this->jail = __DIR__.'/jail';
        $this->sandbox = PhpSandbox::builder()->secureSandbox($this->jail)->build();
    }


    public function testUnableToGetThisFile()
    {
        $thrown = false;
        try {
            $this->sandbox->execute('return file_get_contents("'.__FILE__.'");');
        } catch (Exception $e) {
            $thrown = true;
        }

        $this->assertTrue($thrown);
        $this->assertTrue(false !== stripos($e->getMessage(), 'Operation not permitted'));
    }

    /**
     * @expectedException \Christiaan\PhpSandbox\Exception
     */
    public function testUnableToIncludeFile()
    {
        $this->sandbox->execute('include "'.__FILE__.'";');
    }

    public function testChildCanReadJailedFile()
    {
        $res = $this->sandbox->execute('return file_get_contents("'. $this->jail.'/jailed.txt");');
        $this->assertStringEqualsFile($this->jail . '/jailed.txt', $res);
    }

    public function testChildCwdIsTheJail()
    {
        $res = $this->sandbox->execute('return file_get_contents("jailed.txt");');
        $this->assertStringEqualsFile($this->jail . '/jailed.txt', $res);
    }
}