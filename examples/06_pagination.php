<?php
declare(strict_types=1);

$db = require __DIR__ . '/00_bootstrap.php';

use DBToolkit\Paginator;

$pager = new Paginator($db);

$page = 1;
$perPage = 5;

$res = $pager->paginate(
  "SELECT id, name, status FROM users WHERE status = :s ORDER BY id DESC",
  ['s' => 'active'],
  $page,
  $perPage
);

print_r($res['meta']);
print_r($res['data']);
