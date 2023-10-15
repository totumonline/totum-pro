<?php

namespace totum\common\calculates;

use totum\common\FormatParamsForSelectFromTable;

trait FuncProProfileTrait
{

    protected function funcProProfileSelectRowList($params)
    {
        //start, stop, time, RAM, errors;
        $params = $this->getParamsArray($params, ['where']);
        $where = new FormatParamsForSelectFromTable();
        foreach ($params['where'] ?? [] as $_w) {
            if (preg_match('/^[a-zA-Z]+$/', $_w['field'])) {
                $where->where($_w['field'], $_w['value'], $_w['operator']);
            }
        }
        
        if(!empty($params['order'])){
            foreach ($params['order'] ?? [] as $_w) {
                if (preg_match('/^[a-zA-Z]+$/', $_w['field'])) {
                    $where->order($_w['field'], $_w['ad']);
                }
            }

        }

        $data = [];
        foreach ($this->Table->getTotum()->getConfig()->getProfilerSaveObject()->get($where, (int)($params['limit'] ?? 100)) as $row) {
            $row['start'] = date('Y-m-d H:i:s', (int)$row['start']);
            if (!empty($row['stop'])) {
                $row['stop'] = date('Y-m-d H:i:s', (int)$row['stop']);
            }
            $data[] = $row;
        }
        return $data;
    }
    protected function funcProProfileClear($params)
    {
        //start, stop, time, RAM, errors;
        $params = $this->getParamsArray($params, ['where']);
        $where = new FormatParamsForSelectFromTable();
        foreach ($params['where'] ?? [] as $_w) {
            if (preg_match('/^[a-zA-Z]+$/', $_w['field'])) {
                $where->where($_w['field'], $_w['value'], $_w['operator']);
            }
        }
        $this->Table->getTotum()->getConfig()->getProfilerSaveObject()->clear($where);
    }
}