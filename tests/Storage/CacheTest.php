<?php
declare(strict_types = 1);

namespace Tests\Innmind\ConservationMeasure\Storage;

use Innmind\ConservationMeasure\{
    Storage\Cache,
    Storage,
    Name,
    IPC\Message\FileAccessed,
    IPC\Message\FileDeleted,
};
use Innmind\IPC\{
    IPC,
    Process,
    Exception\FailedToConnect,
    Exception\MessageNotSent,
};
use Innmind\Stream\Readable;
use PHPUnit\Framework\TestCase;
use Eris\{
    Generator,
    TestTrait,
};

class CacheTest extends TestCase
{
    use TestTrait;

    public function testInterface()
    {
        $this->assertInstanceOf(
            Storage::class,
            new Cache(
                $this->createMock(Storage::class),
                $this->createMock(Storage::class),
                $this->createMock(IPC::class),
                new Process\Name('foo')
            )
        );
    }

    public function testGetFromLocalStorageWhenAvailable()
    {
        $this
            ->minimumEvaluationRatio(0.01)
            ->forAll(Generator\string())
            ->when(static function($string): bool {
                return (bool) preg_match('~^[a-zA-Z0-9\-]+$~', $string);
            })
            ->then(function($name) {
                $storage = new Cache(
                    $remote = $this->createMock(Storage::class),
                    $local = $this->createMock(Storage::class),
                    $ipc = $this->createMock(IPC::class),
                    $daemon = new Process\Name('foo')
                );
                $remote
                    ->expects($this->never())
                    ->method('get');
                $local
                    ->expects($this->once())
                    ->method('contains')
                    ->with(new Name($name))
                    ->willReturn(true);
                $local
                    ->expects($this->once())
                    ->method('get')
                    ->with(new Name($name))
                    ->willReturn($content = $this->createMock(Readable::class));
                $ipc
                    ->expects($this->once())
                    ->method('exist')
                    ->with($daemon)
                    ->willReturn(true);
                $ipc
                    ->expects($this->once())
                    ->method('get')
                    ->with($daemon)
                    ->willReturn($process = $this->createMock(Process::class));
                $process
                    ->expects($this->at(0))
                    ->method('send')
                    ->with(new FileAccessed(new Name($name)));
                $process
                    ->expects($this->at(1))
                    ->method('close');

                $this->assertSame($content, $storage->get(new Name($name)));
            });
    }

    public function testGetFromRemoteStorageWhenNotAvailableLocally()
    {
        $this
            ->minimumEvaluationRatio(0.01)
            ->forAll(Generator\string())
            ->when(static function($string): bool {
                return (bool) preg_match('~^[a-zA-Z0-9\-]+$~', $string);
            })
            ->then(function($name) {
                $storage = new Cache(
                    $remote = $this->createMock(Storage::class),
                    $local = $this->createMock(Storage::class),
                    $ipc = $this->createMock(IPC::class),
                    $daemon = new Process\Name('foo')
                );
                $remote
                    ->expects($this->once())
                    ->method('get')
                    ->with(new Name($name))
                    ->willReturn($content = $this->createMock(Readable::class));
                $local
                    ->expects($this->once())
                    ->method('contains')
                    ->with(new Name($name))
                    ->willReturn(false);
                $local
                    ->expects($this->never())
                    ->method('get');
                $ipc
                    ->expects($this->once())
                    ->method('exist')
                    ->with($daemon)
                    ->willReturn(true);
                $ipc
                    ->expects($this->once())
                    ->method('get')
                    ->with($daemon)
                    ->willReturn($process = $this->createMock(Process::class));
                $process
                    ->expects($this->at(0))
                    ->method('send')
                    ->with(new FileAccessed(new Name($name)));
                $process
                    ->expects($this->at(1))
                    ->method('close');

                $this->assertSame($content, $storage->get(new Name($name)));
            });
    }

    public function testDoesNotNotifyDaemonThatAFileHasBeenAccessedIfTheDaemonIsNotStarted()
    {
        $this
            ->minimumEvaluationRatio(0.01)
            ->forAll(Generator\string())
            ->when(static function($string): bool {
                return (bool) preg_match('~^[a-zA-Z0-9\-]+$~', $string);
            })
            ->then(function($name) {
                $storage = new Cache(
                    $remote = $this->createMock(Storage::class),
                    $local = $this->createMock(Storage::class),
                    $ipc = $this->createMock(IPC::class),
                    $daemon = new Process\Name('foo')
                );
                $remote
                    ->expects($this->once())
                    ->method('get')
                    ->with(new Name($name))
                    ->willReturn($content = $this->createMock(Readable::class));
                $local
                    ->expects($this->once())
                    ->method('contains')
                    ->with(new Name($name))
                    ->willReturn(false);
                $local
                    ->expects($this->never())
                    ->method('get');
                $ipc
                    ->expects($this->once())
                    ->method('exist')
                    ->with($daemon)
                    ->willReturn(false);
                $ipc
                    ->expects($this->never())
                    ->method('get');

                $this->assertSame($content, $storage->get(new Name($name)));
            });
    }

    public function testDoesNotThrowWhenTryingToAccessTheDaemon()
    {
        $this
            ->minimumEvaluationRatio(0.01)
            ->forAll(Generator\string())
            ->when(static function($string): bool {
                return (bool) preg_match('~^[a-zA-Z0-9\-]+$~', $string);
            })
            ->then(function($name) {
                $storage = new Cache(
                    $remote = $this->createMock(Storage::class),
                    $local = $this->createMock(Storage::class),
                    $ipc = $this->createMock(IPC::class),
                    $daemon = new Process\Name('foo')
                );
                $remote
                    ->expects($this->once())
                    ->method('get')
                    ->with(new Name($name))
                    ->willReturn($content = $this->createMock(Readable::class));
                $local
                    ->expects($this->once())
                    ->method('contains')
                    ->with(new Name($name))
                    ->willReturn(false);
                $local
                    ->expects($this->never())
                    ->method('get');
                $ipc
                    ->expects($this->once())
                    ->method('exist')
                    ->with($daemon)
                    ->willReturn(true);
                $ipc
                    ->expects($this->once())
                    ->method('get')
                    ->with($daemon)
                    ->will($this->throwException(new FailedToConnect));

                $this->assertSame($content, $storage->get(new Name($name)));
            });
    }

    public function testDoesNotThrowWhenTheDaemonNotificationFailed()
    {
        $this
            ->minimumEvaluationRatio(0.01)
            ->forAll(Generator\string())
            ->when(static function($string): bool {
                return (bool) preg_match('~^[a-zA-Z0-9\-]+$~', $string);
            })
            ->then(function($name) {
                $storage = new Cache(
                    $remote = $this->createMock(Storage::class),
                    $local = $this->createMock(Storage::class),
                    $ipc = $this->createMock(IPC::class),
                    $daemon = new Process\Name('foo')
                );
                $remote
                    ->expects($this->once())
                    ->method('get')
                    ->with(new Name($name))
                    ->willReturn($content = $this->createMock(Readable::class));
                $local
                    ->expects($this->once())
                    ->method('contains')
                    ->with(new Name($name))
                    ->willReturn(false);
                $local
                    ->expects($this->never())
                    ->method('get');
                $ipc
                    ->expects($this->once())
                    ->method('exist')
                    ->with($daemon)
                    ->willReturn(true);
                $ipc
                    ->expects($this->once())
                    ->method('get')
                    ->with($daemon)
                    ->will($this->throwException(new MessageNotSent));

                $this->assertSame($content, $storage->get(new Name($name)));
            });
    }

    public function testAdd()
    {
        $this
            ->minimumEvaluationRatio(0.01)
            ->forAll(Generator\string())
            ->when(static function($string): bool {
                return (bool) preg_match('~^[a-zA-Z0-9\-]+$~', $string);
            })
            ->then(function($name) {
                $storage = new Cache(
                    $remote = $this->createMock(Storage::class),
                    $local = $this->createMock(Storage::class),
                    $ipc = $this->createMock(IPC::class),
                    $daemon = new Process\Name('foo')
                );
                $remote
                    ->expects($this->once())
                    ->method('add')
                    ->with(new Name($name));
                $local
                    ->expects($this->never())
                    ->method('add');
                $ipc
                    ->expects($this->never())
                    ->method('exist');
                $ipc
                    ->expects($this->never())
                    ->method('get');

                $this->assertNull($storage->add(
                    new Name($name),
                    $this->createMock(Readable::class)
                ));
            });
    }

    public function testConsiderTheFileExistIfPresentLocally()
    {
        $this
            ->minimumEvaluationRatio(0.01)
            ->forAll(Generator\string())
            ->when(static function($string): bool {
                return (bool) preg_match('~^[a-zA-Z0-9\-]+$~', $string);
            })
            ->then(function($name) {
                $storage = new Cache(
                    $remote = $this->createMock(Storage::class),
                    $local = $this->createMock(Storage::class),
                    $ipc = $this->createMock(IPC::class),
                    $daemon = new Process\Name('foo')
                );
                $remote
                    ->expects($this->never())
                    ->method('contains');
                $local
                    ->expects($this->once())
                    ->method('contains')
                    ->with(new Name($name))
                    ->willReturn(true);
                $ipc
                    ->expects($this->never())
                    ->method('exist');
                $ipc
                    ->expects($this->never())
                    ->method('get');

                $this->assertTrue($storage->contains(new Name($name)));
            });
    }

    public function testCheckRemotelyIfTheFileExistIfNotPresentLocally()
    {
        $this
            ->minimumEvaluationRatio(0.01)
            ->forAll(Generator\string())
            ->when(static function($string): bool {
                return (bool) preg_match('~^[a-zA-Z0-9\-]+$~', $string);
            })
            ->then(function($name) {
                $storage = new Cache(
                    $remote = $this->createMock(Storage::class),
                    $local = $this->createMock(Storage::class),
                    $ipc = $this->createMock(IPC::class),
                    $daemon = new Process\Name('foo')
                );
                $remote
                    ->expects($this->once())
                    ->method('contains')
                    ->with(new Name($name))
                    ->willReturn(true);
                $local
                    ->expects($this->once())
                    ->method('contains')
                    ->with(new Name($name))
                    ->willReturn(false);
                $ipc
                    ->expects($this->never())
                    ->method('exist');
                $ipc
                    ->expects($this->never())
                    ->method('get');

                $this->assertTrue($storage->contains(new Name($name)));
            });
    }

    public function testConsiderFileInexistantIfNotPresentLocallyNorRemotely()
    {
        $this
            ->minimumEvaluationRatio(0.01)
            ->forAll(Generator\string())
            ->when(static function($string): bool {
                return (bool) preg_match('~^[a-zA-Z0-9\-]+$~', $string);
            })
            ->then(function($name) {
                $storage = new Cache(
                    $remote = $this->createMock(Storage::class),
                    $local = $this->createMock(Storage::class),
                    $ipc = $this->createMock(IPC::class),
                    $daemon = new Process\Name('foo')
                );
                $remote
                    ->expects($this->once())
                    ->method('contains')
                    ->with(new Name($name))
                    ->willReturn(false);
                $local
                    ->expects($this->once())
                    ->method('contains')
                    ->with(new Name($name))
                    ->willReturn(false);
                $ipc
                    ->expects($this->never())
                    ->method('exist');
                $ipc
                    ->expects($this->never())
                    ->method('get');

                $this->assertFalse($storage->contains(new Name($name)));
            });
    }

    public function testDelete()
    {
        $this
            ->minimumEvaluationRatio(0.01)
            ->forAll(Generator\string())
            ->when(static function($string): bool {
                return (bool) preg_match('~^[a-zA-Z0-9\-]+$~', $string);
            })
            ->then(function($name) {
                $storage = new Cache(
                    $remote = $this->createMock(Storage::class),
                    $local = $this->createMock(Storage::class),
                    $ipc = $this->createMock(IPC::class),
                    $daemon = new Process\Name('foo')
                );
                $remote
                    ->expects($this->once())
                    ->method('delete')
                    ->with(new Name($name));
                $local
                    ->expects($this->never())
                    ->method('delete');
                $ipc
                    ->expects($this->once())
                    ->method('exist')
                    ->with($daemon)
                    ->willReturn(true);
                $ipc
                    ->expects($this->once())
                    ->method('get')
                    ->with($daemon)
                    ->willReturn($process = $this->createMock(Process::class));
                $process
                    ->expects($this->at(0))
                    ->method('send')
                    ->with(new FileDeleted(new Name($name)));
                $process
                    ->expects($this->at(1))
                    ->method('close');

                $this->assertNull($storage->delete(new Name($name)));
            });
    }
}
