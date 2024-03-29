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
     * @var bool Небезопасный режим - когда цепочка подтверждения подписи не проверяется.
     * Включение даст возможность использовать самоподписанные сертификаты.
     */
    private bool $nochain;

    /**
     * @var string Путь к исполняемому файлу cryptcp КриптоПро
     */
    public string $cryptcpExec = '/opt/cprocsp/bin/amd64/cryptcp';

    /**
     * @var string Путь к исполняемому файлу certmgr КриптоПро
     */
    public string $certmgrExec = '/opt/cprocsp/bin/amd64/certmgr';

    /**
     * @var string Путь к исполняемому файлу curl КриптоПро
     */
    public string $curlExec = '/opt/cprocsp/bin/amd64/curl';

    public function __construct(bool $nochain = false)
    {
        $this->nochain = $nochain;
    }

    /**
     * Возвращает exec в зависимости от ОС
     *
     *
     * @param string $path
     * @return string
     */
    private static function getExec(string $path): string
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            return '"' . $path . '"';
        } else {
            return $path;
        }
    }

    /**
     * Получить список всех подписей
     *
     *
     * @return string|false|null
     */
    public function getSigns(): string|false|null
    {
        return shell_exec(self::getExec($this->certmgrExec) . ' -list -store uMy');
    }

    /**
     * Подписать ранее неподписанный файл
     *
     *
     * @param string $file Путь к подписываемому файлу
     * @param string|array $thumbprint SHA1 hash подписи, либо неассоциативный массив содержащий thumbprint и pin пароль ключевого контейнера
     * @param string $toFile
     * @param bool $detached Создать открепленную подпись
     * @throws Cli
     */
    public function signFile(string $file, string|array $thumbprint, string $toFile = '', bool $detached = false): void
    {
        list($hash, $pin) = is_array($thumbprint) ? $thumbprint : [$thumbprint, ''];
        $shellCommand = self::getExec($this->cryptcpExec)
            . ' -sign'
            . ($detached ? ' -detached' : '')
            . ($this->nochain ? ' -nochain' : '')
            . ' -thumbprint ' . $hash
            . ($pin ? ' -pin ' . $pin : '')
            . ' ' . $file . ' ' . $toFile;
        $result = shell_exec($shellCommand);

        if (strpos($result, "Signed message is created.") <= 0 && strpos($result, "Подписанное сообщение успешно создано") <= 0) {
            throw new Cli('В ответе Cryptcp не найдена строка "Signed message is created" или "Подписанное сообщение успешно создано": ' . $result . ' команда ' . $shellCommand);
        }
    }

    /**
     * Подписать данные
     *
     *
     * @param string $data Строка подписываемых данных
     * @param string|array $thumbprint SHA1 hash подписи, либо неассоциативный массив содержащий thumbprint и pin пароль ключевого контейнера
     * @return string|false
     * @throws Cli
     */
    public function signData(string $data, string|array $thumbprint): string|false
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
     * @param string $file Путь к подписываемому файлу
     * @param string|array $thumbprint SHA1 hash подписи, либо неассоциативный массив содержащий thumbprint и pin пароль ключевого контейнера
     * @throws Cli
     */
    public function addSignToFile(string $file, string|array $thumbprint): void
    {
        list($hash, $pin) = is_array($thumbprint) ? $thumbprint : [$thumbprint, ''];
        $shellCommand = self::getExec($this->cryptcpExec)
            . ' -addsign'
            . ($this->nochain ? ' -nochain' : '')
            . ' -thumbprint ' . $hash
            . ($pin ? ' -pin ' . $pin : '')
            . ' ' . $file;
        $result = shell_exec($shellCommand);

        if (strpos($result, "Signed message is created.") <= 0 && strpos($result, "Подписанное сообщение успешно создано") <= 0) {
            throw new Cli('В ответе Cryptcp не найдена строка "Signed message is created" или "Подписанное сообщение успешно создано": ' . $result . ' команда ' . $shellCommand);
        }
    }

    /**
     * Проверить, что содержимое файла подписано правильной подписью
     *
     *
     * @param string $fileContent
     * @return string|false|null
     * @throws Cli
     * @throws SignatureError
     */
    public function verifyFileContent(string $fileContent): string|false|null
    {
        $file = tempnam(sys_get_temp_dir(), 'cpc');
        file_put_contents($file, $fileContent);
        try {
            $result = $this->verifyFile($file);
        } finally {
            unlink($file);
        }

        return $result;
    }

    /**
     * Проверить, что содержимое файла подписано правильной подписью открепленной подписью
     *
     *
     * @param string $fileToBeSignedContent
     * @param string $fileSignContent
     * @return string|false|null
     * @throws Cli
     * @throws SignatureError
     */
    public function verifyFileContentDetached(string $fileToBeSignedContent, string $fileSignContent): string|false|null
    {
        $fileToBeSigned = tempnam(sys_get_temp_dir(), 'detach');
        $fileSign = $fileToBeSigned . '.sgn';
        file_put_contents($fileToBeSigned, $fileToBeSignedContent);
        file_put_contents($fileSign, $fileSignContent);
        try {
            $result = $this->verifyFileDetached($fileToBeSigned, $fileSign);
        } finally {
            unlink($fileToBeSigned);
            unlink($fileSign);
        }

        return $result;
    }

    /**
     * Проверить, что файл подписан правильной подписью
     *
     *
     * @param string $file
     * @return string|false|null
     * @throws Cli
     * @throws SignatureError
     */
    public function verifyFile(string $file): string|false|null
    {
        return $this->getVerifyShellCommandResult(
            self::getExec($this->cryptcpExec)
            . ' -verify -verall'
            . ($this->nochain ? ' -nochain' : '')
            . ' ' . $file
        );
    }

    /**
     * Проверить, что файл подписан правильной открепленной подписью
     *
     *
     * @param string $fileToBeSigned
     * @param string $fileSign
     * @return string|false|null
     * @throws Cli
     * @throws SignatureError
     */
    public function verifyFileDetached(string $fileToBeSigned, string $fileSign): string|false|null
    {
        return $this->getVerifyShellCommandResult(
            self::getExec($this->cryptcpExec)
            . ' -verify -verall -detached'
            . ($this->nochain ? ' -nochain' : '')
            . ' ' . $fileToBeSigned
            . ' -f ' . $fileSign
        );
    }

    /**
     * Получить результат выполнения консольной команды проверки подписи
     *
     *
     * @param string $shellCommand
     * @return string|false|null
     * @throws Cli
     * @throws SignatureError
     */
    private function getVerifyShellCommandResult(string $shellCommand): string|false|null
    {
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

        return $result;
    }

    /**
     * Curl-запросы с использованием гостовых сертификатов
     *
     *
     * @param string $url
     * @param string|array $thumbprint
     * @param string $method
     * @param array|null $headers
     * @param string|null $data
     * @return string|false|null
     */
    public function proxyCurl(
        string $url,
        string|array $thumbprint,
        string $method = 'GET',
        ?array $headers = null,
        ?string $data = null
    ): string|false|null
    {
        list($hash, $pin) = is_array($thumbprint) ? $thumbprint : [$thumbprint, ''];
        $shellCommand = self::getExec($this->curlExec)
            . ' -k -s -X ' . $method
            . ' ' . $url;

        if ($headers ?? null) {
            foreach ($headers as $header) {
                $shellCommand .= ' --header "' . $header . '"';
            }
        }

        $shellCommand .= ' --cert-type CERT_SHA1_HASH_PROP_ID:CERT_SYSTEM_STORE_CURRENT_USER:My'
            . ' --cert ' . $hash
            . ($pin ? ' --pass ' . $pin : '')
            . ($data ? ' --data \'' . str_replace("'", "'\''", $data) . '\'' : '');

        return shell_exec($shellCommand);
    }
}
