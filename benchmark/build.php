<?php

require __DIR__.'/../src/EncodingInterface.php';
require __DIR__.'/../src/Exception.php';
require __DIR__.'/../src/QueryBuilder.php';
require __DIR__.'/../src/Components/Query.php';

$pairs = ['module' => ['home'], 'action' => ['show'], 'page' => [3]];
$builder = new League\Uri\QueryBuilder();
for ($i = 0; $i < 100000; ++$i) {
    $builder->build($pairs);
}