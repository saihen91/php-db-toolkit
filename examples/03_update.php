<?php
declare(strict_types=1);

$db = require __DIR__ . '/00_bootstrap.php';

$affected = $db->update('users',
  ['status' => 'inactive'],
  ['id' => 1]
);

echo "Updated rows: {$affected}\n";
print_r($db->lastQuery());
