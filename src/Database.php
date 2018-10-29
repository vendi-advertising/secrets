<?php declare(strict_types=1);

namespace Vendi\Secrets;

use PDO;

final class Database
{
    private static $_link;

    public static function get_connection() : PDO
    {
        if (!self::$_link instanceof PDO) {
            $db_host = getenv('db_host');
            $db_name = getenv('db_name');
            $dsn = "mysql:dbname={$db_name};host={$db_host}";
            $pdo = new PDO($dsn, getenv('db_user'), getenv('db_pass'));
            $pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, ["EPDOStatement\EPDOStatement", [$pdo]]);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            self::$_link = $pdo;
        }

        return self::$_link;
    }
}
