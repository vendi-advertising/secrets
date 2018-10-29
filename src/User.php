<?php

declare(strict_types=1);

namespace Vendi\Secrets;

use OTPHP\TOTP;
use ParagonIE\ConstantTime\Base32;
use ParagonIE\ConstantTime\Encoding;
use ParagonIE\Cookie\Cookie;
use PDO;
use Vendi\Shared\utils;

/**
 * @see  https://evertpot.com/storing-encrypted-session-information-in-a-cookie/
 */
final class User
{
    public const COOKIE_NAME = 'vendi-secret-user';
    public const TIMEOUT = 600;
    public const COOKIE_DELIM = ':';
    public const SIGN_DELIM = ':';
    public const OBJECT_CHANNEL_NAME = 'user';

    public $user_id;

    public $user_remote_ip;
    public $user_remote_ua;

    private $user_secret;

    private $totp_secret;
    private $totp_hash_algo;
    private $totp_digit_count;

    public $totp_current_token;

    /**
     * Create a new user object, optionally loading values from the supplied object.
     * @param object $obj_to_clone_from PDO Object or User with properties to load from
     */
    public function __construct($obj_to_clone_from = null)
    {
        if (is_object($obj_to_clone_from)) {

            //Copy known properties
            $props = [
                        'user_id',
                        'user_remote_ip',
                        'user_remote_ua',
                        'user_secret',
                        'totp_secret',
                        'totp_hash_algo',
                        'totp_digit_count',
            ];
            foreach ($props as $prop) {

                //This is a bunch of extra sanity checking, mostly while this project is being built to guard
                //against refactoring
                if (!property_exists($obj_to_clone_from, $prop)) {
                    Logger::get_channel(self::OBJECT_CHANNEL_NAME)->error('Supplied temporary user is missing property', ['property'=>$prop]);
                    throw new \Exception('Supplied temporary user is missing property: ' . $prop);
                }

                if (!property_exists($this, $prop)) {
                    Logger::get_channel(self::OBJECT_CHANNEL_NAME)->error('User object is missing property', ['property'=>$prop]);
                    throw new \Exception('User object is missing property: ' . $prop);
                }

                if ('totp_digit_count' === $prop) {
                    $this->$prop = (int) $obj_to_clone_from->$prop;
                } else {
                    $this->$prop = $obj_to_clone_from->$prop;
                }
            }
        } else {

            //Standard new user
            $this->set_new_random_user_id();
            $this->set_new_user_secret();

            $this->user_remote_ip = utils::get_server_value('REMOTE_ADDR');
            $this->user_remote_ua = utils::get_server_value('HTTP_USER_AGENT');


            $this->set_new_totp_secret();
            $this->totp_hash_algo = getenv('totp_hash_algo');
            $this->totp_digit_count = (int) getenv('totp_digit_count');
        }
    }

    /**
     * Reset the TOTP secret for the current user.
     *
     * Because the TOTP generates the same token during a given period, if we have
     * a database collision we need to reset the TOTP secret for the given user
     * in order to get a different value. Secrets should be purged from the database
     * once a successful session is created so hopefully this doesn't happen too often.
     *
     * @return [type] [description]
     */
    public function reset_totp_secret_for_user()
    {
        Logger::get_channel(self::OBJECT_CHANNEL_NAME)->debug('Resetting TOTP secret for user');
        $this->set_new_totp_secret();
        $db = Database::get_connection();
        $sql = 'UPDATE users SET totp_secret = :totp_secret WHERE user_id = :user_id';
        $statement = $db->prepare($sql);
        $statement->bindValue(':totp_secret', $this->totp_secret);
        $statement->bindValue(':user_id', $this->user_id);
        if (!$statement->execute()) {
            Logger::get_channel(self::OBJECT_CHANNEL_NAME)->error('Could not insert new TOTP secret into database', ['totp_secret' => $this->totp_secret, 'user_id' => $this->user_id, 'database_error' => $statement->errorInfo()]);
            throw new \Exception('Could not insert new TOTP secret into database');
        }

        Logger::get_channel(self::OBJECT_CHANNEL_NAME)->debug('TOTP secret for user reset');
    }

    public function does_token_already_exist(string $token) : bool
    {
        Logger::get_channel(self::OBJECT_CHANNEL_NAME)->debug('Checking if token already exists');
        $db = Database::get_connection();
        $sql = 'SELECT COUNT(*) FROM users WHERE totp_current_token = :totp_current_token';
        $statement = $db->prepare($sql);
        $statement->bindValue(':totp_current_token', $token);
        $statement->execute();

        if (!$statement->execute()) {
            Logger::get_channel(self::OBJECT_CHANNEL_NAME)->error('Could not check database to determine if token already exists', ['token' => $token, 'database_error' => $statement->errorInfo()]);
            throw new \Exception('Could not check database to determine if token already exists');
        }

        $count = $statement->fetchColumn();
        if (false === $count) {
            Logger::get_channel(self::OBJECT_CHANNEL_NAME)->error('Could not determine is token is currently in use', ['token' => $token, 'database_error' => $statement->errorInfo()]);
            throw new \Exception('Could not determine is token is currently in use');
        }

        if ((int) $count !== 0) {
            Logger::get_channel(self::OBJECT_CHANNEL_NAME)->debug('Token already exists', ['token' => $token]);
            return true;
        }

        Logger::get_channel(self::OBJECT_CHANNEL_NAME)->debug('Token does not exist, safe to use');
        return false;
    }

    public function create_new_token() : string
    {
        Logger::get_channel(self::OBJECT_CHANNEL_NAME)->debug('Beginning new token generation');
        $token = null;
        $totp_period_in_seconds = (int) getenv('totp_period_in_seconds');
        do {
            Logger::get_channel(self::OBJECT_CHANNEL_NAME)->debug('Generating new token');
            $this->reset_totp_secret_for_user();
            $totp = TOTP::create(
                $this->totp_secret,
                $totp_period_in_seconds,
                $this->totp_hash_algo,
                $this->totp_digit_count
            );
            $token = $totp->now();
        } while ($this->does_token_already_exist($token));

        $db = Database::get_connection();
        $sql = 'UPDATE users SET totp_current_token = :totp_current_token WHERE user_id = :user_id';
        $statement = $db->prepare($sql);
        $statement->bindValue(':totp_current_token', $token);
        $statement->bindValue(':user_id', $this->user_id);
        $statement->execute();

        Logger::get_channel(self::OBJECT_CHANNEL_NAME)->debug('New token generated', ['token' => $token]);

        $token = str_pad($token, $this->totp_digit_count, '0', STR_PAD_LEFT);

        return $token;
    }

    public static function get_or_create(bool $drop_cookie = true) : self
    {
        $user = self::_get_existing();
        if (!$user) {
            $user = self::_create_new();
        }

        if ($drop_cookie) {
            $time = time();

            Cookie::setcookie(self::COOKIE_NAME, $user->generate_user_cookie_string(), $time + self::TIMEOUT);
        }

        return $user;
    }

    public static function _get_existing() : ?self
    {
        $cookie = utils::get_cookie_value(self::COOKIE_NAME);
        if (!$cookie) {
            Logger::get_channel(self::OBJECT_CHANNEL_NAME)->debug('User cookie not found');
            return null;
        }

        $parts = explode(self::COOKIE_DELIM, $cookie);
        if (3 !== count($parts)) {
            Logger::get_channel(self::OBJECT_CHANNEL_NAME)->error('User cookie format invalid', ['cookie'=>$cookie]);
            return null;
        }

        list($user_id, $time, $signature) = $parts;
        if (!ctype_digit($time)) {
            Logger::get_channel(self::OBJECT_CHANNEL_NAME)->error('User cookie time invalid', ['cookie'=>$cookie]);
            return null;
        }

        $time = (int) $time;

        if ($time > time() + self::TIMEOUT) {
            Logger::get_channel(self::OBJECT_CHANNEL_NAME)->debug('User cookie expired');
            return null;
        }

        Logger::get_channel(self::OBJECT_CHANNEL_NAME)->debug('Valid cookie found, testing database');

        //Now we need to go to the database
        $db = Database::get_connection();
        $sql = 'SELECT * FROM users WHERE user_id = :user_id';
        $statement = $db->prepare($sql);
        $statement->bindValue(':user_id', $user_id);
        $statement->execute();
        $tmp_user = $statement->fetch(PDO::FETCH_OBJ);

        if (false === $tmp_user) {
            // echo '<pre>' . $statement->interpolateQuery() . '</pre>';
            Logger::get_channel(self::OBJECT_CHANNEL_NAME)->error('User by cookie not found in database', ['cookie'=>$cookie]);
            return null;
        }

        Logger::get_channel(self::OBJECT_CHANNEL_NAME)->debug('User found in database by cookie');

        $obj = new self($tmp_user);

        //Timing-safe comparison of hash.
        //NOTE: The PHP docs say that it is important for the user-supplied
        //signature to be the second parameter, although they don't say why.
        if (!\hash_equals($obj->generate_signature($time), $signature)) {
            Logger::get_channel(self::OBJECT_CHANNEL_NAME)->error('User cookie signature invalid', ['cookie'=>$cookie]);
        }

        Logger::get_channel(self::OBJECT_CHANNEL_NAME)->debug('User cookie signature valid');

        return $obj;
    }

    /**
     * Create a new User object.
     * @return [type] [description]
     */
    public static function _create_new() : self
    {
        $obj = new self();

        $db = Database::get_connection();
        $sql = 'INSERT INTO users (user_id, user_remote_ip, user_remote_ua, user_secret, totp_secret, totp_hash_algo, totp_digit_count) VALUES (:user_id, :user_remote_ip, :user_remote_ua, :user_secret, :totp_secret, :totp_hash_algo, :totp_digit_count)';
        $statement = $db->prepare($sql);
        $statement->bindValue(':user_id', $obj->user_id);
        $statement->bindValue(':user_remote_ip', $obj->user_remote_ip);
        $statement->bindValue(':user_remote_ua', $obj->user_remote_ua);
        $statement->bindValue(':user_secret', $obj->user_secret);
        $statement->bindValue(':totp_secret', $obj->totp_secret);
        $statement->bindValue(':totp_hash_algo', $obj->totp_hash_algo);
        $statement->bindValue(':totp_digit_count', $obj->totp_digit_count);

        if (!$statement->execute()) {
            Logger::get_channel(self::OBJECT_CHANNEL_NAME)->error('Could not add new user to database', ['database_error' => $statement->errorInfo()]);
            throw new \Exception('Could not add user to database');
        }
        Logger::get_channel(self::OBJECT_CHANNEL_NAME)->debug('New user created', ['id' => $obj->user_id]);
        return $obj;
    }

    /**
     * Create and set a new user secret on the current user.
     */
    public function set_new_user_secret()
    {
        $this->user_secret = self::generate_new_secret((int) getenv('user_secret_bytes'));
    }

    /**
     * Create and set a new TOTP secret on the current user.
     */
    public function set_new_totp_secret()
    {
        $this->totp_secret = self::generate_new_secret((int) getenv('totp_secret_bytes'));
    }

    /**
     * Create a new generic secret with the supplied length as a byte count.
     * @param int $length The number of bytes for the secret
     */
    public static function generate_new_secret(int $length) : string
    {
        return trim(Base32::encodeUpper(\random_bytes($length)), '=');
    }

    /**
     * Create a new user ID.
     */
    public function set_new_random_user_id()
    {
        $length = (int) getenv('user_id_bytes');
        $this->user_id = Encoding::hexEncodeUpper(random_bytes(($length-($length%2))/2));
    }

    /**
     * Generate a signature based on user-specific details and a timestamp.
     * @param int $time The current system time
     */
    public function generate_signature(int $time) : string
    {
        //The parts that we want to sign
        $parts = [
                   $this->user_id,
                   $time,
                   $this->user_remote_ua,
                   $this->user_remote_ip,
        ];

        //Turn into giant string
        $string_to_sign = implode(self::SIGN_DELIM, $parts);

        //Hash with an HMAC
        return \hash_hmac(getenv('signature_algo'), $string_to_sign, $this->user_secret);
    }

    /**
     * Generate the signed user cookie string with the user's id and expiration
     * time.
     */
    public function generate_user_cookie_string() : string
    {
        //We store the generation time in the cookie itself. Since the cookie
        //is signed this is safe from tampering.
        $time = time();

        //Three parts, ID, current time and a signature that's unqiue(-ish)
        //to the client. See generate_signature().
        $parts = [
                    $this->user_id,
                    $time,
                    $this->generate_signature($time),
        ];

        //Turn into giant string
        return implode(self::COOKIE_DELIM, $parts);
    }
}
