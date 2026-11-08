<?php

namespace Ryra\Core\Messenger\Serialization;

class JsonSerializer implements SerializerInterface
{
    public function decode(string $message): mixed
    {
        $data = json_decode($message, true);
        $class = $data['class'];
        $properties = $data['properties'];
        $object = new $class();
        foreach ($properties as $key => $value) {
            $object->$key = $value;
        }
        return $object;
    }

    public function encode(mixed $message): string
    {
        return json_encode([
            'class' => get_class($message),
            'properties' => get_object_vars($message),
        ]);
    }
}