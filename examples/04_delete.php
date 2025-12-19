<?php
declare(strict_types=1);

$db = require __DIR__ . '/00_bootstrap.php';

$affected = $db->delete('users', ['id' => 2]);
echo "Deleted rows: {$affected}\n";
print_r($db->lastQuery());
