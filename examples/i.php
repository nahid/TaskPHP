<?php


require __DIR__ . '/../vendor/autoload.php';

use Nahid\TaskPHP\IPC\Serializer;

$serializer = new Serializer();

function sum($a, $b)
{
    return $a + $b;
}
;

$fn = function () {
    echo sum(5, 4);
};

$data = $serializer->serialize($fn);

echo $data;