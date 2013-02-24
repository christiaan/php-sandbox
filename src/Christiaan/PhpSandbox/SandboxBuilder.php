<?php
namespace Christiaan\PhpSandbox;

class SandboxBuilder
{
    private $iniSettings;
    private $workingDirectory;
    private $childBinPath;
    private $phpPath;

    public function __construct()
    {
        $this->iniSettings = array();
        $this->childBinPath = __DIR__ . '/../../../bin/child.php';
        $this->phpPath = 'php';
    }

    /**
     * @param array $directories
     * @return $this
     * @throws \InvalidArgumentException when a dir does not exist
     */
    public function openBasedir(array $directories)
    {
        foreach ($directories as $dir)
            if (!is_dir($dir))
                throw new \InvalidArgumentException();

        $this->iniSettings['open_basedir'] = implode(PATH_SEPARATOR, $directories);

        return $this;
    }

    /**
     * @param string $dir
     * @return $this
     * @throws \InvalidArgumentException when dir does not exist
     */
    public function workingDirectory($dir)
    {
        if (!is_dir($dir))
            throw new \InvalidArgumentException();

        $this->workingDirectory = $dir;

        return $this;
    }

    /**
     * Disable the list of functions inside the sandbox
     *
     * @param array $functions
     * @return $this
     */
    public function disableFunctions(array $functions)
    {
        $this->iniSettings['disable_functions'] = implode(',', $functions);

        return $this;
    }

    /**
     * Disable the list of classes inside the sandbox
     *
     * @param array $classes
     * @return $this
     */
    public function disableClasses(array $classes)
    {
        $this->iniSettings['disable_classes'] = implode(',', $classes);

        return $this;
    }

    /**
     * Apply various settings to set up a secure sandbox
     *
     * @param string $openBasedir
     * @return $this
     */
    public function secureSandbox($openBasedir)
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
        $this->openBasedir(array($openBasedir));
        $this->workingDirectory($openBasedir);

        return $this;
    }

    /**
     * Set the PHP binary path
     *
     * @param string $path
     * @return $this
     */
    public function phpPath($path)
    {
        $this->phpPath = $path;

        return $this;
    }

    /**
     * Set the path to the child.php file
     *
     * @param string $path
     * @return $this
     */
    public function childBinPath($path)
    {
        $this->childBinPath = $path;

        return $this;
    }

    /**
     * @return PhpSandbox
     * @throws Exception
     */
    public function build()
    {
        return new PhpSandbox($this->spawnChildProcess());
    }

    /**
     * @return Process
     * @throws Exception
     */
    private function spawnChildProcess()
    {
        $childBin = $this->childBinPath;
        if (!is_file($childBin))
            throw new Exception('child.php not found generate it using bin/generateChild.php');

        $cwd = false;
        if ($this->workingDirectory) {
            $cwd = getcwd();
            chdir($this->workingDirectory);
        }

        $cmd = sprintf(
            '%s %s %s',
            $this->phpPath,
            $this->compileArgs(),
            escapeshellarg(realpath($childBin))
        );
        $child = new Process($cmd);

        if ($cwd)
            chdir($cwd);

        if (!$child->isOpen() || !$child->isRunning())
            throw new Exception('Failed to spawn child process');

        return $child;
    }

    private function compileArgs()
    {
        $args = array();
        foreach ($this->iniSettings as $key => $value)
            $args[] = sprintf('-d %s=%s', escapeshellarg($key), escapeshellarg($value));

        return implode(' ', $args);
    }
}