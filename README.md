# cryptoprocli

Функции для работы с серверной КриптоПро. Для работы должен быть установлен КриптоПро и доступен https://www.cryptopro.ru/products/other/cryptcp

## Установка

`composer require nikserg/cryptoprocli`

В переменной `CryptoProCli::$cryptcpExec` хранится путь к утилите `cryptcp`. Путь по умолчанию подходит для Linux-систем. Для Windows-систем путь нужно изменить.

## Функции

Под `$thumbprint` понимается SHA1-отпечаток подписи.

* `CryptoProCli::signFile($file, $thumbprint, $toFile = null)` - Подписать ранее неподписанный файл
* `CryptoProCli::signData($data, $thumbprint)` - Подписать данные
* `CryptoProCli::addSignToFile($file, $thumbprint)` - Добавить подпись в файл, уже содержащий подпись
* `CryptoProCli::verifyFile($file):bool` - Проверяет корректность всех подписей, наложенных на файл.