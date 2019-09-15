<?php
declare(strict_types = 1);

namespace Tests\Innmind\ConservationMeasure\Command;

use Innmind\ConservationMeasure\{
    Command\CacheDaemon,
    Storage,
    Cache\Strategy,
    Cache\GarbageCollect,
    IPC\Message\FileAccessed,
    IPC\Message\FileDeleted,
    Name,
    Exception\RuntimeException,
};
use Innmind\IPC\Server;
use Innmind\CLI\{
    Command,
    Command\Arguments,
    Command\Options,
    Environment,
};
use Innmind\Stream\Readable;
use PHPUnit\Framework\TestCase;
use Eris\{
    Generator,
    TestTrait,
};

class CacheDaemonTest extends TestCase
{
    use TestTrait;

    public function testInterface()
    {
        $this->assertInstanceOf(
            Command::class,
            new CacheDaemon(
                $this->createMock(Storage::class),
                $this->createMock(Storage::class),
                $this->createMock(Server::class),
                $this->createMock(Strategy::class),
                $this->createMock(GarbageCollect::class)
            )
        );
    }

    public function testUsage()
    {
        $usage = <<<USAGE
storage-cache-daemon -d|--daemon

Will start a daemon to cache the frequently accessed files
USAGE;

        $this->assertSame(
            $usage,
            (string) new CacheDaemon(
                $this->createMock(Storage::class),
                $this->createMock(Storage::class),
                $this->createMock(Server::class),
                $this->createMock(Strategy::class),
                $this->createMock(GarbageCollect::class)
            )
        );
    }

    public function testAddFileToLocalStorageWhenTheStrategyTellsIt()
    {
        $this
            ->minimumEvaluationRatio(0.01)
            ->forAll(Generator\string())
            ->when(static function($string): bool {
                return (bool) preg_match('~^[a-zA-Z0-9\-]+$~', $string);
            })
            ->then(function($name) {
                $name = new Name($name);

                $command = new CacheDaemon(
                    $remote = $this->createMock(Storage::class),
                    $local = $this->createMock(Storage::class),
                    $listen = $this->createMock(Server::class),
                    $strategy = $this->createMock(Strategy::class),
                    $garbageCollect = $this->createMock(GarbageCollect::class)
                );
                $strategy
                    ->expects($this->once())
                    ->method('__invoke')
                    ->with($name)
                    ->willReturn(true);
                $garbageCollect
                    ->expects($this->once())
                    ->method('__invoke')
                    ->with($name);
                $remote
                    ->expects($this->once())
                    ->method('get')
                    ->with($name)
                    ->willReturn($content = $this->createMock(Readable::class));
                $local
                    ->expects($this->once())
                    ->method('add')
                    ->with($name, $content);
                $listen
                    ->expects($this->once())
                    ->method('__invoke')
                    ->with($this->callback(static function($listen) use ($name) {
                        return $listen(new FileAccessed($name)) === null;
                    }));

                $this->assertNull($command(
                    $this->createMock(Environment::class),
                    new Arguments,
                    new Options
                ));
            });
    }

    public function testContinueRunningTheServerWhenFailingToAddToTheLocalStorage()
    {
        $this
            ->minimumEvaluationRatio(0.01)
            ->forAll(Generator\string())
            ->when(static function($string): bool {
                return (bool) preg_match('~^[a-zA-Z0-9\-]+$~', $string);
            })
            ->then(function($name) {
                $name = new Name($name);

                $command = new CacheDaemon(
                    $remote = $this->createMock(Storage::class),
                    $local = $this->createMock(Storage::class),
                    $listen = $this->createMock(Server::class),
                    $strategy = $this->createMock(Strategy::class),
                    $garbageCollect = $this->createMock(GarbageCollect::class)
                );
                $strategy
                    ->expects($this->once())
                    ->method('__invoke')
                    ->with($name)
                    ->willReturn(true);
                $garbageCollect
                    ->expects($this->never())
                    ->method('__invoke');
                $remote
                    ->expects($this->once())
                    ->method('get')
                    ->with($name)
                    ->will($this->throwException(new RuntimeException));
                $local
                    ->expects($this->never())
                    ->method('add');
                $listen
                    ->expects($this->once())
                    ->method('__invoke')
                    ->with($this->callback(static function($listen) use ($name) {
                        return $listen(new FileAccessed($name)) === null;
                    }));

                $this->assertNull($command(
                    $this->createMock(Environment::class),
                    new Arguments,
                    new Options
                ));
            });
    }

    public function testDoesNotAddFileToLocalStorageWhenTheStrategySaySo()
    {
        $this
            ->minimumEvaluationRatio(0.01)
            ->forAll(Generator\string())
            ->when(static function($string): bool {
                return (bool) preg_match('~^[a-zA-Z0-9\-]+$~', $string);
            })
            ->then(function($name) {
                $name = new Name($name);

                $command = new CacheDaemon(
                    $remote = $this->createMock(Storage::class),
                    $local = $this->createMock(Storage::class),
                    $listen = $this->createMock(Server::class),
                    $strategy = $this->createMock(Strategy::class),
                    $garbageCollect = $this->createMock(GarbageCollect::class)
                );
                $strategy
                    ->expects($this->once())
                    ->method('__invoke')
                    ->with($name)
                    ->willReturn(false);
                $garbageCollect
                    ->expects($this->once())
                    ->method('__invoke')
                    ->with($name);
                $remote
                    ->expects($this->never())
                    ->method('get');
                $local
                    ->expects($this->never())
                    ->method('add');
                $listen
                    ->expects($this->once())
                    ->method('__invoke')
                    ->with($this->callback(static function($listen) use ($name) {
                        return $listen(new FileAccessed($name)) === null;
                    }));

                $this->assertNull($command(
                    $this->createMock(Environment::class),
                    new Arguments,
                    new Options
                ));
            });
    }

    public function testRemoveFileFromLocalStorage()
    {
        $this
            ->minimumEvaluationRatio(0.01)
            ->forAll(Generator\string())
            ->when(static function($string): bool {
                return (bool) preg_match('~^[a-zA-Z0-9\-]+$~', $string);
            })
            ->then(function($name) {
                $name = new Name($name);

                $command = new CacheDaemon(
                    $remote = $this->createMock(Storage::class),
                    $local = $this->createMock(Storage::class),
                    $listen = $this->createMock(Server::class),
                    $strategy = $this->createMock(Strategy::class),
                    $garbageCollect = $this->createMock(GarbageCollect::class)
                );
                $strategy
                    ->expects($this->never())
                    ->method('__invoke');
                $garbageCollect
                    ->expects($this->once())
                    ->method('__invoke')
                    ->with($name);
                $remote
                    ->expects($this->never())
                    ->method('delete');
                $local
                    ->expects($this->once())
                    ->method('delete')
                    ->with($name);
                $listen
                    ->expects($this->once())
                    ->method('__invoke')
                    ->with($this->callback(static function($listen) use ($name) {
                        return $listen(new FileDeleted($name)) === null;
                    }));

                $this->assertNull($command(
                    $this->createMock(Environment::class),
                    new Arguments,
                    new Options
                ));
            });
    }
}
