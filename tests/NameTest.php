<?php
declare(strict_types = 1);

namespace Tests\Innmind\ConservationMeasure;

use Innmind\ConservationMeasure\{
    Name,
    Exception\DomainException,
};
use PHPUnit\Framework\TestCase;
use Eris\{
    Generator,
    TestTrait,
};

class NameTest extends TestCase
{
    use TestTrait;

    public function testThrowWhenInvalidFormat()
    {
        $this
            ->forAll(Generator\string())
            ->when(static function($string): bool {
                return (bool) !preg_match('~^[a-zA-Z0-9\-]+$~', $string);
            })
            ->then(function($string) {
                $this->expectException(DomainException::class);
                $this->expectExceptionMessage($string);

                new Name($string);
            });
    }

    public function testStringCast()
    {
        $this
            ->minimumEvaluationRatio(0.01)
            ->forAll(Generator\string())
            ->when(static function($string): bool {
                return (bool) preg_match('~^[a-zA-Z0-9\-]+$~', $string);
            })
            ->then(function($string) {
                $this->assertSame($string, (string) new Name($string));
            });
    }
}
