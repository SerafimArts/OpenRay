<?php

declare(strict_types=1);

namespace Serafim\OpenRay\Exception;

class MessageDecodingException extends OpenRayException
{
    public static function becauseMessageIsInvalid(string|int $id, string $data, ?\Throwable $prev = null): self
    {
        $message = \sprintf('Unable to decode a message from %s client: %s', $id, $data);

        return new self($message, previous: $prev);
    }
}

