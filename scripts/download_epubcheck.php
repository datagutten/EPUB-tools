<?php
$data = file_get_contents('https://api.github.com/repos/w3c/epubcheck/releases/latest');
var_dump($data);
$releases = json_decode($data, true);
print_r($releases);
