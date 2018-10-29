<?php

header('Content-type: text/css; charset: UTF-8');

$files = glob(__DIR__ . '/src/*.css');
if(false === $files){
    exit;
}

foreach($files as $file){
    readfile($file);
}
exit;
