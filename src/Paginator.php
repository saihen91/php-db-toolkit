<?php
declare(strict_types=1);

namespace DBToolkit;

final class Paginator
{
    public function __construct(private DB $db) {}

    /**
     * Paginate from a base SQL (without LIMIT).
     * Note: base SQL should be a SELECT query.
     *
     * @param array<string,mixed>|array<int,mixed> $params
     * @return array{
     *  data: array<int,array<string,mixed>>,
     *  meta: array{page:int, perPage:int, total:int, totalPages:int, offset:int}
     * }
     */
    public function paginate(string $baseSql, array $params = [], int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(200, $perPage));
        $offset = ($page - 1) * $perPage;

        // Count query wrapper
        $countSql = "SELECT COUNT(*) AS cnt FROM ({$baseSql}) AS _t";
        $total = (int)($this->db->value($countSql, $params) ?? 0);

        $totalPages = (int)max(1, (int)ceil($total / $perPage));

        // Data query
        $dataSql = $baseSql . " LIMIT :__limit OFFSET :__offset";
        $params2 = $params;
        $params2['__limit'] = $perPage;
        $params2['__offset'] = $offset;

        // Need to bind limit/offset as integers -> use statement directly with manual bind
        $stmt = $this->db->pdo()->prepare($dataSql);

        foreach ($params2 as $k => $v) {
            $paramName = is_int($k) ? $k + 1 : (':' . ltrim((string)$k, ':'));
            if ($k === '__limit' || $k === '__offset') {
                $stmt->bindValue($paramName, (int)$v, \PDO::PARAM_INT);
            } else {
                $stmt->bindValue($paramName, $v);
            }
        }

        $t0 = microtime(true);
        $stmt->execute();

        // Update lastQuery info
        // (optional: keep DB's lastQuery consistent)
        // We'll just fetch here; DB already has its own tracking on statement()
        $rows = $stmt->fetchAll();

        $ms = (microtime(true) - $t0) * 1000.0;
        // No direct write-back to DB::lastQuery (kept simple)

        return [
            'data' => $rows,
            'meta' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => $totalPages,
                'offset' => $offset,
            ],
        ];
    }
}
