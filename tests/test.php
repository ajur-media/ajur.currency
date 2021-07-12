<?php

use AJUR\Toolkit\Currency;

require_once '../vendor/autoload.php';

try {
    Currency::init();
    Currency::selectCurrencySet();

    Currency::storeFile('test.json');
} catch (Exception $e) {
    var_dump($e->getMessage());
}