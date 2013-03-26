<?php 
namespace Christiaan\PhpSandbox
{
use React\EventLoop\LoopInterface;
class RpcProtocol
{
private $writeStream;
private $loop;
private $lastReturn;
private $lastError;
private $callbacks;
private $closureCounter = 1;
public function __construct($readStream, $writeStream, $errorStream,
LoopInterface $loop)
{
$this->assertResource($readStream);
$this->assertResource($writeStream);
$this->assertResource($errorStream);
$this->writeStream = $writeStream;
$this->loop = $loop;
$this->loop->addReadStream(
$readStream, array($this,'receive')
);
$this->loop->addReadStream(
$errorStream, array($this,'receiveError')
);
$this->callbacks = array();
}
public function registerCallback($name, $callable)
{
if (!is_callable($callable))
throw new \InvalidArgumentException();
$this->callbacks[$name] = $callable;
}
public function sendReturn($value)
{
if ($value instanceof \Closure) {
$name ='__closure_'. $this->closureCounter;
$this->registerCallback($name, $value);
$this->closureCounter += 1;
$this->send('returnClosure', $name);
return;
}
$this->send('return', $value);
}
public function sendError($message)
{
$this->send('error', $message);
}
public function sendCall($name, array $args)
{
return $this->send('call', $name, $args);
}
public function receive($stream)
{
$message = $this->readMessage($stream);
$action = array_shift($message);
if ($action ==='return') {
$this->lastReturn = array_shift($message);
$this->loop->stop();
}
if ($action ==='returnClosure') {
$this->lastReturn = new SandboxClosure($this, array_shift($message));
$this->loop->stop();
}
if ($action ==='error') {
$this->lastError = array_shift($message);
$this->loop->stop();
}
if ($action ==='call') {
$method = array_shift($message);
$args = array_shift($message);
if (!array_key_exists($method, $this->callbacks))
$this->sendError('Invalid Method');
try {
$ret = $this->callCallback($method, $args);
$this->sendReturn($ret);
} catch (\Exception $e) {
$this->sendError($e->getMessage());
}
}
}
public function receiveError($stream)
{
$error = fgets($stream);
$this->lastError = $error;
$this->loop->stop();
}
private function send()
{
$args = func_get_args();
$this->lastError = null;
$this->lastReturn = null;
$this->writeMessage($args);
$this->loop->run();
if ($this->lastError)
throw new Exception($this->lastError);
return $this->lastReturn;
}
private function readMessage($stream)
{
$this->assertResource($stream);
$message = fgets($stream);
if ($message)
$message = json_decode($message, true);
if (!$message || !is_array($message))
$message = array();
return $message;
}
private function writeMessage(array $message)
{
$message = json_encode($message).PHP_EOL;
fputs($this->writeStream, $message);
}
private function callCallback($method, $args)
{
$error = null;
set_error_handler(function($code, $message, $file, $line) use(&$error) {
$error = new \ErrorException($message, $code, 0, $file, $line);
});
$ret = call_user_func_array($this->callbacks[$method], $args);
restore_error_handler();
if ($error instanceof \ErrorException)
throw $error;
return $ret;
}
private function assertResource($stream)
{
if (!is_resource($stream))
throw new \InvalidArgumentException();
}
}
}
namespace Christiaan\PhpSandbox\Child
{
use Christiaan\PhpSandbox\RpcProtocol;
class SandboxParent
{
private $protocol;
public function __construct(RpcProtocol $protocol)
{
$this->protocol = $protocol;
}
public function __call($name, $args)
{
$ret = $this->protocol->sendCall($name, $args);
return $ret;
}
}
}
namespace Christiaan\PhpSandbox\Child
{
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
$this->protocol->registerCallback('execute',
function ($code) use ($parent, &$data) {
extract($data);
return eval($code);
}
);
$this->protocol->registerCallback('assignVar',
function ($key, $value) use (&$data) {
$data[$key] = $value;
}
);
$this->protocol->registerCallback('assignObject',
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
return'';
},
version_compare(PHP_VERSION,'5.4','>=') ? 1 : 2
);
}
}
}
namespace Christiaan\PhpSandbox\Child
{
class SandboxObjectProxy
{
private $parent;
private $name;
public function __construct(SandboxParent $parent, $name)
{
$this->parent = $parent;
$this->name = $name;
}
public function __call($name, array $arguments)
{
return $this->callParent('call', array($name, $arguments));
}
public function __isset($name)
{
return $this->callParent('isset', array($name));
}
public function __get($name)
{
return $this->callParent('get', array($name));
}
public function __set($name, $value)
{
return $this->callParent('set', array($name, $value));
}
private function callParent($action, $arguments)
{
return $this->parent->{'__objectProxy_'. $this->name}($action, $arguments);
}
}
}
namespace Christiaan\PhpSandbox
{
class Exception extends \Exception
{
}
}
namespace React\EventLoop
{
use React\EventLoop\StreamSelectLoop;
use React\EventLoop\LibEventLoop;
class Factory
{
public static function create()
{
if (function_exists('event_base_new')) {
return new LibEventLoop();
}
return new StreamSelectLoop();
}
}
}
namespace React\EventLoop\Timer
{
use React\EventLoop\LoopInterface;
class Timers
{
const MIN_RESOLUTION = 0.001;
private $loop;
private $time;
private $active = array();
private $timers;
public function __construct(LoopInterface $loop)
{
$this->loop = $loop;
$this->timers = new \SplPriorityQueue();
}
public function updateTime()
{
return $this->time = microtime(true);
}
public function getTime()
{
return $this->time ?: $this->updateTime();
}
public function add($interval, $callback, $periodic = false)
{
if ($interval < self::MIN_RESOLUTION) {
throw new \InvalidArgumentException('Timer events do not support sub-millisecond timeouts.');
}
if (!is_callable($callback)) {
throw new \InvalidArgumentException('The callback must be a callable object.');
}
$interval = (float) $interval;
$timer = (object) array('interval'=> $interval,'callback'=> $callback,'periodic'=> $periodic,'scheduled'=> $interval + $this->getTime(),
);
$timer->signature = spl_object_hash($timer);
$this->timers->insert($timer, -$timer->scheduled);
$this->active[$timer->signature] = $timer;
return $timer->signature;
}
public function cancel($signature)
{
unset($this->active[$signature]);
}
public function getFirst()
{
if ($this->timers->isEmpty()) {
return null;
}
return $this->timers->top()->scheduled;
}
public function isEmpty()
{
return !$this->active;
}
public function tick()
{
$time = $this->updateTime();
$timers = $this->timers;
while (!$timers->isEmpty() && $timers->top()->scheduled < $time) {
$timer = $timers->extract();
if (isset($this->active[$timer->signature])) {
call_user_func($timer->callback, $timer->signature, $this->loop);
if ($timer->periodic === true) {
$timer->scheduled = $timer->interval + $time;
$timers->insert($timer, -$timer->scheduled);
} else {
unset($this->active[$timer->signature]);
}
}
}
}
}
}
namespace React\EventLoop
{
interface LoopInterface
{
public function addReadStream($stream, $listener);
public function addWriteStream($stream, $listener);
public function removeReadStream($stream);
public function removeWriteStream($stream);
public function removeStream($stream);
public function addTimer($interval, $callback);
public function addPeriodicTimer($interval, $callback);
public function cancelTimer($signature);
public function tick();
public function run();
public function stop();
}
}
namespace React\EventLoop
{
use React\EventLoop\Timer\Timers;
class StreamSelectLoop implements LoopInterface
{
const QUANTUM_INTERVAL = 1000000;
private $timers;
private $running = false;
private $readStreams = array();
private $readListeners = array();
private $writeStreams = array();
private $writeListeners = array();
public function __construct()
{
$this->timers = new Timers($this);
}
public function addReadStream($stream, $listener)
{
$id = (int) $stream;
if (!isset($this->readStreams[$id])) {
$this->readStreams[$id] = $stream;
$this->readListeners[$id] = $listener;
}
}
public function addWriteStream($stream, $listener)
{
$id = (int) $stream;
if (!isset($this->writeStreams[$id])) {
$this->writeStreams[$id] = $stream;
$this->writeListeners[$id] = $listener;
}
}
public function removeReadStream($stream)
{
$id = (int) $stream;
unset(
$this->readStreams[$id],
$this->readListeners[$id]
);
}
public function removeWriteStream($stream)
{
$id = (int) $stream;
unset(
$this->writeStreams[$id],
$this->writeListeners[$id]
);
}
public function removeStream($stream)
{
$this->removeReadStream($stream);
$this->removeWriteStream($stream);
}
public function addTimer($interval, $callback)
{
return $this->timers->add($interval, $callback);
}
public function addPeriodicTimer($interval, $callback)
{
return $this->timers->add($interval, $callback, true);
}
public function cancelTimer($signature)
{
$this->timers->cancel($signature);
}
protected function getNextEventTimeInMicroSeconds()
{
$nextEvent = $this->timers->getFirst();
if (null === $nextEvent) {
return self::QUANTUM_INTERVAL;
}
$currentTime = microtime(true);
if ($nextEvent > $currentTime) {
return ($nextEvent - $currentTime) * 1000000;
}
return 0;
}
protected function sleepOnPendingTimers()
{
if ($this->timers->isEmpty()) {
$this->running = false;
} else {
usleep($this->getNextEventTimeInMicroSeconds());
}
}
protected function runStreamSelect()
{
$read = $this->readStreams ?: null;
$write = $this->writeStreams ?: null;
$except = null;
if (!$read && !$write) {
$this->sleepOnPendingTimers();
return;
}
if (stream_select($read, $write, $except, 0, $this->getNextEventTimeInMicroSeconds()) > 0) {
if ($read) {
foreach ($read as $stream) {
$listener = $this->readListeners[(int) $stream];
if (call_user_func($listener, $stream, $this) === false) {
$this->removeReadStream($stream);
}
}
}
if ($write) {
foreach ($write as $stream) {
if (!isset($this->writeListeners[(int) $stream])) {
continue;
}
$listener = $this->writeListeners[(int) $stream];
if (call_user_func($listener, $stream, $this) === false) {
$this->removeWriteStream($stream);
}
}
}
}
}
public function tick()
{
$this->timers->tick();
$this->runStreamSelect();
return $this->running;
}
public function run()
{
$this->running = true;
while ($this->tick()) {
}
}
public function stop()
{
$this->running = false;
}
}
}
namespace React\EventLoop
{
class LibEventLoop implements LoopInterface
{
const MIN_TIMER_RESOLUTION = 0.001;
private $base;
private $callback;
private $timers = array();
private $events = array();
private $flags = array();
private $readCallbacks = array();
private $writeCallbacks = array();
public function __construct()
{
$this->base = event_base_new();
$this->callback = $this->createLibeventCallback();
}
protected function createLibeventCallback()
{
$readCallbacks = &$this->readCallbacks;
$writeCallbacks = &$this->writeCallbacks;
return function ($stream, $flags, $loop) use (&$readCallbacks, &$writeCallbacks) {
$id = (int) $stream;
try {
if (($flags & EV_READ) === EV_READ && isset($readCallbacks[$id])) {
if (call_user_func($readCallbacks[$id], $stream, $loop) === false) {
$loop->removeReadStream($stream);
}
}
if (($flags & EV_WRITE) === EV_WRITE && isset($writeCallbacks[$id])) {
if (call_user_func($writeCallbacks[$id], $stream, $loop) === false) {
$loop->removeWriteStream($stream);
}
}
} catch (\Exception $ex) {
$loop->stop();
throw $ex;
}
};
}
public function addReadStream($stream, $listener)
{
$this->addStreamEvent($stream, EV_READ,'read', $listener);
}
public function addWriteStream($stream, $listener)
{
$this->addStreamEvent($stream, EV_WRITE,'write', $listener);
}
protected function addStreamEvent($stream, $eventClass, $type, $listener)
{
$id = (int) $stream;
if ($existing = isset($this->events[$id])) {
if (($this->flags[$id] & $eventClass) === $eventClass) {
return;
}
$event = $this->events[$id];
event_del($event);
} else {
$event = event_new();
}
$flags = isset($this->flags[$id]) ? $this->flags[$id] | $eventClass : $eventClass;
event_set($event, $stream, $flags | EV_PERSIST, $this->callback, $this);
if (!$existing) {
event_base_set($event, $this->base);
}
event_add($event);
$this->events[$id] = $event;
$this->flags[$id] = $flags;
$this->{"{$type}Callbacks"}[$id] = $listener;
}
public function removeReadStream($stream)
{
$this->removeStreamEvent($stream, EV_READ,'read');
}
public function removeWriteStream($stream)
{
$this->removeStreamEvent($stream, EV_WRITE,'write');
}
protected function removeStreamEvent($stream, $eventClass, $type)
{
$id = (int) $stream;
if (isset($this->events[$id])) {
$flags = $this->flags[$id] & ~$eventClass;
if ($flags === 0) {
return $this->removeStream($stream);
}
$event = $this->events[$id];
event_del($event);
event_free($event);
unset($this->{"{$type}Callbacks"}[$id]);
$event = event_new();
event_set($event, $stream, $flags | EV_PERSIST, $this->callback, $this);
event_base_set($event, $this->base);
event_add($event);
$this->events[$id] = $event;
$this->flags[$id] = $flags;
}
}
public function removeStream($stream)
{
$id = (int) $stream;
if (isset($this->events[$id])) {
$event = $this->events[$id];
unset(
$this->events[$id],
$this->flags[$id],
$this->readCallbacks[$id],
$this->writeCallbacks[$id]
);
event_del($event);
event_free($event);
}
}
protected function addTimerInternal($interval, $callback, $periodic = false)
{
if ($interval < self::MIN_TIMER_RESOLUTION) {
throw new \InvalidArgumentException('Timer events do not support sub-millisecond timeouts.');
}
if (!is_callable($callback)) {
throw new \InvalidArgumentException('The callback must be a callable object.');
}
$timer = (object) array('loop'=> $this,'resource'=> $resource = event_new(),'callback'=> $callback,'interval'=> $interval * 1000000,'periodic'=> $periodic,'cancelled'=> false,
);
$timer->signature = spl_object_hash($timer);
$that = $this;
$callback = function () use ($timer, $that, &$callback) {
if ($timer->cancelled === false) {
call_user_func($timer->callback, $timer->signature, $timer->loop);
if ($timer->periodic === true && $timer->cancelled === false) {
event_add($timer->resource, $timer->interval);
} else {
$that->cancelTimer($timer->signature);
}
}
};
event_timer_set($resource, $callback);
event_base_set($resource, $this->base);
event_add($resource, $interval * 1000000);
$this->timers[$timer->signature] = $timer;
return $timer->signature;
}
public function addTimer($interval, $callback)
{
return $this->addTimerInternal($interval, $callback);
}
public function addPeriodicTimer($interval, $callback)
{
return $this->addTimerInternal($interval, $callback, true);
}
public function cancelTimer($signature)
{
if (isset($this->timers[$signature])) {
$timer = $this->timers[$signature];
$timer->cancelled = true;
event_del($timer->resource);
event_free($timer->resource);
unset($this->timers[$signature]);
}
}
public function tick()
{
event_base_loop($this->base, EVLOOP_ONCE | EVLOOP_NONBLOCK);
}
public function run()
{
event_base_loop($this->base);
}
public function stop()
{
event_base_loopexit($this->base);
}
}
}
namespace {
    $loop = \React\EventLoop\Factory::create();
    $child = new Christiaan\PhpSandbox\Child\SandboxChild(
        new \Christiaan\PhpSandbox\RpcProtocol(
            STDIN, STDOUT, STDERR, $loop
        )
    );
    while ($loop->run()) {
        // NOOP
    }
}