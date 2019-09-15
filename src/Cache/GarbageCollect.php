<?php
declare(strict_types = 1);

namespace Innmind\ConservationMeasure\Cache;

use Innmind\ConservationMeasure\Name;

interface GarbageCollect
{
    /**
     * @param Name $name The one triggering the garbage collection
     */
    public function __invoke(Name $name): void;
}
