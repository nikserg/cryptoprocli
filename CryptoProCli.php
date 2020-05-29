<?php

namespace nikserg\cryptoprocli;

/**
 * Class CryptoProCli
 *
 * Функции для работы с консольной утилитой КриптоПро
 *
 * @package nikserg\cryptoprocli
 */
class CryptoProCli
{
    /**
     * @var string Путь к исполняемому файлу Curl КриптоПро
     */
    public static $cryptcpExec = '/opt/cprocsp/bin/amd64/cryptcp';

    private static function getCryptcpExec()
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            return '"'.self::$cryptcpExec.'"';
        } else {
            return self::$cryptcpExec;
        }
    }

    /**
     * Подписать ранее неподписанный файл
     *
     * @param string $file
     * @param string $thumbprint
     * @param null   $toFile
     * @throws \Exception
     */
    public static function signFile($file, $thumbprint, $toFile = null)
    {
        $shellCommand = self::getCryptcpExec() .
            ' -sign -thumbprint ' . $thumbprint . ' ' . $file . ' ' . $toFile;
        $result = shell_exec($shellCommand);

        if (strpos($result, "Signed message is created.") <= 0 && strpos($result,
                "Подписанное сообщение успешно создано") <= 0) {
            throw new \Exception('В ответе Cryptcp не найдена строка "Signed message is created" или "Подписанное сообщение успешно создано": ' . $result . ' команда ' . $shellCommand);
        }
    }

    /**
     * Подписать данные
     *
     *
     * @param $data
     * @param $thumbprint
     * @return bool|string
     */
    public static function signData($data, $thumbprint)
    {
        $from = tempnam('/tmp', 'cpsign');
        $to = tempnam('/tmp', 'cpsign');
        file_put_contents($from, $data);

        self::signFile($from, $thumbprint, $to);
        unlink($from);
        $return = file_get_contents($to);
        unlink($to);
        return $return;
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
        $shellCommand = self::getCryptcpExec() .
            ' -addsign -thumbprint ' . $thumbprint . ' ' . $file;
        $result = shell_exec($shellCommand);

        if (strpos($result, "Signed message is created.") <= 0) {
            throw new \Exception('В ответе Cryptcp не найдена строка Signed message is created: ' . $result . ' команда ' . $shellCommand);
        }
    }

    /**
     * @param $file
     * @throws \Exception
     */
    public static function verifyFile($file)
    {
        $shellCommand = self::getCryptcpExec() .
            ' -verify -verall ' . $file;
        $result = shell_exec($shellCommand);
        if (strpos($result, "[ErrorCode: 0x00000000]") === false && strpos($result, "[ReturnCode: 0]") === false) {
            //Проверка неуспешна
            throw new \Exception('В ответе Cryptcp не найдена строка [ErrorCode: 0x00000000] и [ReturnCode: 0]: ' . $result . ' команда ' . $shellCommand);
        }
    }
}
