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
    }

    private function setupCallbacks()
    {
        $parent = $this->parent;
        $data = array();
        $this->protocol->registerCallback(
            'execute',
            function ($code) use ($parent, &$data) {
                extract($data, EXTR_SKIP);
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
}
