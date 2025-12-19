<?php
declare(strict_types=1);

$db = require __DIR__ . '/00_bootstrap.php';

$id = $db->insert('users', [
  'name' => 'Ali',
  'status' => 'active'
]);

echo "Inserted ID: {$id}\n";
print_r($db->lastQuery());
