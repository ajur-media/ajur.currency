<?php

use AJUR\Toolkit\Currency;

require_once '../vendor/autoload.php';

try {
    Currency::init([
        'format_method' =>  'numfmp'
    ]);
    Currency::selectCurrencySet(['USD', 'EUR']);

    Currency::storeFile('test.json');
} catch (Exception $e) {
    var_dump($e->getMessage());
}