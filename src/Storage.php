<?php
declare(strict_types = 1);

namespace Innmind\ConservationMeasure;

use Innmind\Stream\Readable;

interface Storage
{
    public function get(Name $name): Readable;
    public function add(Name $name, Readable $file): void;
    public function contains(Name $name): bool;
    public function delete(Name $name): void;
}
