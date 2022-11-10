<?php

namespace totum\common\calculates;

use totum\common\Crypt;
use totum\common\errorException;
use totum\common\Model;

trait FuncDbTrait
{
    static string $dbHashTypeName = 'DBPRO';

    protected function funcProDbConnect($params)
    {
        $params = $this->getParamsArray($params);
        $this->__checkNotEmptyParams($params, ['name']);
        $this->__checkNotArrayParams($params, ['name']);

        return $this->Table->getTotum()->hashValue(static::$dbHashTypeName,
            null,
            $this->__ProDbConnect($params['name']));
    }

    protected function __ProDbConnect($name): \PDO
    {
        $DbRow = $this->Table->getTotum()->getModel('ttm__external_databases')->get(['name' => $name]);

        if (empty($DbRow)) {
            throw new errorException($this->translate('DB connection by name %s was not found.', $name));
        }
        $DbRow = Model::getClearValuesWithExtract($DbRow);

        try {
            $PDO = new \PDO($DbRow['type'] . ':host=' . $DbRow['host'] . ';port=' . $DbRow['port'] . ';dbname=' . $DbRow['database_name'],
                $DbRow['username'],
                Crypt::getDeCrypted($DbRow['user_pass'],
                    $this->Table->getTotum()->getConfig()->getCryptKeyFileContent()), $DbRow['options'] ?? []);
        } catch (\Exception $e) {
            throw new errorException($e->getMessage());
        }
        return $PDO;
    }

    protected function funcProDbDisconnect($params)
    {
        $params = $this->getParamsArray($params);
        $this->__checkNotEmptyParams($params, ['hash']);
        $this->__checkNotArrayParams($params, ['hash']);


        $this->Table->getTotum()->hashValue(static::$dbHashTypeName, $params['hash'], null);
    }

    protected function funcProDbExecQuery($params)
    {
        $params = $this->getParamsArray($params);
        $stmt = $this->__ProDbPDO($params);

        return $stmt->rowCount();
    }

    protected function __ProDbPDO($params)
    {
        $this->__checkNotEmptyParams($params, ['query']);
        $this->__checkNotArrayParams($params, ['hash', 'name', 'query']);

        if (!empty($params['hash'])) {
            $PDO = $this->Table->getTotum()->hashValue(static::$dbHashTypeName, $params['hash']);
            if (empty($PDO)) {
                throw new errorException($this->translate('DB connection by hash %s was not found.', $params['hash']));
            }
        } else {
            $this->__checkNotEmptyParams($params, ['name']);
            $PDO = $this->__ProDbConnect($params['name']);
        }

        if (!empty($params['params'])) {
            $this->__checkListParam($params['params'], 'params');
        }
        $stmt = $PDO->prepare($params['query']);

        if (!$stmt) {
            throw new errorException($PDO->errorInfo());
        }

        try {
            $r = $stmt->execute($params['params'] ?? null);
            if (!$r || $stmt->errorCode() !== "00000") {
                throw new errorException($stmt->errorInfo());
            }
        } catch (\PDOException $exception) {
            throw new errorException($exception->getMessage());
        }
        return $stmt;
    }

    protected function funcProDbSelect($params)
    {
        $params = $this->getParamsArray($params);
        $stmt = $this->__ProDbPDO($params);

        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    protected function funcProDbSelectList($params)
    {
        $params = $this->getParamsArray($params);
        $stmt = $this->__ProDbPDO($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

}