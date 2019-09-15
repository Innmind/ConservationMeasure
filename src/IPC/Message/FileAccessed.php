<?php
declare(strict_types = 1);

namespace Innmind\ConservationMeasure\IPC\Message;

use Innmind\ConservationMeasure\{
    Name,
    Exception\LogicException,
};
use Innmind\IPC\Message;
use Innmind\Filesystem\MediaType;
use Innmind\Immutable\Str;

final class FileAccessed implements Message
{
    private $content;

    public function __construct(Name $name)
    {
        $this->content = Str::of('file_accessed:'.$name);
    }

    /**
     * @throws LogicException when not the expected message type
     */
    public static function extractName(Message $message): Name
    {
        if ((string) $message->content()->take(14) !== 'file_accessed:') {
            throw new LogicException('Not a file accessed message');
        }

        return new Name((string) $message->content()->drop(14));
    }

    public function mediaType(): MediaType
    {
        return new MediaType\MediaType('text', 'plain');
    }

    public function content(): Str
    {
        return $this->content;
    }

    public function equals(Message $message): bool
    {
        return (string) $this->mediaType() === (string) $message->mediaType() &&
            $this->content->equals($message->content());
    }
}
