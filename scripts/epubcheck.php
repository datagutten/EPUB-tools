<?php

use datagutten\epub\EPUBCheck;

require __DIR__ . '/../vendor/autoload.php';
EPUBCheck::check($argv[1]);