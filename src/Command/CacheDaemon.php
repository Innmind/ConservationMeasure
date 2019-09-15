<?php
declare(strict_types = 1);

namespace Innmind\ConservationMeasure\Command;

use Innmind\ConservationMeasure\{
    Storage,
    IPC\Message\FileAccessed,
    IPC\Message\FileDeleted,
    Cache\Strategy,
    Cache\GarbageCollect,
    Exception\LogicException,
    Exception\RuntimeException,
};
use Innmind\IPC\{
    Server,
    Message,
};
use Innmind\CLI\{
    Command,
    Command\Arguments,
    Command\Options,
    Environment,
};

final class CacheDaemon implements Command
{
    private $remote;
    private $local;
    private $listen;
    private $shouldCache;
    private $garbageCollect;
    private $name;

    public function __construct(
        Storage $remote,
        Storage $local,
        Server $listen,
        Strategy $shouldCache,
        GarbageCollect $garbageCollect,
        string $commandName = 'storage-cache-daemon'
    ) {
        $this->remote = $remote;
        $this->local = $local;
        $this->listen = $listen;
        $this->shouldCache = $shouldCache;
        $this->garbageCollect = $garbageCollect;
        $this->name = $commandName;
    }

    public function __invoke(Environment $env, Arguments $arguments, Options $options): void
    {
        ($this->listen)(function(Message $message): void {
            try {
                $name = FileAccessed::extractName($message);

                if (($this->shouldCache)($name)) {
                    $this->local->add(
                        $name,
                        $this->remote->get($name)
                    );
                }

                ($this->garbageCollect)($name);

                return;
            } catch (RuntimeException $e) {
                // do nothing in case the file couldn't be accessed
                return;
            } catch (LogicException $e) {
                // let attempt file removed
            }

            try {
                $name = FileDeleted::extractName($message);
                $this->local->delete($name);
                ($this->garbageCollect)($name);
            } catch (LogicException $e) {
                // nothing left to do
            }
        });
    }

    public function __toString(): string
    {
        return <<<USAGE
{$this->name} -d|--daemon

Will start a daemon to cache the frequently accessed files
USAGE;
    }
}
