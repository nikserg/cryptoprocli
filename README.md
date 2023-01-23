[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/nikserg/cryptoprocli/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/nikserg/cryptoprocli/?branch=master)
[![Code Intelligence Status](https://scrutinizer-ci.com/g/nikserg/cryptoprocli/badges/code-intelligence.svg?b=master)](https://scrutinizer-ci.com/code-intelligence)

# cryptoprocli

Функции для работы с серверной КриптоПро. Для работы должен быть установлен КриптоПро и доступен https://www.cryptopro.ru/products/other/cryptcp

## Установка

`composer require nikserg/cryptoprocli`

В свойстве `$cryptcpExec` объекта CryptoProCli хранится путь к утилите `cryptcp`. В свойстве `$certmgrExec` объекта CryptoProCli хранится путь к утилите `cryptcp`. В свойстве `$curlExec` объекта CryptoProCli хранится путь к утилите `curl`. Пути по умолчанию подходят для Linux-систем. Для Windows-систем пути нужно изменить.

## Конструктор объекта CryptoProCli

- `bool $nochain = false`. Небезопасный режим - когда цепочка подтверждения подписи не проверяется.

## Некоторые параметры методов объекта CryptoProCli

- `string|array $thumbprint` - SHA1 hash подписи, либо неассоциативный массив собержащий thumbprint и pin пароль ключевого контейнера.
- `bool $detached = false` - Создать или нет открепленную подпись.

## Методы объекта CryptoProCli

* `getSigns()` - Получить список всех подписей.
* `signFile(string $file, string $thumbprint, string $toFile = '')` - Подписать ранее неподписанный файл.
* `signData(string $data, string $thumbprint)` - Подписать данные.
* `addSignToFile(string $file, string $thumbprint)` - Добавить подпись в файл, уже содержащий подпись.
* `verifyFile(string $file)` - Проверяет корректность всех подписей, наложенных на файл. В случае ошибки выкидывает исключение, если нет ошибок, возвращает результат операции.
* `verifyFileContent(string $fileContent)` - Аналогично verifyFile, но по-содержимому.
* `verifyFileDetached(string $fileToBeSigned, string $fileSign)` - Аналогично verifyFile, но для открепленной подписи.
* `verifyFileContentDetached(string $fileToBeSignedContent, string $fileSignContent)` - Аналогично verifyFileContent, но для открепленной подписи.
* `proxyCurl(string $url, string|array $thumbprint, string $method = 'GET', ?array $headers = null, ?string $data = null)` - Curl-запросы с использованием гостовых сертификатов
