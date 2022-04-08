<?php

namespace nikserg\cryptoprocli\Exception;

use Throwable;

/**
 * Ошибка в подписи
 *
 * @package nikserg\cryptoprocli\Exception
 */
class SignatureError extends \Exception
{
    protected $signatureCode;
    public function __construct($message = "", $signatureCode = null, $code = 0, Throwable $previous = null)
    {
        $this->signatureCode = $signatureCode;
        parent::__construct($message, $code, $previous);
    }

    public function getSignatureCode()
    {
        return $this->signatureCode;
    }
}
