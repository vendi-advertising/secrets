<?php

require_once dirname(__DIR__) . '/includes/boot.php';

use Vendi\Secrets\User;
use ParagonIE\ConstantTime\Base32;
use ParagonIE\ConstantTime\Encoding;

dump(User::get_or_create()->create_new_token());
