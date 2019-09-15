<?php
declare(strict_types = 1);

namespace Innmind\ConservationMeasure\Cache;

use Innmind\ConservationMeasure\Name;

interface Strategy
{
    /**
     * Whether or not the file should be cached
     */
    public function __invoke(Name $name): bool;
}
