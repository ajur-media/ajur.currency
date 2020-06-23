<?php

namespace AJUR\Toolkit;

use Psr\Log\LoggerInterface;

interface CurrencyInterface
{
    const CBR_URL = 'http://www.cbr.ru/scripts/XML_daily.asp';

    const credentials = [
        // internal codes
        'currencies'    =>  [ 'R01235', 'R01239' ], // usd, euro
        'API_key'       =>  '',
        'URL'           =>  'http://www.cbr.ru/scripts/XML_daily.asp'
    ];

    /**
     * Фильтрует набор данных из ЦБР на предмет валют по набору кодов
     *
     * @param array $codes
     * @return bool
     */
    public static function selectCurrencySet(array $codes);

    /**
     * Возвращает информацию о загруженных валютах
     *
     * @return array
     */
    public static function getPrices();

    /**
     * Возвращает информацию о загруженных валютах в компактном виде
     *
     * @return array
     */
    public static function getPricesCompact();

    /**
     * Сохраняет данные в файл
     *
     * @param string $filename
     * @return array
     */
    public static function storeFile(string $filename);

    /**
     * Загружает данные из файла
     *
     * @param string $filename
     * @param LoggerInterface|null $logger
     * @return array
     */
    public static function loadFile(string $filename, LoggerInterface $logger = null):array;
}