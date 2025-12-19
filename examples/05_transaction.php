<?php
declare(strict_types=1);

$db = require __DIR__ . '/00_bootstrap.php';

try {
  $result = $db->transaction(function($db) {
    $id1 = $db->insert('users', ['name' => 'Tran A', 'status' => 'active']);
    $id2 = $db->insert('users', ['name' => 'Tran B', 'status' => 'active']);
    return [$id1, $id2];
  });

  echo "Committed IDs: " . implode(', ', $result) . "\n";
} catch (Throwable $e) {
  echo "Rolled back: " . $e->getMessage() . "\n";
}
