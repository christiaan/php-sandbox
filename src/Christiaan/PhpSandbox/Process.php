<?php
namespace Christiaan\PhpSandbox;

use RuntimeException;

class Process
{
    private $cmd;
    private $pipes;
    private $cwd;
    private $env;
    private $res;
    private $returnCode;

    public function __construct($cmd, $cwd = null, array $env = null)
    {
        if (!function_exists('proc_open'))
            throw new RuntimeException('The Process class relies on proc_open, which is not available on your PHP installation.');

        $this->cmd = $cmd;
        $this->cwd = null === $cwd ? getcwd() : $cwd;
        if (null !== $env) {
            $this->env = array();
            foreach ($env as $key => $value) {
                $this->env[(binary) $key] = (binary) $value;
            }
        } else {
            $this->env = null;
        }
        $this->pipes = array();
        $this->open();
    }

    public function __destruct()
    {
        if ($this->isOpen())
            $this->close();
    }

    /**
     * @throws ProcessException
     */
    public function open()
    {
        if ($this->isOpen())
            throw new ProcessException('Process already opened', ProcessException::ALREADY_OPEN);

        $spec = array(array("pipe", "r"), array("pipe", "w"), array("pipe", "w"));
        $res = proc_open($this->cmd, $spec, $this->pipes, $this->cwd, $this->env);
        if ($res === false)
            throw new ProcessException(sprintf('Unable to proc_open cmd: %s', $this->cmd), ProcessException::OPEN_FAILED);

        $this->res = $res;
    }

    /**
     * @return bool
     */
    public function isOpen()
    {
        return is_resource($this->res);
    }

    /**
     * @return bool
     */
    public function isRunning()
    {
        $status = $this->getStatus();
        return isset($status['running']) && $status['running'];
    }

    /**
     * @return int exit code of process
     * @throws ProcessException when process is already closed
     */
    public function close()
    {
        if (!$this->isOpen())
            throw new ProcessException('Trying to close non open process', ProcessException::NOT_OPEN);

        $status = $this->getStatus();
        if ($status['running']);
            $this->returnCode = proc_close($this->res);

        return $this->returnCode;
    }

    /**
     * @return resource
     */
    public function getWriteStream()
    {
        return $this->pipes[0];
    }

    /**
     * @return resource
     */
    public function getReadStream()
    {
        return $this->pipes[1];
    }

    /**
     * @return resource
     */
    public function getErrorStream()
    {
        return $this->pipes[2];
    }

    /**
     * @return array
     */
    private function getStatus()
    {
        $status = proc_get_status($this->res);
        if (!$status['running'] && $status['exitcode'] != -1)
            $this->returnCode = (int) $status['exitcode'];

        return $status;
    }
}
