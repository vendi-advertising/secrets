<?php

define('SECRETS_ROOT_DIR', dirname(__DIR__));

require_once SECRETS_ROOT_DIR . '/includes/autoload.php';

// dump($_SERVER);

$dotenv = new \Symfony\Component\Dotenv\Dotenv();

$dotenv->load(
                \Webmozart\PathUtil\Path::join(SECRETS_ROOT_DIR, '.config', '.env.dev')
            );
