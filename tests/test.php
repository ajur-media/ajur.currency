<?php

use AJUR\Toolkit\Currency;

require_once '../vendor/autoload.php';

try {
    Currency::selectCurrencySet([]);

    Currency::storeFile('data.json');
} catch (Exception $e) {
    var_dump($e->getMessage());
}