<?php

namespace nikserg\cryptoprocli\Exception;

/**
 * Class Cli
 *
 * Ошибка во время исполнения запроса в командной строке
 *
 * @package nikserg\cryptoprocli\Exception
 */
class Cli extends \Exception
{
    /**
     * Сообщение, из которого удалены символы, ломающие json
     *
     *
     * @return mixed|string
     */
    public function getMessageSafe()
    {
        $message = $this->getMessage();
        return mb_convert_encoding($message, 'UTF-8', 'UTF-8');
    }
}
