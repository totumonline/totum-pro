<?php


namespace totum\models;

use totum\common\errorException;
use totum\common\Lang\RU;
use totum\common\Model;

class CalcsTablesVersions extends Model
{
    protected $cacheDefVersions = [];

    public function getDefaultVersion($tableName, $withDefaultOrd = false)
    {
        if (!key_exists($tableName, $this->cacheDefVersions)) {
            $this->cacheDefVersions[$tableName] = $this->executePrepared(
                true,
                ['table_name' => $tableName, 'is_default' => "true"],
                'version, default_ord, default_auto_recalc'
            )->fetch();

            if (!$this->cacheDefVersions[$tableName]) {
                throw new errorException($this->translate('There is no default version for table %s.'), $tableName);
            }
        }
        if ($withDefaultOrd) {
            return $this->cacheDefVersions[$tableName];
        }
        return $this->cacheDefVersions[$tableName]['version'];
    }
}
