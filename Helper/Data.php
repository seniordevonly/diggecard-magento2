<?php
/**
 * @author DiggEcard Team
 * @copyright Copyright (c) 2019 DiggEcard (https://diggecard.com)
 */

namespace Diggecard\Giftcard\Helper;

use InvalidArgumentException;

/**
 * Class Data
 *
 * @package Diggecard\Giftcard\Helper
 */
class Data
{
    /**
     * @inheritDoc
     * @since 101.0.0
     */
    public function serialize($data)
    {
        $result = json_encode($data);
        if (false === $result) {
            throw new InvalidArgumentException(__("Unable to serialize value. Error: ") . json_last_error_msg());
        }
        return $result;
    }

    /**
     * @inheritDoc
     * @since 101.0.0
     */
    public function unserialize($string)
    {
        $result = json_decode($string, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException(__("Unable to unserialize value. Error: ") . json_last_error_msg());
        }
        return $result;
    }
}