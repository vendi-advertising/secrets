<?php declare(strict_types=1);

namespace Vendi\Secrets;

use ParagonIE\ConstantTime\Base32;
use Vendi\Shared\utils;

final class Session
{
    public const COOKIE_NAME = 'vendi-secret-cookie';

    public $id;
    public $totp_secret;
    public $totp_hash_algo;
    public $totp_digit_count;
    public $date_time_created;
    public $date_time_modified;
    public $remote_ip;

    public static function get_or_create() : self
    {
    }

    public static function _get_existing() : ?self
    {
        $cookie = utils::get_cookie_value(self::COOKIE_NAME);
        if (!$cookie) {
            return null;
        }
    }

    public static function _create_new() : self
    {
        $session = new self();
        $session->id = $this->_create_new_id();
        $session->totp_secret = trim(Base32::encodeUpper(\random_bytes(128)), '=');

        $session->totp_hash_algo = getenv('totp_hash_algo');
        $session->totp_digit_count = getenv('totp_digit_count');
        $session->remote_ip = utils::get_server_value('REMOTE_ADDR');
    }

    public static function _create_new_id() : string
    {
        $length = 128;
        return bin2hex(random_bytes(($length-($length%2))/2));
    }
}
