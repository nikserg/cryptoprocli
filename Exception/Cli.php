<?php

namespace nikserg\cryptoprocli\Exception;

/**
 * Class Cli
 *
 * Ошибка во время исполнения запроса в командной строке
 *
 *
 * @package nikserg\cryptoprocli\Exception
 */
class Cli extends \Exception
{
    /**
     * Сообщение, из которого удалены символы, ломающие json
     *
     *
     * @return array|string|false
     */
    public function getMessageSafe(): array|string|false
    {
        return mb_convert_encoding($this->getMessage(), 'UTF-8', 'UTF-8');
    }
}
