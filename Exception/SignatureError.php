<?php

namespace nikserg\cryptoprocli\Exception;

use Throwable;

/**
 * Ошибка в подписи
 *
 *
 * @package nikserg\cryptoprocli\Exception
 */
class SignatureError extends \Exception
{
    protected string $signatureCode;

    public function __construct(
        string $message = "",
        string $signatureCode = null,
        int $code = 0,
        Throwable $previous = null
    )
    {
        $this->signatureCode = $signatureCode;

        parent::__construct($message, $code, $previous);
    }

    public function getSignatureCode(): ?string
    {
        return $this->signatureCode;
    }
}
