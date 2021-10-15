<?php

namespace nikserg\cryptoprocli;

use nikserg\cryptoprocli\Exception\Cli;
use nikserg\cryptoprocli\Exception\SignatureError;

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
            return '"' . self::$cryptcpExec . '"';
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
     * @throws Cli
     */
    public static function signFile($file, $thumbprint, $toFile = null)
    {
        $shellCommand = self::getCryptcpExec() .
            ' -sign -thumbprint ' . $thumbprint . ' ' . $file . ' ' . $toFile;
        $result = shell_exec($shellCommand);

        if (strpos($result, "Signed message is created.") <= 0 && strpos($result,
                "Подписанное сообщение успешно создано") <= 0) {
            throw new Cli('В ответе Cryptcp не найдена строка "Signed message is created" или "Подписанное сообщение успешно создано": ' . $result . ' команда ' . $shellCommand);
        }
    }

    /**
     * Подписать данные
     *
     *
     * @param $data
     * @param $thumbprint
     * @return bool|string
     * @throws Cli
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
     * @throws Cli
     */
    public static function addSignToFile($file, $thumbprint)
    {
        $shellCommand = self::getCryptcpExec() .
            ' -addsign -thumbprint ' . $thumbprint . ' ' . $file;
        $result = shell_exec($shellCommand);
        if (strpos($result, "Signed message is created.") <= 0) {
            throw new Cli('В ответе Cryptcp не найдена строка Signed message is created: ' . $result . ' команда ' . $shellCommand);
        }
    }

    /**
     * Проверить, что содержимое файла подписано правильной подписью
     *
     *
     * @param $fileContent
     * @throws Cli
     * @throws SignatureError
     */
    public static function verifyFileContent($fileContent)
    {
        $file = tempnam(sys_get_temp_dir(), 'cpc');
        file_put_contents($file, $fileContent);
        try {
            self::verifyFile($file);
        } finally {
            unlink($file);
        }
    }

    /**
     * Проверить, что содержимое файла подписано правильной подписью открепленной подписью
     *
     *
     * @param $fileSignContent
     * @param $fileToBeSigned
     * @throws Cli
     * @throws SignatureError
     */
    public static function verifyFileContentDetached($fileSignContent, $fileToBeSignedContent)
    {
        $fileToBeSigned = tempnam(sys_get_temp_dir(), 'detach');
        $fileSign = $fileToBeSigned . '.sgn';
        file_put_contents($fileSign, $fileSignContent);
        file_put_contents($fileToBeSigned, $fileToBeSignedContent);
        try {
            self::verifyFileDetached($fileSign, $fileToBeSigned, sys_get_temp_dir());
        } finally {
            unlink($fileSign);
            unlink($fileToBeSigned);
        }
    }

    private static function getDevNull()
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            return 'NUL';
        }
        return '/dev/null';
    }

    CONST ERROR_CODE_WRONG_SIGN = '0x200001f9';
    const ERROR_CODE_MESSAGE = [
        '0x20000133' => 'Цепочка сертификатов не проверена',
        self::ERROR_CODE_WRONG_SIGN => 'Подпись не верна',
        '0x2000012d' => 'Сертификаты не найдены',
        '0x2000012e' => 'Более одного сертификата',
    ];

    /**
     * Проверить, что файл подписан правильной подписью
     *
     *
     * @param $file
     * @throws Cli
     * @throws SignatureError
     */
    public static function verifyFile($file)
    {
        $shellCommand = 'yes "n" 2> '.self::getDevNull().' | ' . escapeshellarg(self::$cryptcpExec) . ' -verify -verall ' . escapeshellarg($file);
        $result = shell_exec($shellCommand);
        if (strpos($result, "[ErrorCode: 0x00000000]") === false && strpos($result, "[ReturnCode: 0]") === false) {
            preg_match('#\[ErrorCode: (.+)\]#', $result, $matches);
            $code = strtolower($matches[1]);
            if (isset(self::ERROR_CODE_MESSAGE[$code])) {
                $message = self::ERROR_CODE_MESSAGE[$code];
                //Дополнительная расшифровка ошибки
                if (strpos($result, 'The certificate or certificate chain is based on an untrusted root') !== false) {
                    $message .= ' - нет доверия к корневому сертификату УЦ, выпустившего эту подпись.';
                }
                throw new SignatureError($message);
            }
            throw new Cli("Неожиданный результат $shellCommand: \n$result");
        }
    }

    /**
     * Проверить, что файл подписан правильной открепленной подписью подписью
     *
     * @param $fileSign
     * @param $fileToBeSigned
     * @param $fileDir
     * @throws Cli
     * @throws SignatureError
     */
    public static function verifyFileDetached($fileSign, $fileToBeSigned, $fileDir)
    {
        //Пример cryptcp.exe -verify y:\text.txt -detached -nochain -f y:\signature.sig -dir y:\
        $shellCommand = 'yes "n" 2> '.self::getDevNull() . ' | ' . escapeshellarg(self::$cryptcpExec) . ' -vsignf -dir '
            . escapeshellarg($fileDir) . ' '
            . escapeshellarg($fileToBeSigned)
            . ' -f ' . escapeshellarg($fileSign);
        $result = shell_exec($shellCommand);
        if (strpos($result, "[ErrorCode: 0x00000000]") === false && strpos($result, "[ReturnCode: 0]") === false) {
            preg_match('#\[ErrorCode: (.+)\]#', $result, $matches);
            $code = strtolower($matches[1]);
            if (isset(self::ERROR_CODE_MESSAGE[$code])) {
                throw new SignatureError(self::ERROR_CODE_MESSAGE[$code]);
            }
            throw new Cli("Неожиданный результат $shellCommand: \n$result");
        }
    }

}
