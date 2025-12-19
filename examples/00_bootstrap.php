<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use DBToolkit\DB;

$db = new DB([
  'host' => '127.0.0.1',
  'port' => 3306,
  'dbname' => 'test',
  'user' => 'root',
  'pass' => '',
  'debug' => true
]);

return $db;
