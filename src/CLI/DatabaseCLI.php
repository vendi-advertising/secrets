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

final class DatabaseCLI extends Command
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
            ->setName('app:database-cli')
            ->setDescription('Database CLI')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /*
        This code taken from WP CLI:
        https://github.com/wp-cli/db-command/blob/master/src/DB_Command.php
        https://github.com/wp-cli/wp-cli/blob/master/php/utils.php
        */

        //Backup the old password if it exists
        $old_pass = getenv( 'MYSQL_PWD' );

        //Temporarily put the current password into the global variable
        putenv( 'MYSQL_PWD=' . getenv('db_pass') );

        //Our command
        $cmd = sprintf('/usr/bin/env mysql --user=%1$s --database=%2$s --host=%3$s', getenv('db_user'), getenv('db_name'), getenv('db_host'));

        //Proc/pips
        $descriptors = array( STDIN, STDOUT, STDERR );
        $proc = proc_open( $cmd, $descriptors, $pipes );
        if ( ! $proc ) {
            exit( 1 );
        }
        $r = proc_close( $proc );

        //Reset password if it existed
        putenv( 'MYSQL_PWD=' . $old_pass );
        if ( $r ) {
            exit( $r );
        }
    }
}
