<?php

$options = array('argument_functions' => array('file_get_contents', 'curl_exec', 'fgets', 'fputs', 'fread', 'fgetcsv', 'stream_get_contents', 'PDOStatement::execute', 'PDO::query'));

xhprof_enable(0, $options);

function fetch($url)
{
    return file_get_contents($url);
}

function fetch_curl($url)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    return curl_exec($ch);
}

fetch('http://qafoo.com');
fetch('http://php.net');
fetch_curl('http://qafoo.com');
fetch_curl('http://php.net');

echo strlen(str_repeat('"', 300));

$fh = fopen(__FILE__, 'r');

while ($line = fgets($fh, 4096)) {
    fputs($fh, "foo");
}
fclose($fh);

$fh = fopen(__FILE__, 'r');
stripos(stream_get_contents($fh), 1);

$pdo = new PDO('sqlite:memory:', 'root', '');

$stmt = $pdo->prepare("SELECT 1");
$stmt->execute();

$stmt = $pdo->query("SELECT 1");
$stmt->execute();

var_dump(xhprof_disable());
