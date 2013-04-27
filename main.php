<?php
if ( ! PHP_SAPI === 'cli'){
    print "Sorry, you should run this from CLI\n";
}
if ( ! isset($argv[1]) ||
        ! $keyword = htmlspecialchars(trim($argv[1]))){
    exit("Usage: {$argv[0]} keyword\n");
}

include 'request.php';
include 'rozetka.php';
include 'model.php';

function output($text){
    print "{$text}\n";
}

$newsearch = new Rozetka();
$result = $newsearch->parse($keyword);