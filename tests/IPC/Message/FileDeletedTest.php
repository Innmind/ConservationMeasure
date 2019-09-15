<?php
declare(strict_types = 1);

namespace Tests\Innmind\ConservationMeasure\IPC\Message;

use Innmind\ConservationMeasure\{
    IPC\Message\FileDeleted,
    Name,
    Exception\LogicException,
};
use Innmind\IPC\Message;
use PHPUnit\Framework\TestCase;
use Eris\{
    Generator,
    TestTrait,
};

class FileDeletedTest extends TestCase
{
    use TestTrait;

    public function testInterface()
    {
        $this->assertInstanceOf(
            Message::class,
            new FileDeleted(new Name('foo'))
        );
        $this
            ->minimumEvaluationRatio(0.01)
            ->forAll(Generator\string())
            ->when(static function($string): bool {
                return (bool) preg_match('~^[a-zA-Z0-9\-]+$~', $string);
            })
            ->then(function($name) {
                $message = new FileDeleted(new Name($name));

                $this->assertSame('text/plain', (string) $message->mediaType());
                $this->assertSame('file_deleted:'.$name, (string) $message->content());
                $this->assertTrue($message->equals(new FileDeleted(new Name($name))));
                $this->assertFalse($message->equals(new FileDeleted(new Name('f'.$name))));
            });
    }

    public function testExtractName()
    {
        $this
            ->minimumEvaluationRatio(0.01)
            ->forAll(Generator\string())
            ->when(static function($string): bool {
                return (bool) preg_match('~^[a-zA-Z0-9\-]+$~', $string);
            })
            ->then(function($expected) {
                $message = new FileDeleted(new Name($expected));

                $name = FileDeleted::extractName($message);

                $this->assertInstanceOf(Name::class, $name);
                $this->assertSame($expected, (string) $name);
            });
    }

    public function testThrowWhenExtractingNameForADifferentMessage()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Not a file deleted message');

        FileDeleted::extractName($this->createMock(Message::class));
    }
}
