<?php

namespace nikserg\cryptoprocli;

use nikserg\cryptoprocli\Exception\Cli;
use nikserg\cryptoprocli\Exception\SignatureError;

/**
 * Class CryptoProCli
 *
 * Функции для работы с консольной утилитой КриптоПро
 *
 *
 * @package nikserg\cryptoprocli
 */
class CryptoProCli
{
    /**
     * @var bool Создать открепленную подпись.
     */
    private bool $detached;

    /**
     * @var bool Небезопасный режим - когда цепочка подтверждения подписи не проверяется.
     * Включение даст возможность использовать самоподписанные сертификаты.
     */
    private bool $nochain;

    /**
     * @var string Задать пароль ключевого контейнера.
     */
    private string $pin;

    /**
     * @var string Путь к исполняемому файлу Curl КриптоПро
     */
    private static string $cryptcpExec = '/opt/cprocsp/bin/amd64/cryptcp';

    /**
     * @var string Команда получения списка всех подписей
     */
    private static string $certmgrExec = '/opt/cprocsp/bin/amd64/certmgr -list -store uMy';

    /**
     * @param bool $detached
     * @param bool $nochain
     * @param string $pin
     */
    public function __construct(bool $detached = false, bool $nochain = false, string $pin = '')
    {
        $this->detached = $detached;
        $this->nochain = $nochain;
        $this->pin = $pin;
    }

    /**
     * Получить список подписей
     *
     *
     * @return string|false|null
     */
    public static function getSigns(): string|false|null
    {
        return shell_exec(self::$certmgrExec);
    }

    private static function getCryptcpExec(): string
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
     *
     * @param string $file
     * @param string $thumbprint
     * @param string $toFile
     * @throws Cli
     */
    public function signFile(string $file, string $thumbprint, string $toFile = ''): void
    {
        $shellCommand = self::getCryptcpExec()
            . ' -sign'
            . ($this->detached ? ' -detached' : '')
            . ($this->nochain ? ' -nochain' : '')
            . ' -thumbprint ' . $thumbprint
            . ($this->pin ? ' -pin ' . $this->pin : '')
            . ' ' . $file . ' ' . $toFile;
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
     * @param string $data
     * @param string $thumbprint
     * @return bool|string
     * @throws Cli
     */
    public function signData(string $data, string $thumbprint): bool|string
    {
        $from = tempnam('/tmp', 'cpsign');
        $to = tempnam('/tmp', 'cpsign');
        file_put_contents($from, $data);

        $this->signFile($from, $thumbprint, $to);
        unlink($from);
        $return = file_get_contents($to);
        unlink($to);

        return $return;
    }

    /**
     * Добавить подпись в файл, уже содержащий подпись
     *
     *
     * @param string $file Путь к файлу
     * @param string $thumbprint SHA1 отпечаток, например, bb959544444d8d9e13ca3b8801d5f7a52f91fe97
     * @throws Cli
     */
    public function addSignToFile(string $file, string $thumbprint): void
    {
        $shellCommand = self::getCryptcpExec()
            . ' -addsign'
            . ($this->nochain ? ' -nochain' : '')
            . ' -thumbprint ' . $thumbprint
            . ($this->pin ? ' -pin ' . $this->pin : '')
            . ' ' . $file;
        $result = shell_exec($shellCommand);

        if (strpos($result, "Signed message is created.") <= 0) {
            throw new Cli('В ответе Cryptcp не найдена строка Signed message is created: ' . $result . ' команда ' . $shellCommand);
        }
    }

    /**
     * Проверить, что содержимое файла подписано правильной подписью
     *
     *
     * @param string $fileContent
     * @throws Cli
     * @throws SignatureError
     */
    public function verifyFileContent(string $fileContent): void
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
     * @param string $fileSignContent
     * @param string $fileToBeSignedContent
     * @throws Cli
     * @throws SignatureError
     */
    public function verifyFileContentDetached(string $fileSignContent, string $fileToBeSignedContent): void
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

    private static function getDevNull(): string
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            return 'NUL';
        }
        return '/dev/null';
    }

    const ERROR_CODE_WRONG_SIGN = '0x200001f9';
    const ERROR_CODE_WRONG_CHAIN = '0x20000133';
    const ERROR_CODE_NO_CERTS = '0x2000012d';
    const ERROR_CODE_MULTIPLE_CERTS = '0x2000012e';
    const ERROR_CODE_UNTRUSTED_ROOT = '0x20000131';
    const ERROR_CODE_MESSAGE = [
        self::ERROR_CODE_WRONG_CHAIN    => 'Цепочка сертификатов не проверена',
        self::ERROR_CODE_WRONG_SIGN     => 'Подпись не верна',
        self::ERROR_CODE_NO_CERTS       => 'Сертификаты не найдены',
        self::ERROR_CODE_MULTIPLE_CERTS => 'Более одного сертификата',
        self::ERROR_CODE_UNTRUSTED_ROOT => 'Нет доверия к корневому сертификату',
    ];

    /**
     * Проверить, что файл подписан правильной подписью
     *
     *
     * @param string $file
     * @throws Cli
     * @throws SignatureError
     */
    public function verifyFile(string $file): void
    {
        $shellCommand = 'yes "n" 2> ' . self::getDevNull() . ' | ' . escapeshellarg(self::$cryptcpExec) . ' -verify -verall ' . escapeshellarg($file);
        $result = shell_exec($shellCommand);
        if (!str_contains($result, "[ErrorCode: 0x00000000]") && !str_contains($result, "[ReturnCode: 0]")) {
            preg_match('#\[ErrorCode: (.+)]#', $result, $matches);
            $code = strtolower($matches[1]);
            if (isset(self::ERROR_CODE_MESSAGE[$code])) {
                $message = self::ERROR_CODE_MESSAGE[$code];

                //Дополнительная расшифровка ошибки
                if (str_contains($result, 'The certificate or certificate chain is based on an untrusted root')) {
                    $message .= ' - нет доверия к корневому сертификату УЦ, выпустившего эту подпись.';
                }
                throw new SignatureError($message, $code);
            }
            throw new Cli("Неожиданный результат $shellCommand: \n$result");
        }
    }

    /**
     * Проверить, что файл подписан правильной открепленной подписью
     *
     *
     * @param string $fileSign
     * @param string $fileToBeSigned
     * @param string $fileDir
     * @throws Cli
     * @throws SignatureError
     */
    public function verifyFileDetached(string $fileSign, string $fileToBeSigned, string $fileDir): void
    {
        $shellCommand = 'yes "n" 2> ' . self::getDevNull() . ' | ' . escapeshellarg(self::$cryptcpExec) . ' -vsignf -dir '
            . escapeshellarg($fileDir) . ' '
            . escapeshellarg($fileToBeSigned)
            . ' -f ' . escapeshellarg($fileSign);
        $result = shell_exec($shellCommand);

        if (!str_contains($result, "[ErrorCode: 0x00000000]") && !str_contains($result, "[ReturnCode: 0]")) {
            preg_match('#\[ErrorCode: (.+)]#', $result, $matches);
            $code = strtolower($matches[1]);
            if (isset(self::ERROR_CODE_MESSAGE[$code])) {
                throw new SignatureError(self::ERROR_CODE_MESSAGE[$code], $code);
            }
            throw new Cli("Неожиданный результат $shellCommand: \n$result");
        }
    }
}
