<?php
namespace Christiaan\PhpSandbox;

class ProcessException extends Exception
{
    const ALREADY_OPEN = 1;
    const NOT_OPEN = 2;
    const OPEN_FAILED = 3;
}
