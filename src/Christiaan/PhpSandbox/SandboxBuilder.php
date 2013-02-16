<?php
namespace Christiaan\PhpSandbox;

class SandboxBuilder
{
    private $iniSettings;
    private $jailDir;

    public function __construct()
    {
        $this->iniSettings = array();
    }

    public function openBasedir(array $directories)
    {
        foreach ($directories as $dir)
            if (!is_dir($dir))
                throw new \InvalidArgumentException();

        $this->iniSettings['open_basedir'] = implode(PATH_SEPARATOR, $directories);
        return $this;
    }

    public function disableFunctions(array $functions)
    {
        $this->iniSettings['disable_functions'] = implode(',', $functions);
        return $this;
    }

    public function disableClasses(array $classes)
    {
        $this->iniSettings['disable_classes'] = implode(',', $classes);
        return $this;
    }

    public function secureSandbox($jailDir)
    {
        $this->disableFunctions(
            array(
                'exec',
                'passthru',
                'shell_exec',
                'system',
                'proc_open',
                'popen',
                'pcntl_fork',
                'pcntl_exec',
                'phpinfo',
                'ini_set'
            )
        );
        $this->openBasedir(array($jailDir));
        $this->jailDir = $jailDir;

        return $this;
    }

    public function build()
    {
        $cwd = false;
        if ($this->jailDir) {
            $cwd = getcwd();
            chdir($this->jailDir);
        }
        $childBin = __DIR__.'/../../../bin/child.php';
        if (!is_file($childBin))
            throw new Exception('child.php not found generate it using bin/generateChild.php');

        $cmd = sprintf(
            'php %s %s',
            $this->compileArgs(),
            escapeshellarg(realpath($childBin))
        );

        $child = new Process($cmd);
        if (!$child->isOpen() || !$child->isRunning())
            throw new Exception('Failed to spawn child process');

        if ($cwd)
            chdir($cwd);

        return new PhpSandbox($child);
    }

    private function compileArgs()
    {
        $args = array();
        foreach ($this->iniSettings as $key => $value)
            $args[] = sprintf('-d %s=%s', escapeshellarg($key), escapeshellarg($value));

        return implode(' ', $args);
    }
}