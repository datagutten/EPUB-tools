<?php

use datagutten\epub\OPF;

require __DIR__.'/../vendor/autoload.php';

$opf = new OPF($argv[1], dirname($argv[1]));
$opf->strip_missing_files();
$opf->saveFile($argv[1]);