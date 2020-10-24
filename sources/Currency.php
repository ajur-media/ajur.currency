<?php

namespace AJUR\Toolkit;

use Curl\Curl;
use DateTime;
use Exception;
use NumberFormatter;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Currency implements CurrencyInterface
{
    /**
     * @var array [
     * - locale - локаль, по умолчанию ru_RU
     * - max_currency_string_length - максимальная длина строки с записью валюты (5)
     * ]
     */
    private static $options = [
        'out_format'                    =>  "%01.2f",
    ];

    /**
     * @var array Список валют, общий вид
     */
    private static $cbr_prices = [];

    /**
     * @var array Список валют, компактный вид
     */
    private static $cbr_prices_compact = [];

    /**
     * @var array Отладочный набор данных
     */
    private static $cbr_prices_full;

    /**
     * @var array Полный набор данных
     */
    public static $cbr_response_raw_data;
    /**
     * @var NullLogger|LoggerInterface|null
     */
    private static $logger;
    
    /**
     * @param array $options
     * @param LoggerInterface|null $logger
     */
    public static function init($options = [], LoggerInterface $logger = null)
    {
        self::$options['locale']
            = array_key_exists('locale', $options)
            ? $options['locale']
            : 'ru_RU';
        setlocale(LC_MONETARY, self::$options['locale']);

        self::$options['out_format']
            = array_key_exists('out_format', $options)
            ? $options['out_format']
            : "%01.2f";
    
        self::$logger
            = $logger instanceof LoggerInterface
            ? $logger
            : new NullLogger();
    }

    /**
     * Фильтрует набор данных из ЦБР на предмет валют по набору кодов
     *
     * @param array $codes
     * @param null $fetch_date
     * @return bool
     * @throws Exception
     */
    public static function selectCurrencySet(array $codes, $fetch_date = null)
    {
        $cbr_prices = [];
        $cbr_prices_compact = [];

        $daily = self::loadCurrencyDataset($fetch_date);

        if (!$daily || !array_key_exists('Valute', $daily)) {
            // self::$logger->error("[ERROR] CBR API returns empty data", [ $daily ]);
            throw new Exception("[ERROR] CBR API returns empty data");
        }

        $cbr_prices_full = array_filter($daily['Valute'], function ($price) use (&$cbr_prices, &$cbr_prices_compact, $codes) {
            if (empty($codes) || in_array($price['CharCode'], $codes)) {
                $_code = $price['CharCode'];
                $cbr_prices[ $_code ] = [
                    'Code'      =>  $price['CharCode'],
                    'Name'      =>  $price['Name'],
                    'Value'     =>  self::formatCurrencyValue($price['Value']),
                    'Nominal'   =>  $price['Nominal'],
                    'PureValue' =>  $price['Value']
                ];

                $cbr_prices_compact[ $_code ] = self::formatCurrencyValue($price['Value']);
                return true;
            }
            return false;
        });

        self::$cbr_prices = $cbr_prices;
        self::$cbr_prices_full = $cbr_prices_full;
        self::$cbr_prices_compact = $cbr_prices_compact;
        return true;
    }

    /**
     * Возвращает информацию о загруженных валютах
     *
     * @return mixed
     */
    public static function getPrices()
    {
        return self::$cbr_prices;
    }

    /**
     * Возвращает информацию о загруженных валютах в компактном виде
     *
     * @return mixed
     */
    public static function getPricesCompact()
    {
        return self::$cbr_prices_compact;
    }

    /**
     * Сохраняет данные в файл
     *
     * @param string $filename
     * @return mixed
     */
    public static function storeFile(string $filename)
    {
        $asset = [
            'update_ts'     =>  time(),
            'update_time'   =>  (new DateTime())->format('Y-m-d H-i-s'),
            'summary'       =>  self::getPricesCompact(),
            'data'          =>  self::getPrices()
        ];
        return file_put_contents($filename, json_encode($asset, JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    /**
     * Загружает данные из файла
     *
     * @param string $filename
     * @param LoggerInterface|null $logger
     * @return array
     */
    public static function loadFile(string $filename): array
    {
        $current_currency = [];

        try {
            $file_content = file_get_contents($filename);
            if ($file_content === FALSE) throw new Exception("Currency file `{$filename}` not found", 1);

            $file_content = json_decode($file_content, true);
            if (($file_content === NULL) || !is_array($file_content)) throw new Exception("Currency data can't be parsed", 2);

            if (!array_key_exists('summary', $file_content)) throw new Exception("Currency file does not contain DATA section", 3);

            // добиваем валюту до $MAX_CURRENCY_STRING_LENGTH нулями (то есть 55.4 (4 десятых) добивается до 55.40 (40 копеек)
            foreach ($file_content['summary'] as $currency_code => $currency_data) {
                // $current_currency[ $currency_code ] = str_pad($currency_data, self::$options['max_currency_string_length'], '0');
                $current_currency[ $currency_code ] = sprintf(self::$options['out_format'], $currency_data);
            }

        } catch (Exception $e) {
            self::$logger->error('[ERROR] Load Currency ', [$e->getMessage()]);
        }

        return $current_currency;
    }

    /**
     * @param $fetch_date
     * @return mixed
     * @throws Exception
     */
    private static function loadCurrencyDataset($fetch_date)
    {
        $fetch_date = $fetch_date ?? (new DateTime())->format('d/m/Y');
        $url = self::CBR_URL;

        $curl = new Curl();
        $curl->setCookie('stay_here', 1);
        $curl->setOpt(CURLOPT_RETURNTRANSFER, true);
        $curl->setOpt(CURLOPT_FOLLOWLOCATION, false);
        $curl->setOpt(CURLOPT_MAXREDIRS,10);
        $curl->get($url, [
            'date_req'  =>  $fetch_date
        ]);

        if ($curl->error)
            throw new Exception("[CURL] Error", $curl->error_code);

        $xml = simplexml_load_string($curl->response);
        $json = json_encode( $xml );
        return json_decode( $json , true );
    }

    /**
     * Форматирует
     *
     * @param $value
     * @return string
     */
    private static function formatCurrencyValue($value)
    {
        if (version_compare(PHP_VERSION, "7.4", '>=')) {
            return numfmt_format_currency(numfmt_create( 'ru_RU', NumberFormatter::CURRENCY ), $value, "RUR");
        } else {
            return money_format('%i', str_replace(',', '.', $value));
        }
    }
}

# -eof-
