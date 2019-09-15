<?php
declare(strict_types = 1);

namespace Innmind\ConservationMeasure\Storage;

use Innmind\ConservationMeasure\{
    Storage,
    Name,
    IPC\Message\FileAccessed,
    IPC\Message\FileDeleted,
};
use Innmind\IPC\{
    IPC,
    Process,
    Message,
    Exception\FailedToConnect,
    Exception\MessageNotSent,
};
use Innmind\Stream\Readable;

final class Cache implements Storage
{
    private $remote;
    private $local;
    private $ipc;
    private $daemon;

    public function __construct(
        Storage $remote,
        Storage $local,
        IPC $ipc,
        Process\Name $daemon
    ) {
        $this->remote = $remote;
        $this->local = $local;
        $this->ipc = $ipc;
        $this->daemon = $daemon;
    }

    public function get(Name $name): Readable
    {
        $message = new FileAccessed($name);

        if ($this->local->contains($name)) {
            $file = $this->local->get($name);
            $this->notify($name, $message);

            return $file;
        }

        $file = $this->remote->get($name);
        $this->notify($name, $message);

        return $file;
    }

    public function add(Name $name, Readable $file): void
    {
        $this->remote->add($name, $file);
    }

    public function contains(Name $name): bool
    {
        return $this->local->contains($name) || $this->remote->contains($name);
    }

    public function delete(Name $name): void
    {
        $this->remote->delete($name);
        $this->notify($name, new FileDeleted($name));
    }

    private function notify(Name $name, Message $message): void
    {
        if (!$this->ipc->exist($this->daemon)) {
            return;
        }

        try {
            $daemon = $this->ipc->get($this->daemon);
            $daemon->send($message);
            $daemon->close();
        } catch (FailedToConnect | MessageNotSent $e) {
            // pass
        }
    }
}
