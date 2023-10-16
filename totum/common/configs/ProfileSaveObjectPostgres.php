<?php

namespace totum\common\configs;

use totum\common\FormatParamsForSelectFromTable;

class ProfileSaveObjectPostgres implements ProfilerSaveObjectInterface
{
    const table = '_profiler';

    public function __construct(protected \PDO $PDO)
    {

    }

    public function insert(array $data)
    {
        $hash = bin2hex(random_bytes(10));

        try {
            if ($st = $this->PDO->prepare('insert into ' . static::table . ' (data, path, start, hash) values (?,?,?,?)')) {
                $st->execute([$this->getClearedPreparedData($data), $data['path'], $data['start'], $hash]);
            }
        } catch (\PDOException $exception) {
            if ($exception->getCode() === '42P01') {
                $table = static::table;
                $this->PDO->exec(
                    <<<SQL
CREATE TABLE $table (
   hash     text                                                      not null,
   path     text                                                      not null,
   start     decimal                                                      not null,
   data     jsonb not null
)
SQL
                );
                if ($st = $this->PDO->prepare('insert into ' . static::table . ' (data, path, start, hash) values (?,?,?,?)')) {
                    $st->execute([$this->getClearedPreparedData($data), $data['path'], $data['start'], $hash]);
                }
            }
        }
        return $hash;
    }

    public function update(array $data, $hash)
    {
        try {
            if ($st = $this->PDO->prepare("update " . static::table . " set data=? where path=? AND start = ? AND hash = ?")) {
                $st->execute([$this->getClearedPreparedData($data), $data['path'], $data['start'], $hash]);
            }
        } catch (\Exception) {
        }
    }

    protected function getClearedPreparedData($data)
    {
        unset($data['path']);
        unset($data['start']);
        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    public function get(FormatParamsForSelectFromTable $where, int $limit): array
    {

        list($query, $params) = $this->getQueryFromWhere($where);

        $order = '';

        foreach ($where->params()['order'] ?? [] as $_w) {
            if (!empty($order)) {
                $order .= ',';
            }
            if (in_array($_w['field'], ['path', 'start', 'hash'])) {
                $order .= "{$_w['field']} {$_w['ad']}";
            } else {
                $order .= "data->'{$_w['field']}' {$_w['ad']}";
            }
        }
        if (empty($order)) {
            $order = 'start desc';
        }

        if ($st = $this->PDO->prepare("select * from " . static::table . " where $query order by $order limit $limit")) {
            $st->execute($params);
        }
        $data = [];
        foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $_data = json_decode($row['data'], true);
            unset($row['data']);
            $data[] = array_merge($row, $_data);
        }
        return $data;
    }

    protected function getQueryFromWhere(FormatParamsForSelectFromTable $where): array
    {
        $query = '';
        $params = [];
        foreach ($where->params()['where'] as $_w) {
            if ($_w['value'] === '*ALL*') {
                continue;
            }
            if ($query) {
                $query .= ' AND ';
            }

            if (in_array($_w['field'], ['hash', 'path', 'start'])) {
                $query .= $_w['field'] . $_w['operator'] . '?';
                if ($_w['field'] === 'start') {
                    $d = date_create($_w['value']);
                    $_w['value'] = date_timestamp_get($d);
                }
                $params[] = $_w['value'];
            } else {
                if ($_w['value'] === '' || is_null($_w['value'])) {
                    if ($_w['operator'] === '!=') {
                        $query .= 'data->\'' . $_w['field'] . '\' is not null';
                    } else {
                        $query .= 'data->\'' . $_w['field'] . '\' is null';
                    }
                } else {
                    if (is_array($_w['value'])) {
                        if (!empty($_w['value'])) {
                            $_q = '';
                            foreach ($_w['value'] as $_v) {
                                if (!empty($_q)) {
                                    $_q .= ' OR ';
                                }
                                $_q .= 'data->\'' . $_w['field'] . '\' ' . $_w['operator'] . '?';
                                $params[] = $_v;
                            }
                            $query .= "($_q)";
                        } else {
                            $query = "FALSE";
                        }
                    } else {
                        $query .= 'data->\'' . $_w['field'] . '\' ' . $_w['operator'] . '?';
                        $params[] = $_w['value'];
                    }
                }
            }
        }
        if (empty($query)) {
            $query = "TRUE";
        }

        return [$query, $params];
    }

    public function clear(FormatParamsForSelectFromTable $where)
    {
        list($query, $params) = $this->getQueryFromWhere($where);

        if ($st = $this->PDO->prepare("delete from " . static::table . " where $query")) {
            $st->execute($params);
        }
        $this->PDO->exec("vacuum " . static::table);
    }

}