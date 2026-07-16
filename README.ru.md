# Расуваефф/длительность
[![Latest Stable Version](https://poser.pugx.org/rasuvaeff/duration/v)](https://packagist.org/packages/rasuvaeff/duration)
[![Total Downloads](https://poser.pugx.org/rasuvaeff/duration/downloads)](https://packagist.org/packages/rasuvaeff/duration)
[![Build](https://github.com/rasuvaeff/duration/actions/workflows/build.yml/badge.svg)](https://github.com/rasuvaeff/duration/actions/workflows/build.yml)
[![Static analysis](https://github.com/rasuvaeff/duration/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/rasuvaeff/duration/actions/workflows/static-analysis.yml)
[![Psalm level](https://img.shields.io/badge/psalm-level_1-blue.svg)](https://github.com/rasuvaeff/duration/actions/workflows/static-analysis.yml)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/duration/php)](https://packagist.org/packages/rasuvaeff/duration)
[![License](https://img.shields.io/badge/license-BSD--3--Clause-blue.svg)](LICENSE.md)
Типобезопасный, неизменяемый, неотрицательный объект значения продолжительности для PHP. Заменяет простые параметры
 `int` (секунды? миллисекунды?) на явные единицы измерения, удаляя целый класс
 ошибок «секунды против миллисекунд».
 параметры ожидания и аренды, разработанные в качестве основы для тайм-аута, в пакетах обеспечения устойчивости.

 > Используете помощника по программированию с искусственным интеллектом? [llms.txt](llms.txt) содержит компактную ссылку на API, которой вы можете поделиться с моделью. @@ЛИНИЯ@@
## Требования
- PHP 8.3+
 - Нет зависимостей во время выполнения

## Установка
```bash
composer require rasuvaeff/duration
```
## Использование
```php
use Rasuvaeff\Duration\Duration;

$timeout = Duration::seconds(2.5);

$timeout->toMillis();  // 2500
$timeout->toMicros();  // 2500000
$timeout->toSeconds(); // 2.5

$total = Duration::millis(500)->plus(Duration::seconds(1)); // 1500ms

Duration::seconds(1)->isGreaterThan(Duration::millis(500)); // true

echo Duration::minutes(1.5); // "1.5min"
```
### Заводы
| Метод | Описание |
 |---|---|
 | `Продолжительность::ноль()` | Продолжительность нулевой длины |
 | `Duration::micros(int $micros)` | Из микросекунд |
 | `Продолжительность::миллис(int $миллис)` | Из миллисекунд |
 | `Продолжительность::секунды(int\|float $секунды)` | С секунд (допускается дробно) |
 | `Продолжительность::минуты(int\|float $минуты)` | От минут (допускается дробно) |
 | `Продолжительность::часы(int\|float $hours)` | От часов (допускается дробно) |
 | `Продолжительность::days(int\|float $days)` | От дней (допускается дробно) | @@ЛИНИЯ@@
### Конверсии
| Метод | Возврат | Заметки |
 |---|---|---|
 | `toMicros()` | `интервал` | Точно — микросекунды — это единица хранения |
 | `тоМиллис()` | `интервал` | Округлено **вверх** («ячейка») |
 | `toSeconds()` | `плавать` | |
 | `toMinutes()` | `плавать` | |

 `toMillis()` округляется намеренно: ненулевая продолжительность менее миллисекунды должна
 никогда не сворачиваться до `0`, поскольку `0ms` означает "нет таймаута/бесконечность" для cURL и
 большинства HTTP-клиентов — именно этот сбой предназначен для предотвращения значения таймаута. @@ЛИНИЯ@@
### Арифметика и сравнение
| Метод | Возврат | Описание |
 |---|---|---|
 | `плюс(Продолжительность $other)` | `Продолжительность` | Сумма двух длительностей |
 | `минус(Продолжительность $other)` | `Продолжительность` | Насыщающая разница — никогда не бывает отрицательной (пройденный срок равен «0») |
 | `Продолжительность::мин($a, $b)` | `Продолжительность` | Статический — меньшая из двух длительностей |
 | `Продолжительность::max($a, $b)` | `Продолжительность` | Статический — большая из двух длительностей |
 | `isZero()` | `бул` | Истинно, когда нулевая длина |
 | `isPositive()` | `бул` | Истина, когда ненулевое |
 | `equals(Duration $other)` | `бул` | Равная длина |
 | `compareTo(Продолжительность $other)` | `интервал` | `-1` / `0` / `1` |
 | `isGreaterThan(Duration $other)` | `бул` | Строго длиннее |
 | `isLessThan(Продолжительность $other)` | `бул` | Строго короче | @@ЛИНИЯ@@
### Строковое представление
`Duration` реализует `Stringable`. Приведение к строке дает удобочитаемую форму
, в которой выбирается наибольшая единица измерения со значением не менее 1:

```
"0"        zero
"1µs"      microseconds (integer)
"250ms"    milliseconds (integer, rounded up — matches toMillis())
"2.5s"     seconds (%g, trailing zeros trimmed)
"1.5min"   minutes
"2h"       hours
"1.5d"     days
```
Набор единиц измерения, округление и написание суффикса являются наблюдаемым контрактом: изменение
 в них является **значительным** изменением версии. Для машиночитаемых значений используйте `toMicros()` /
 `toMillis()` / `toSeconds()` / `toMinutes()` напрямую. @@ЛИНИЯ@@
## Безопасность
Не чувствителен к безопасности: чистый объект значения без побочных эффектов. Он не выполняет
 ввода-вывода и не хранит никаких секретов. Единственным обязательным контрактом является входной домен:

 — отрицательные длительности вызывают исключение InvalidArgumentException («Длительность не может быть отрицательной»).
 — Неконечные числа с плавающей запятой (`INF` / `NAN`) выбрасывают `InvalidArgumentException` ("Длительность должна быть конечной"). @@ЛИНИЯ@@
## Примеры
См. [examples/](examples/) для работоспособных сценариев.
 Ожидается, что примеры будут выполняться без фатальных ошибок и соответствовать документированному
 общедоступному API.

 | Скрипт | Шоу | Нужен сервер? |
 |---|---|---|
 | `basic.php` | Фабрики, преобразования, арифметика, сравнение | нет | @@ЛИНИЯ@@
## Разработка
На хосте нет PHP/Composer — запустите в Docker через образ `composer:2`:

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer install
docker run --rm -v "$PWD":/app -w /app composer:2 composer build
docker run --rm -v "$PWD":/app -w /app composer:2 composer cs:fix
docker run --rm -v "$PWD":/app -w /app composer:2 composer test
docker run --rm -v "$PWD":/app -w /app composer:2 composer release-check
```
Или с помощью Make:

```bash
make install
make build
make cs-fix
make test
make test-coverage
make mutation
make release-check
```
`make test-coverage` и `makemutation` загружают `pcov` внутри контейнера
 `composer:2`, поскольку базовый образ не имеет драйвера покрытия. @@ЛИНИЯ@@
## Лицензия
[BSD-3-пункт](LICENSE.md)
