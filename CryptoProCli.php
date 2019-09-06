<?php
namespace nikserg\cryptoprocli;

/**
 * Class CryptoProCli
 * 
 * Функции для работы с консольной утилитой КриптоПро
 *
 * @package nikserg\cryptoprocli
 */
class CryptoProCli {
    /**
     * @var string Путь к исполняемому файлу Curl КриптоПро
     */
    public static $cryptcpExec = '/opt/cprocsp/bin/amd64/cryptcp';


    /**
     * Подписать ранее неподписанный файл
     * 
     * @param string $file
     * @param string $thumbprint
     * @param null $toFile
     * @throws \Exception
     */
    public static function signFile($file, $thumbprint, $toFile = null)
    {
        $shellCommand = 'yes "o" 2>/dev/null | ' . self::$cryptcpExec .
            ' -sign -thumbprint ' . $thumbprint . ' ' . $file . ' ' . $toFile;
        $result = shell_exec($shellCommand);

        if (strpos($result, "Signed message is created.") <= 0) {
            throw new \Exception('В ответе Cryptcp не найдена строка Signed message is created: ' . $result . ' команда ' . $shellCommand);
        }
    }

    /**
     * Добавить подпись в файл, уже содержащий подпись
     *
     * @param string $file Путь к файлу
     * @param string $thumbprint SHA1 отпечаток, например, bb959544444d8d9e13ca3b8801d5f7a52f91fe97
     * @throws \Exception
     */
    public static function addSignToFile($file, $thumbprint)
    {
        $shellCommand = 'yes "o" | ' . self::$cryptcpExec .
            ' -addsign -thumbprint ' . $thumbprint . ' ' . $file;
        $result = shell_exec($shellCommand);

        if (strpos($result, "Signed message is created.") <= 0) {
            throw new \Exception('В ответе Cryptcp не найдена строка Signed message is created: ' . $result . ' команда ' . $shellCommand);
        }
    }
}
