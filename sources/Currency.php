<?php

namespace AJUR\Toolkit;

use Curl\Curl;
use DateTime;
use RuntimeException;
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
        'format_method'                 =>  'legacy',
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
    private static $logger = null;
    
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
        
        self::$options['format_method']
            = array_key_exists('format_method', $options)
            ? $options['format_method']
            : 'legacy';
        if (!in_array(self::$options['format_method'], ['legacy', 'numfmt'])) {
            self::$options['format_method'] = 'legacy';
        }
    
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
     */
    public static function selectCurrencySet(array $codes = [], $fetch_date = null):bool
    {
        $cbr_prices = [];
        $cbr_prices_compact = [];

        $daily = self::loadCurrencyDataset($fetch_date);

        if (!$daily || !array_key_exists('Valute', $daily)) {
            throw new RuntimeException("[ERROR] CBR API returns empty data");
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
     * @return array
     */
    public static function getPrices():array
    {
        return self::$cbr_prices;
    }

    /**
     * Возвращает информацию о загруженных валютах в компактном виде
     *
     * @return mixed
     */
    public static function getPricesCompact():array
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
     * @return array
     */
    public static function loadFile(string $filename): array
    {
        $current_currency = [];

        try {
            if (empty($filename)) {
                throw new RuntimeException( "Currency file not defined (null or empty string given)", 4 );
            }
            
            if (!file_exists($filename)) {
                throw new RuntimeException("Currency file `{$filename}` not found", 1);
            }
            
            if (!is_readable($filename)) {
                throw new RuntimeException("Currency file `{$filename}` not readable", 1);
            }
            
            $file_content = file_get_contents($filename);
            if ($file_content === false) {
                throw new RuntimeException( "Currency file `{$filename}` can't be retrieved", 1 );
            }

            $file_content = json_decode($file_content, true);
            if (!is_array($file_content)) {
                throw new RuntimeException( "Currency data can't be parsed", 2 );
            }

            if (!array_key_exists('summary', $file_content)) {
                throw new RuntimeException( "Currency file does not contain DATA section", 3 );
            }

            // добиваем валюту до $MAX_CURRENCY_STRING_LENGTH нулями (то есть 55.4 (4 десятых) добивается до 55.40 (40 копеек)
            foreach ($file_content['summary'] as $currency_code => $currency_data) {
                $current_currency[ $currency_code ] = sprintf(self::$options['out_format'], $currency_data);
            }

        } catch (RuntimeException $e) {
            if (self::$logger instanceof LoggerInterface) {
                self::$logger->error('[ERROR] Load Currency ', [$e->getMessage()]);
            }
            
        }

        return $current_currency;
    }

    /**
     * @param $fetch_date
     * @return mixed
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
            throw new RuntimeException("[CURL] Error", $curl->error_code);

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
        // return money_format('%i', str_replace(',', '.', $value));
        return number_format(str_replace(',', '.', $value), 2, '.', '');
    }
    
    /**
     * Форматирует деньги с учетом параметров локали.
     * Это НЕ числовое форматирование
     *
     * @param $format
     * @param $number
     * @return array|mixed|string|string[]
     */
    public static function money_format_with_locale($format, $number)
    {
        $regex  = '/%((?:[\^!\-]|\+|\(|\=.)*)([0-9]+)?'.
            '(?:#([0-9]+))?(?:\.([0-9]+))?([in%])/';
        if (setlocale(LC_MONETARY, 0) == 'C') {
            setlocale(LC_MONETARY, '');
        }
        $locale = localeconv();
        preg_match_all($regex, $format, $matches, PREG_SET_ORDER);
        foreach ($matches as $fmatch) {
            $value = (float)$number;
            $flags = [
                'fillchar'  => preg_match('/\=(.)/', $fmatch[1], $match) ? $match[1] : ' ',
                'nogroup'   => preg_match('/\^/', $fmatch[1]) > 0,
                'usesignal' => preg_match('/\+|\(/', $fmatch[1], $match) ? $match[0] : '+',
                'nosimbol'  => preg_match('/\!/', $fmatch[1]) > 0,
                'isleft'    => preg_match('/\-/', $fmatch[1]) > 0
            ];
            $width      = trim($fmatch[2]) ? (int)$fmatch[2] : 0;
            $left       = trim($fmatch[3]) ? (int)$fmatch[3] : 0;
            $right      = trim($fmatch[4]) === '' ? $locale['int_frac_digits'] : (int)$fmatch[4];
            $conversion = $fmatch[5];
            
            $positive = true;
            if ($value < 0) {
                $positive = false;
                $value  *= -1;
            }
            $letter = $positive ? 'p' : 'n';
            
            $prefix = $suffix = $cprefix = $csuffix = $signal = '';
            
            $signal = $positive ? $locale['positive_sign'] : $locale['negative_sign'];
            switch (true) {
                case $locale["{$letter}_sign_posn"] == 1 && $flags['usesignal'] == '+':
                    $prefix = $signal;
                    break;
                case $locale["{$letter}_sign_posn"] == 2 && $flags['usesignal'] == '+':
                    $suffix = $signal;
                    break;
                case $locale["{$letter}_sign_posn"] == 3 && $flags['usesignal'] == '+':
                    $cprefix = $signal;
                    break;
                case $locale["{$letter}_sign_posn"] == 4 && $flags['usesignal'] == '+':
                    $csuffix = $signal;
                    break;
                case $flags['usesignal'] == '(' && $letter === 'n':
                case $locale["{$letter}_sign_posn"] == 0:
                    $prefix = '(';
                    $suffix = ')';
                    break;
            }
            if (!$flags['nosimbol']) {
                $currency = $cprefix . ($conversion == 'i' ? $locale['int_curr_symbol'] : $locale['currency_symbol'] ) . $csuffix;
            } else {
                $currency = '';
            }
            $space  = $locale["{$letter}_sep_by_space"] ? ' ' : '';
            
            $value = number_format(
                $value,
                $right,
                $locale['mon_decimal_point'],
                $flags['nogroup'] ? '' : $locale['mon_thousands_sep']
            );
            $value = @explode($locale['mon_decimal_point'], $value);
            
            $n = strlen($prefix) + strlen($currency) + strlen($value[0]);
            if ($left > 0 && $left > $n) {
                $value[0] = str_repeat($flags['fillchar'], $left - $n) . $value[0];
            }
            $value = implode($locale['mon_decimal_point'], $value);
            if ($locale["{$letter}_cs_precedes"]) {
                $value = $prefix . $currency . $space . $value . $suffix;
            } else {
                $value = $prefix . $value . $space . $currency . $suffix;
            }
            if ($width > 0) {
                $value = str_pad(
                    $value,
                    $width,
                    $flags['fillchar'],
                    $flags['isleft'] ? STR_PAD_RIGHT : STR_PAD_LEFT
                );
            }
            
            $format = str_replace($fmatch[0], $value, $format);
        }
        return $format;
    }
}

# -eof-
