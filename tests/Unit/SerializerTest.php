<?php

namespace Nahid\PHPTask\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Nahid\PHPTask\IPC\Serializer;
use Exception;

class SerializerTest extends TestCase
{
    private $serializer;

    protected function setUp(): void
    {
        $this->serializer = new Serializer();
    }

    public function testSerializeString()
    {
        $data = "Hello World";
        $serialized = $this->serializer->serialize($data);
        $unserialized = $this->serializer->unserialize($serialized);

        $this->assertEquals($data, $unserialized);
    }

    public function testSerializeArray()
    {
        $data = ['foo' => 'bar', 123];
        $serialized = $this->serializer->serialize($data);
        $unserialized = $this->serializer->unserialize($serialized);

        $this->assertEquals($data, $unserialized);
    }

    public function testSerializeException()
    {
        $exception = new Exception("Test Error", 123);
        $serialized = $this->serializer->serialize($exception);
        $unserialized = $this->serializer->unserialize($serialized);

        $this->assertIsArray($unserialized);
        $this->assertTrue($unserialized['__is_error']);
        $this->assertEquals("Test Error", $unserialized['message']);
        $this->assertEquals(123, $unserialized['code']);
    }
}
