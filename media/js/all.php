<?php

header('Content-type: application/javascript;');

$files = glob(__DIR__ . '/src/*.js');
if(false === $files){
    exit;
}

foreach($files as $file){
    readfile($file);
}
exit;
