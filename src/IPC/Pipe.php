<?php

namespace Nahid\TaskPHP\IPC;

use Nahid\TaskPHP\Exceptions\TaskException;
use RuntimeException;

class Pipe
{
    /** @var resource */
    private $reader;

    /** @var resource */
    private $writer;

    public function __construct()
    {
        $domain = DIRECTORY_SEPARATOR === '\\' ? STREAM_PF_INET : STREAM_PF_UNIX;
        $sockets = stream_socket_pair($domain, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

        if ($sockets === false) {
            throw new RuntimeException("Failed to create socket pair");
        }

        $this->reader = $sockets[0];
        $this->writer = $sockets[1];
    }

    public function closeReader(): void
    {
        if (is_resource($this->reader)) {
            fclose($this->reader);
        }
    }

    public function closeWriter(): void
    {
        if (is_resource($this->writer)) {
            fclose($this->writer);
        }
    }

    public function write(string $data): void
    {
        if (!is_resource($this->writer)) {
            throw new RuntimeException("Pipe writer is closed");
        }

        // Write length prefix first to handle fragmentation/boundaries if needed,
        // but for simple streams we might just read all.
        // Let's rely on stream_get_contents for reading everything until EOF (connection close).
        // Since we close writer after writing in child, EOF will be sent.

        $len = strlen($data);
        $written = 0;
        while ($written < $len) {
            $result = fwrite($this->writer, substr($data, $written));
            if ($result === false) {
                throw new RuntimeException("Failed to write to pipe");
            }
            $written += $result;
        }
    }

    /**
     * Read all data from the pipe.
     *
     * @return string
     */
    public function read(): string
    {
        if (!is_resource($this->reader)) {
            throw new RuntimeException("Pipe reader is closed");
        }

        $content = stream_get_contents($this->reader);
        if ($content === false) {
            throw new RuntimeException("Failed to read from pipe");
        }

        return $content;
    }

    public function getReader()
    {
        return $this->reader;
    }

    public function close(): void
    {
        $this->closeReader();
        $this->closeWriter();
    }
}
