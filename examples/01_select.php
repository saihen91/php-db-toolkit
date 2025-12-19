<?php
declare(strict_types=1);

$db = require __DIR__ . '/00_bootstrap.php';

$rows = $db->selectAll("SELECT id, name, status FROM users WHERE status = :s", ['s' => 'active']);
print_r($rows);

print_r($db->lastQuery());
