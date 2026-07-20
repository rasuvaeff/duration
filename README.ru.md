# rasuvaeff/duration

[![Latest Stable Version](https://poser.pugx.org/rasuvaeff/duration/v)](https://packagist.org/packages/rasuvaeff/duration)
[![Total Downloads](https://poser.pugx.org/rasuvaeff/duration/downloads)](https://packagist.org/packages/rasuvaeff/duration)
[![Build](https://github.com/rasuvaeff/duration/actions/workflows/build.yml/badge.svg)](https://github.com/rasuvaeff/duration/actions/workflows/build.yml)
[![Static analysis](https://github.com/rasuvaeff/duration/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/rasuvaeff/duration/actions/workflows/static-analysis.yml)
[![Psalm level](https://img.shields.io/badge/psalm-level_1-blue.svg)](https://github.com/rasuvaeff/duration/actions/workflows/static-analysis.yml)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/duration/php)](https://packagist.org/packages/rasuvaeff/duration)
[![License](https://img.shields.io/badge/license-BSD--3--Clause-blue.svg)](LICENSE.md)
[English version](README.md)

Типобезопасный, иммутабельный, неотрицательный value object длительности для PHP.
Заменяет «голые» параметры `int` (секунды? миллисекунды?) явной единицей измерения,
устраняя целый класс ошибок путаницы между секундами и миллисекундами. Создан как
базовый строительный блок для параметров timeout/wait/lease в пакетах устойчивости
(resilience).

> Используете AI-ассистента? В [llms.txt](llms.txt) — компактный API-справочник,
> которым можно поделиться с моделью.

## Требования

- PHP 8.3+
- Нет runtime-зависимостей

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

### Фабрики

| Метод | Описание |
|---|---|
| `Duration::zero()` | Нулевая длительность |
| `Duration::micros(int $micros)` | Из микросекунд |
| `Duration::millis(int $millis)` | Из миллисекунд |
| `Duration::seconds(int\|float $seconds)` | Из секунд (допускается дробное значение) |
| `Duration::minutes(int\|float $minutes)` | Из минут (допускается дробное значение) |
| `Duration::hours(int\|float $hours)` | Из часов (допускается дробное значение) |
| `Duration::days(int\|float $days)` | Из дней (допускается дробное значение) |

### Преобразования

| Метод | Возвращает | Примечания |
|---|---|---|
| `toMicros()` | `int` | Точно — микросекунды являются единицей хранения |
| `toMillis()` | `int` | Округление **вверх** (`ceil`) |
| `toSeconds()` | `float` | |
| `toMinutes()` | `float` | |

`toMillis()` округляет вверх намеренно: ненулевая длительность короче миллисекунды
никогда не должна превращаться в `0`, потому что `0ms` означает «нет таймаута /
бесконечность» для cURL и большинства HTTP-клиентов — а именно этот сбой таймаут
и должен предотвращать.

### Арифметика и сравнение

| Метод | Возвращает | Описание |
|---|---|---|
| `plus(Duration $other)` | `Duration` | Сумма двух длительностей |
| `minus(Duration $other)` | `Duration` | Насыщающая разность — никогда не отрицательна (прошедший дедлайн даёт `0`) |
| `Duration::min($a, $b)` | `Duration` | Статический — меньшая из двух длительностей |
| `Duration::max($a, $b)` | `Duration` | Статический — большая из двух длительностей |
| `isZero()` | `bool` | Истинно для нулевой длительности |
| `isPositive()` | `bool` | Истинно для ненулевой длительности |
| `equals(Duration $other)` | `bool` | Равенство по длине |
| `compareTo(Duration $other)` | `int` | `-1` / `0` / `1` |
| `isGreaterThan(Duration $other)` | `bool` | Строго длиннее |
| `isLessThan(Duration $other)` | `bool` | Строго короче |

### Строковое представление

`Duration` реализует `Stringable`. Приведение к строке возвращает человекочитаемую
форму, выбирая наибольшую единицу со значением не меньше 1:

```
"0"        zero
"1µs"      microseconds (integer)
"250ms"    milliseconds (integer, rounded up — matches toMillis())
"2.5s"     seconds (%g, trailing zeros trimmed)
"1.5min"   minutes
"2h"       hours
"1.5d"     days
```

Набор единиц, правила округления и написание суффиксов — наблюдаемый контракт: их
изменение является повышением **major**-версии. Для машиночитаемых значений
используйте напрямую `toMicros()` / `toMillis()` / `toSeconds()` / `toMinutes()`.

## Безопасность

Не относится к security-чувствительному коду: чистый value object без побочных
эффектов. Не выполняет I/O и не хранит секретов. Единственный инфорсимый контракт —
входной домен:

- Отрицательные длительности бросают `InvalidArgumentException` (`Duration cannot be negative`).
- Неконечные числа с плавающей точкой (`INF` / `NAN`) бросают `InvalidArgumentException` (`Duration must be finite`).

## Примеры

См. [examples/](examples/) — запускаемые скрипты. Ожидается, что примеры
выполняются без fatal errors и остаются согласованными с документированным
публичным API.

| Скрипт | Показывает | Нужен сервер? |
|---|---|---|
| `basic.php` | Фабрики, преобразования, арифметика, сравнение | нет |

## Разработка

На хосте нет PHP/Composer — запускайте через Docker-образ `composer:2`:

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer install
docker run --rm -v "$PWD":/app -w /app composer:2 composer build
docker run --rm -v "$PWD":/app -w /app composer:2 composer cs:fix
docker run --rm -v "$PWD":/app -w /app composer:2 composer test
docker run --rm -v "$PWD":/app -w /app composer:2 composer release-check
```

Или через Make:

```bash
make install
make build
make cs-fix
make test
make test-coverage
make mutation
make release-check
```

`make test-coverage` и `make mutation` поднимают `pcov` внутри контейнера
`composer:2`, потому что в базовом образе нет драйвера покрытия.

## Лицензия

[BSD-3-Clause](LICENSE.md)
