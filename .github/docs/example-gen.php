<?php

require __DIR__.'/../../vendor/autoload.php';

use FriendsOfPhp\Number\Number;

$examples = document([
    Number::format(1234567.89),
    Number::spell(1234),
    Number::ordinal(42),
    Number::percentage(0.75),
    Number::currency(1234.56, 'EUR'),
    Number::fileSize(1024),
    Number::forHumans(1234567.89),
]);

echo $examples;

function document($data): string
{
    $contents = explode("\n", file_get_contents(__FILE__));

    $examples = [];

    $functionCallLine = debug_backtrace()[0]['line'] - count($data);

    foreach ($data as $index => $result) {
        $source = trim($contents[$functionCallLine + $index], "\t\n\r\0\x0B, ");

        $examples[] = "echo $source; // $result";
    }

    return implode("\n", $examples);
}

function dd($data)
{
    var_dump($data);
    die;
}
