<?php declare(strict_types=1);

namespace Vendi\Secrets;

use Monolog\Handler\StreamHandler;
use Monolog\Logger as MonoLogger;
use Webmozart\PathUtil\Path;

final class Logger
{
    public static $_channels = [];

    public static function get_channel(string $name) : MonoLogger
    {
        if (!array_key_exists($name, self::$_channels)) {
            self::maybe_create_log_dir();
            $log_dir_abs = self::get_log_dir_abs();
            $log = new MonoLogger($name);
            $log->pushHandler(new StreamHandler(Path::join($log_dir_abs, 'debug.log'), MonoLogger::DEBUG));
            $log->pushHandler(new StreamHandler(Path::join($log_dir_abs, 'warning.log'), MonoLogger::WARNING));
            $log->pushHandler(new StreamHandler(Path::join($log_dir_abs, 'error.log'), MonoLogger::ERROR));
            self::$_channels[$name] = $log;
        }

        return self::$_channels[$name];
    }

    public static function maybe_create_log_dir()
    {
        $log_dir_abs = self::get_log_dir_abs();
        if (! \is_dir($log_dir_abs)) {
            $umask = \umask(0);
            $status = @\mkdir($log_dir_abs);
            \umask($umask);
            if (! \is_dir($log_dir_abs)) {
                throw new \Exception('Could not create directory for logging');
            }
        }
    }

    public static function get_log_dir_abs() : string
    {
        return Path::join(SECRETS_ROOT_DIR, '.logs');
    }
}
