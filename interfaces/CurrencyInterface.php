<?php

namespace AJUR\Toolkit;

use Psr\Log\LoggerInterface;

interface CurrencyInterface
{
    /**
     *
     */
    const CBR_URL = 'http://www.cbr.ru/scripts/XML_daily.asp';

    const credentials = [
        // internal codes
        'currencies'    =>  [ 'R01235', 'R01239' ], // usd, euro
        'API_key'       =>  '',
        'URL'           =>  'http://www.cbr.ru/scripts/XML_daily.asp'
    ];

    /**
     * Инициализирует класс.
     * Необязательное действие. Основные значения заданы по умолчанию.
     *
     * @param array $options
     * @param LoggerInterface|null $logger
     * @return mixed
     */
    public static function init(array $options = [], LoggerInterface $logger = null);

    /**
     * Фильтрует набор данных из ЦБР на предмет валют по набору кодов
     *
     * @param array $codes
     * @return bool
     */
    public static function selectCurrencySet(array $codes):bool ;

    /**
     * Возвращает информацию о загруженных валютах
     *
     * @return array
     */
    public static function getPrices():array ;

    /**
     * Возвращает информацию о загруженных валютах в компактном виде
     *
     * @return array
     */
    public static function getPricesCompact():array;

    /**
     * Сохраняет данные в файл
     *
     * @param string $filename
     * @return mixed
     */
    public static function storeFile(string $filename) ;

    /**
     * Загружает данные из файла
     *
     * @param string $filename
     * @return array
     */
    public static function loadFile(string $filename):array;
}