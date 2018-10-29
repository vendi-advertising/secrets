<?php

declare(strict_types=1);

namespace Vendi\Secrets\CLI;

use App\Database;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Vendi\YamlSchemaToSql\table_schema_generator;
use Webmozart\PathUtil\Path;

final class DatabaseReset extends Command
{
    private $_io;

    protected function get_or_create_io(InputInterface $input, OutputInterface $output) : SymfonyStyle
    {
        if (!$this->_io) {
            $this->_io = new SymfonyStyle($input, $output);
        }
        return $this->_io;
    }

    protected function configure()
    {
        $this
            ->setName('app:database-reset')
            ->setDescription('Erase the database and re-install')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = $this->get_or_create_io($input, $output);

        $reset = false;

        if ($io->confirm('Are you sure that you want to delete the database?', false)) {
            if ($io->confirm('Seriously, are you really, really sure? This can\'t be undone!! You know that, right?', false)) {
                $reset = true;
            }
        }

        if (!$reset) {
            $io->warning('Ok, I\'m going to leave everything as-is.');
            return;
        }

        $io->text('Database reset in progress...');

        if (!$this->_drop_foreign_keys($io)) {
            return;
        }

        if (!$this->_drop_tables($io)) {
            return;
        }

        if (!$this->_create_tables($io)) {
            return;
        }
    }

    protected function _create_tables(SymfonyStyle $io):bool
    {
        $io->text('Creating database tables...');

        $path = Path::canonicalize(SECRETS_ROOT_DIR . '/.schema/database.yaml');

        if (!is_file($path) || !is_readable($path)) {
            $io->error('Unable to load database.yaml file from config dir.');
            dump($path);
            return false;
        }

        $mysqli = $this->_get_mysqli();

        $gen = new table_schema_generator($path);
        $tables = $gen->get_sql_as_array();
        foreach ($tables as $table_name => $query) {
            $io->text($table_name);

            if (!$mysqli->query($query)) {
                $io->error(sprintf("Query failed with error number: %s\n", $mysqli->error));
                return false;
            }
        }

        $io->success('All tables created successfully');

        return true;
    }

    protected function _drop_tables(SymfonyStyle $io):bool
    {
        $io->text('Dropping tables...');

        $sql = 'show tables;';
        $mysqli = $this->_get_mysqli();
        $result = $mysqli->query($sql);
        $tables = $result->fetch_all(MYSQLI_NUM);

        foreach ($tables as $table) {
            $table_name = reset($table);

            $io->text($table_name);

            $query = sprintf('DROP TABLE `%1$s`;', $table_name);
            if (!$mysqli->query($query)) {
                $io->error(sprintf("Query failed with error number: %s\n", $mysqli->error));
                return false;
            }
        }

        $io->success('All tables dropped successfully');

        return true;
    }

    protected function _drop_foreign_keys(SymfonyStyle $io):bool
    {
        $io->text('Dropping foreign keys...');

        $db_name_with_quotes = "'" . getenv('db_name') . "'";

        $sql = "
                SELECT
                    concat('ALTER TABLE ', TABLE_NAME, ' DROP FOREIGN KEY ', CONSTRAINT_NAME, ';')
                FROM
                    information_schema.key_column_usage
                WHERE
                    CONSTRAINT_SCHEMA = ${db_name_with_quotes}
                AND
                    referenced_table_name IS NOT NULL
                ;
            ";

        $mysqli = $this->_get_mysqli();

        $result = $mysqli->query($sql);

        $foreign_key_drop_queries = $result->fetch_all(MYSQLI_NUM);

        foreach ($foreign_key_drop_queries as $query) {
            //Get the first item from the
            $query = reset($query);
            $io->text($query);
            if (!$mysqli->query($query)) {
                $io->error(sprintf("Query failed with error number: %s\n", $mysqli->error));
                return false;
            }
        }

        $io->success('All foreign feys dropped successfully');

        return true;
    }

    protected function _get_mysqli() : \mysqli
    {
        $mysqli = new \mysqli(getenv('db_host'), getenv('db_user'), getenv('db_pass'), getenv('db_name'));
        if ($mysqli->connect_errno) {
            $io->error(sprintf("Connect failed: %s\n", $mysqli->connect_error));
            exit;
        }

        return $mysqli;
    }
}
