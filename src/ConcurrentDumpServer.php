<?php

declare(strict_types=1);

namespace Serafim\OpenRay;

use Serafim\OpenRay\Exception\MessageDecodingException;
use Socket\Raw\Factory;
use Socket\Raw\Socket;
use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Component\VarDumper\Cloner\Stub;

final class ConcurrentDumpServer
{
    private readonly Socket $server;

    /**
     * @var \SplObjectStorage<Socket, mixed>
     */
    private readonly \SplObjectStorage $clients;

    /**
     * @var array<int, string>
     */
    private array $buffers = [];

    /**
     * @param non-empty-string $host
     */
    public function __construct(string $host)
    {
        $factory = new Factory();

        $this->server = $factory->createServer($host);
        $this->server->setBlocking(false);

        $this->clients = new \SplObjectStorage();
    }

    /**
     * @return iterable<int, array{string, string}>
     */
    public function listen(): iterable
    {
        if ($this->server->selectRead()) {
            $client = $this->server->accept();
            $client->setBlocking(false);

            $this->clients->attach($client);
        }

        foreach ($this->clients as $client) {
            if ($client->selectRead()) {
                $clientId = \spl_object_id($client);

                if (!isset($this->buffers[$clientId])) {
                    $this->buffers[$clientId] = '';
                }

                $this->buffers[$clientId] .= $client->read(4096);
            }
        }

        foreach ($this->buffers as $clientId => $buffer) {
            $hasMessages = false;

            while(($offset = \strpos($buffer, "\n")) !== false) {
                $hasMessages = true;

                $message = \substr($buffer, 0, $offset);

                $buffer = \substr($buffer, $offset + 1);

                yield $clientId => $this->decode($clientId, $message);
            }

            if ($hasMessages) {
                $this->buffers[$clientId] = $buffer;
            }
        }
    }

    private function decode(int $clientId, string $data): array
    {
        $payload = @\unserialize(\base64_decode($data), [
            'allowed_classes' => [
                Data::class,
                Stub::class,
            ],
        ]);

        if ($payload === false) {
            throw MessageDecodingException::becauseMessageIsInvalid($clientId, $data);
        }

        if (!\is_array($payload)
            || \count($payload) < 2
            || !$payload[0] instanceof Data
            || !\is_array($payload[1])
        ) {
            throw MessageDecodingException::becauseMessageIsInvalid($clientId, $data);
        }

        return $payload;
    }
}
