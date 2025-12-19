<?php
declare(strict_types=1);

namespace DBToolkit;

use PDO;
use PDOStatement;
use Throwable;

final class DB
{
    private PDO $pdo;
    private bool $debug = false;

    private ?string $lastSql = null;
    private array $lastParams = [];
    private float $lastMs = 0.0;
    private int $lastRowCount = 0;

    /**
     * @param array{
     *  host?:string, port?:int, dbname?:string, charset?:string,
     *  user?:string, pass?:string,
     *  dsn?:string,
     *  options?:array<int,mixed>,
     *  debug?:bool
     * } $config
     */
    public function __construct(array $config)
    {
        $this->debug = (bool)($config['debug'] ?? false);

        $options = $config['options'] ?? [];
        $options += [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $dsn = $config['dsn'] ?? null;
        if (!$dsn) {
            $host = $config['host'] ?? '127.0.0.1';
            $port = (int)($config['port'] ?? 3306);
            $dbname = $config['dbname'] ?? '';
            $charset = $config['charset'] ?? 'utf8mb4';
            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";
        }

        $user = $config['user'] ?? '';
        $pass = $config['pass'] ?? '';
        $this->pdo = new PDO($dsn, $user, $pass, $options);
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function setDebug(bool $debug): void
    {
        $this->debug = $debug;
    }

    public function isDebug(): bool
    {
        return $this->debug;
    }

    /** @return array{sql:?string, params:array, ms:float, rowCount:int} */
    public function lastQuery(): array
    {
        return [
            'sql' => $this->lastSql,
            'params' => $this->lastParams,
            'ms' => $this->lastMs,
            'rowCount' => $this->lastRowCount,
        ];
    }

    /**
     * Prepare + execute statement and return PDOStatement.
     * @param array<string,mixed>|array<int,mixed> $params
     */
    public function statement(string $sql, array $params = []): PDOStatement
    {
        $t0 = microtime(true);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $this->lastSql = $sql;
        $this->lastParams = $params;
        $this->lastMs = (microtime(true) - $t0) * 1000.0;
        $this->lastRowCount = $stmt->rowCount();

        return $stmt;
    }

    /**
     * Execute and return success boolean.
     * @param array<string,mixed>|array<int,mixed> $params
     */
    public function execute(string $sql, array $params = []): bool
    {
        $stmt = $this->statement($sql, $params);
        return $stmt->rowCount() >= 0;
    }

    /**
     * Select multiple rows.
     * @param array<string,mixed>|array<int,mixed> $params
     * @return array<int,array<string,mixed>>
     */
    public function selectAll(string $sql, array $params = []): array
    {
        return $this->statement($sql, $params)->fetchAll();
    }

    /**
     * Select single row.
     * @param array<string,mixed>|array<int,mixed> $params
     * @return array<string,mixed>|null
     */
    public function selectOne(string $sql, array $params = []): ?array
    {
        $row = $this->statement($sql, $params)->fetch();
        return $row !== false ? $row : null;
    }

    /**
     * Get single scalar value (first column of first row).
     * @param array<string,mixed>|array<int,mixed> $params
     */
    public function value(string $sql, array $params = []): mixed
    {
        $val = $this->statement($sql, $params)->fetchColumn(0);
        return $val === false ? null : $val;
    }

    /**
     * Insert helper (simple).
     * @param array<string,mixed> $data
     * @return int lastInsertId (0 if not available)
     */
    public function insert(string $table, array $data): int
    {
        $this->assertIdentifier($table);

        if (empty($data)) {
            throw new \InvalidArgumentException('Insert data cannot be empty.');
        }

        $cols = array_keys($data);
        foreach ($cols as $c) $this->assertIdentifier($c);

        $colSql = implode(', ', array_map([$this, 'quoteIdent'], $cols));
        $phSql  = implode(', ', array_map(fn($c) => ':' . $c, $cols));

        $sql = "INSERT INTO {$this->quoteIdent($table)} ({$colSql}) VALUES ({$phSql})";
        $this->statement($sql, $data);

        $id = (string)$this->pdo->lastInsertId();
        return ctype_digit($id) ? (int)$id : 0;
    }

    /**
     * Update helper.
     * @param array<string,mixed> $data
     * @param array<string,mixed> $where (AND combined)
     * @return int affected rows
     */
    public function update(string $table, array $data, array $where): int
    {
        $this->assertIdentifier($table);

        if (empty($data)) {
            throw new \InvalidArgumentException('Update data cannot be empty.');
        }
        if (empty($where)) {
            throw new \InvalidArgumentException('Update where cannot be empty (safety).');
        }

        foreach (array_keys($data) as $c) $this->assertIdentifier($c);
        foreach (array_keys($where) as $c) $this->assertIdentifier($c);

        $setParts = [];
        $params = [];

        foreach ($data as $col => $val) {
            $setParts[] = $this->quoteIdent($col) . " = :set_" . $col;
            $params["set_" . $col] = $val;
        }

        $whereParts = [];
        foreach ($where as $col => $val) {
            $whereParts[] = $this->quoteIdent($col) . " = :w_" . $col;
            $params["w_" . $col] = $val;
        }

        $sql = "UPDATE {$this->quoteIdent($table)} SET " . implode(', ', $setParts)
             . " WHERE " . implode(' AND ', $whereParts);

        $stmt = $this->statement($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Delete helper.
     * @param array<string,mixed> $where (AND combined)
     * @return int affected rows
     */
    public function delete(string $table, array $where): int
    {
        $this->assertIdentifier($table);

        if (empty($where)) {
            throw new \InvalidArgumentException('Delete where cannot be empty (safety).');
        }

        foreach (array_keys($where) as $c) $this->assertIdentifier($c);

        $params = [];
        $whereParts = [];
        foreach ($where as $col => $val) {
            $whereParts[] = $this->quoteIdent($col) . " = :w_" . $col;
            $params["w_" . $col] = $val;
        }

        $sql = "DELETE FROM {$this->quoteIdent($table)} WHERE " . implode(' AND ', $whereParts);
        $stmt = $this->statement($sql, $params);

        return $stmt->rowCount();
    }

    /**
     * Run callback in a transaction.
     * Rolls back on exception and rethrows.
     * @template T
     * @param callable(self):T $fn
     * @return T
     */
    public function transaction(callable $fn): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $fn($this);
            $this->pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /** Identifier safety: allow letters, numbers, underscore only */
    private function assertIdentifier(string $ident): void
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $ident)) {
            throw new \InvalidArgumentException("Invalid identifier: {$ident}");
        }
    }

    private function quoteIdent(string $ident): string
    {
        // MySQL uses backticks
        $this->assertIdentifier($ident);
        return "`{$ident}`";
    }
}
