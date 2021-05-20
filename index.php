<?php

require __DIR__ . '/vendor/autoload.php';

interface Marker { }
class HasMarker implements Marker { }

use Pimple\Container;
$c = new Container;
$c[HasMarker::class] = function (Container $c) {
    return new HasMarker;
};

$getImplementations = fn(string $interface): callable => fn(Container $c): array => array_reduce(
    $c->keys(),
    fn(array $implementations, string $key): array => $c[$key] instanceof $interface
        ? array_merge($implementations, [$c[$key]])
        : $implementations,
    []
);

$c['a'] = $getImplementations(Marker::class);

var_dump($c['a']);
