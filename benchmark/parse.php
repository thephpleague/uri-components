<?php

require __DIR__.'/../src/EncodingInterface.php';
require __DIR__.'/../src/ComponentInterface.php';
require __DIR__.'/../src/Exception.php';
require __DIR__.'/../src/QueryParser.php';
require __DIR__.'/../src/Components/Query.php';

$query = 'module=home&action=show&page=3';
$parser = new League\Uri\QueryParser();
for ($i = 0; $i < 100000; ++$i) {
    $parser->parse($query);
}