<?php

use datagutten\epub\EPUBUtils;

require __DIR__.'/../vendor/autoload.php';
EPUBUtils::epubCheck($argv[1]);