<?php
// execute with `php -d phar.readonly=0 -f make.php`
//do not move the make script, because of our use of __DIR__
$phar = new Phar(__DIR__ . '/src/KafkaPhp.phar');
$phar->addFile(__DIR__ . '/dev/KafkaPhp.php');
