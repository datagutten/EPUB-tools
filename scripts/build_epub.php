<?php


use datagutten\epub\EPUBUtils;

require __DIR__ . '/../vendor/autoload.php';
$file = EPUBUtils::buildEPUB($argv[1]);
EPUBUtils::epubCheck($file);
