[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/nikserg/cryptoprocli/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/nikserg/cryptoprocli/?branch=master)
[![Code Intelligence Status](https://scrutinizer-ci.com/g/nikserg/cryptoprocli/badges/code-intelligence.svg?b=master)](https://scrutinizer-ci.com/code-intelligence)

# cryptoprocli

Функции для работы с серверной КриптоПро. Для работы должен быть установлен КриптоПро и доступен https://www.cryptopro.ru/products/other/cryptcp

## Установка

`composer require nikserg/cryptoprocli`

В свойстве `$cryptcpExec` объекта CryptoProCli хранится путь к утилите `cryptcp`. В свойстве `$certmgrExec` объекта CryptoProCli хранится путь к утилите `cryptcp`. Пути по умолчанию подходят для Linux-систем. Для Windows-систем пути нужно изменить.

## Конструктор объекта CryptoProCli

- `bool $detached = false`. Создать открепленную подпись.
- `bool $nochain = false`. Небезопасный режим - когда цепочка подтверждения подписи не проверяется.
- `string $pin = ''`. Задать пароль ключевого контейнера.

## Методы объекта CryptoProCli

Под `$thumbprint` понимается SHA1-отпечаток подписи.

* `getSigns()` - Получить список всех подписей
* `signFile(string $file, string $thumbprint, string $toFile = '')` - Подписать ранее неподписанный файл
* `signData(string $data, string $thumbprint)` - Подписать данные
* `addSignToFile(string $file, string $thumbprint)` - Добавить подпись в файл, уже содержащий подпись
* `verifyFile(string $file)` - Проверяет корректность всех подписей, наложенных на файл. В случае ошибки выкидывает исключение, если все хорошо, ничего не происходит.
* `verifyFileContent(string $file)` - Аналогично verifyFile, но по содержимому.
