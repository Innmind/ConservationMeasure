<?php
declare(strict_types = 1);

namespace Tests\Innmind\ConservationMeasure\Storage;

use Innmind\ConservationMeasure\{
    Storage\OnTopOfFilesystem,
    Storage,
    Name,
    Exception\RuntimeException,
};
use Innmind\Filesystem\{
    Adapter,
    File\File,
    Exception\FileNotFound,
};
use Innmind\Stream\Readable;
use PHPUnit\Framework\TestCase;
use Eris\{
    Generator,
    TestTrait,
};

class OnTopOfFilesystemTest extends TestCase
{
    use TestTrait;

    public function testInterface()
    {
        $this->assertInstanceOf(
            Storage::class,
            new OnTopOfFilesystem($this->createMock(Adapter::class))
        );
    }

    public function testGet()
    {
        $this
            ->minimumEvaluationRatio(0.01)
            ->forAll(Generator\string())
            ->when(static function($string): bool {
                return (bool) preg_match('~^[a-zA-Z0-9\-]+$~', $string);
            })
            ->then(function($name) {
                $storage = new OnTopOfFilesystem(
                    $filesystem = $this->createMock(Adapter::class)
                );
                $filesystem
                    ->expects($this->once())
                    ->method('get')
                    ->with($name)
                    ->willReturn(new File(
                        $name,
                        $content = $this->createMock(Readable::class)
                    ));

                $this->assertSame($content, $storage->get(new Name($name)));
            });
    }

    public function testThrowWhenGettingUnknownFile()
    {
        $this
            ->minimumEvaluationRatio(0.01)
            ->forAll(Generator\string())
            ->when(static function($string): bool {
                return (bool) preg_match('~^[a-zA-Z0-9\-]+$~', $string);
            })
            ->then(function($name) {
                $storage = new OnTopOfFilesystem(
                    $filesystem = $this->createMock(Adapter::class)
                );
                $filesystem
                    ->expects($this->once())
                    ->method('get')
                    ->with($name)
                    ->will($this->throwException(new FileNotFound($name)));

                $this->expectException(RuntimeException::class);

                $storage->get(new Name($name));
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
                $storage = new OnTopOfFilesystem(
                    $filesystem = $this->createMock(Adapter::class)
                );
                $content = $this->createMock(Readable::class);
                $filesystem
                    ->expects($this->once())
                    ->method('add')
                    ->with(new File($name, $content));

                $this->assertNull($storage->add(new Name($name), $content));
            });
    }

    public function testContains()
    {
        $this
            ->minimumEvaluationRatio(0.01)
            ->forAll(Generator\string(), Generator\elements(true, false))
            ->when(static function($string): bool {
                return (bool) preg_match('~^[a-zA-Z0-9\-]+$~', $string);
            })
            ->then(function($name, $exist) {
                $storage = new OnTopOfFilesystem(
                    $filesystem = $this->createMock(Adapter::class)
                );
                $content = $this->createMock(Readable::class);
                $filesystem
                    ->expects($this->once())
                    ->method('has')
                    ->with($name)
                    ->willReturn($exist);

                $this->assertSame($exist, $storage->contains(new Name($name)));
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
                $storage = new OnTopOfFilesystem(
                    $filesystem = $this->createMock(Adapter::class)
                );
                $content = $this->createMock(Readable::class);
                $filesystem
                    ->expects($this->once())
                    ->method('remove')
                    ->with($name);

                $this->assertNull($storage->delete(new Name($name)));
            });
    }

    public function testDoesNotThrowWhenDeletingUnnownFile()
    {
        $this
            ->minimumEvaluationRatio(0.01)
            ->forAll(Generator\string())
            ->when(static function($string): bool {
                return (bool) preg_match('~^[a-zA-Z0-9\-]+$~', $string);
            })
            ->then(function($name) {
                $storage = new OnTopOfFilesystem(
                    $filesystem = $this->createMock(Adapter::class)
                );
                $content = $this->createMock(Readable::class);
                $filesystem
                    ->expects($this->once())
                    ->method('remove')
                    ->with($name)
                    ->will($this->throwException(new FileNotFound($name)));

                $this->assertNull($storage->delete(new Name($name)));
            });
    }
}
