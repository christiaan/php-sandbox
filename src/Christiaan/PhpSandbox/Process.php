<?php
namespace Christiaan\PhpSandbox;

class Process
{
    private $cmd;
    private $pipes;
    private $cwd;
    private $env;
    private $res;
    private $returnCode;

    public function __construct($cmd)
    {
        $this->cmd = $cmd;
        $this->pipes = array();
        $this->open();
    }

    public function __destruct()
    {
        if ($this->isOpen())
            $this->close();
    }

    public function open()
    {
        if ($this->isOpen())
            throw new ProcessException('Process already opened');

        $spec = array(array("pipe", "r"), array("pipe", "w"), array("pipe", "w"));
        $res = proc_open($this->cmd, $spec, $this->pipes, $this->cwd, $this->env);
        if ($res === false)
            throw new ProcessException(sprintf('Unable to proc_open cmd: %s', $this->cmd));

        $this->res = $res;
    }

    public function isOpen()
    {
        return is_resource($this->res);
    }

    public function close()
    {
        if (!$this->isOpen())
            throw new ProcessException('Trying to close non open process');

        $status = $this->getStatus();
        if ($status['running']);
            $this->returnCode = proc_close($this->res);

        return $this->returnCode;
    }

    public function getWriteStream()
    {
        return $this->pipes[0];
    }

    public function getReadStream()
    {
        return $this->pipes[1];
    }

    public function getErrorStream()
    {
        return $this->pipes[2];
    }

    private function getStatus()
    {
        $status = proc_get_status($this->res);
        if (!$status['running'] && $status['exitcode'] != -1)
            $this->returnCode = (int) $status['exitcode'];

        return $status;
    }
}
