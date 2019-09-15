<?php
declare(strict_types = 1);

namespace Innmind\ConservationMeasure\Storage;

use Innmind\ConservationMeasure\{
    Storage,
    Name,
    Exception\RuntimeException,
};
use Innmind\Stream\Readable;
use Innmind\Filesystem\{
    Adapter,
    File\File,
    Exception\FileNotFound,
};

final class OnTopOfFilesystem implements Storage
{
    private $filesystem;

    public function __construct(Adapter $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    public function get(Name $name): Readable
    {
        try {
            return $this->filesystem->get((string) $name)->content();
        } catch (FileNotFound $e) {
            throw new RuntimeException('', 0, $e);
        }
    }

    public function add(Name $name, Readable $file): void
    {
        $this->filesystem->add(new File(
            (string) $name,
            $file
        ));
    }

    public function contains(Name $name): bool
    {
        return $this->filesystem->has((string) $name);
    }

    public function delete(Name $name): void
    {
        try {
            $this->filesystem->remove((string) $name);
        } catch (FileNotFound $e) {
            // pass
        }
    }
}
