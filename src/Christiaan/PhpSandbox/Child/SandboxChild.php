<?php
namespace Christiaan\PhpSandbox\Child;
 
use Christiaan\PhpSandbox\RpcProtocol;

class SandboxChild
{
    private $protocol;
    private $parent;

    public function __construct(RpcProtocol $protocol)
    {
        $this->protocol = $protocol;
        $this->setupErrorHandlers();
        $this->parent = new SandboxParent($protocol);
        $this->setupCallbacks();
        $this->setupOutputBuffering();
    }

    private function setupCallbacks()
    {
        $parent = $this->parent;
        $data = array();
        $this->protocol->registerCallback(
            'execute',
            function ($code) use ($parent, &$data) {
                extract($data);
                return eval($code);
            }
        );
        $this->protocol->registerCallback(
            'assignVar',
            function ($key, $value) use (&$data) {
                $data[$key] = $value;
            }
        );
        $this->protocol->registerCallback(
            'assignObject',
            function ($key) use (&$data, $parent) {
                $data[$key] = new SandboxObjectProxy($parent, $key);
            }
        );
    }

    private function setupErrorHandlers()
    {
        $protocol = $this->protocol;
        $exceptionHandler = function (\Exception $exception) use ($protocol) {
            $protocol->sendError($exception->getMessage());
        };
        set_exception_handler($exceptionHandler);

        $errorHandler = function ($errno, $errstr, $errfile, $errline) use ($exceptionHandler) {
            $exceptionHandler(new \ErrorException($errstr, $errno, 0, $errfile, $errline));
        };
        set_error_handler($errorHandler);
    }

    private function setupOutputBuffering()
    {
        $protocol = $this->protocol;
        ob_start(
            function ($output) use ($protocol) {
                $protocol->sendCall('output', array($output));
                return '';
            },
            // Prior to PHP 5.4.0, the value 1 was a special case value that set the chunk size to 4096 bytes.
            version_compare(PHP_VERSION, '5.4', '>=') ? 1 : 2
        );
    }
}
