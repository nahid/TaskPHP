<?php

namespace Nahid\PHPTask\IPC;

use Exception;
use Throwable;
use Nahid\PHPTask\Exceptions\TaskException;

class Serializer
{
    /**
     * Serialize payload.
     *
     * @param mixed $data
     * @return string
     */
    public function serialize($data): string
    {
        if ($data instanceof Throwable) {
            return serialize([
                '__is_error' => true,
                'message' => $data->getMessage(),
                'code' => $data->getCode(),
                'file' => $data->getFile(),
                'line' => $data->getLine(),
                'trace' => $data->getTraceAsString(),
                'class' => get_class($data),
            ]);
        }

        return serialize($data);
    }

    /**
     * Unserialize payload.
     *
     * @param string $data
     * @return mixed
     */
    public function unserialize(string $data)
    {
        $unserialized = unserialize($data);

        if (is_array($unserialized) && isset($unserialized['__is_error'])) {
            // We return the raw error array to the process manager, 
            // which will decide how to reconstruct the exception or handle it.
            // But to adhere to the plan of "wrapping exceptions into arrays", 
            // we effectively just returned it.
            return $unserialized;
        }

        return $unserialized;
    }
}
