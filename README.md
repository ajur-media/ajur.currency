# AJUR Media Steamboat Currency Toolkit

## Методы

- `Currency::init($options = [], $logger = null)` - инициализирует класс, оверрайдит опции новыми значениями. `$logger` - инстанс логгера (например, `AppLogger::scope()`).   
Список опций:
    - `locale` - локаль, используемая для форматирования денежного номинала, по умолчанию (`ru_RU`)
    - `out_format` - формат вывода стоимости валюты, по умолчанию `%01.2f` (смотри форматы `sprintf()`)
- `Currency::selectCurrencySet([])` - загружает данные из ЦБР и выбирает из них валюты, символьные коды которых переданы в параметре. Важно: коды передаются в верхнем регистре, например `['USD', 'EUR']`.
- `Currency::getPrices()` - получаем данные о валютах в полной форме (код, название, стоимость, номинал, исходное значение стоимости, переданное банком)
- `Currency::getPricesCompact()` - получаем компактные данные вида { "<код валюты>": <стоимость>, ... }
- `Currency::storeFile(<name>)` - сохраняет загруженные валюты в файле
- `Currency::loadFile(<name>)` - загружает данные по валюте из файла и форматирует их в формат "XX.YY"

Внимание, код валюты **везде** в верхнем регистре. Это надо учитывать при отображении данных. 

## HOW TO USE?

### Получение данных (в cron)

```
use \AJUR\Toolkit\Currency;

Currency::selectCurrencySet([]);

Currency::storeFile('data.json');
```


### Загрузка данных из файла (в движке)

```
use \AJUR\Toolkit\Currency;
...
$currency = Currency::loadFile(getenv('STORAGE.CURRENCY'), \Arris\AppLogger::scope('currency'));
```