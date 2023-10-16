<?php

namespace totum\common\configs;

use totum\common\FormatParamsForSelectFromTable;

interface ProfilerSaveObjectInterface
{
    public function insert(array $data);
    public function update(array $data, $hash);

    public function get(FormatParamsForSelectFromTable $where, int $limit): array;

    public function clear(FormatParamsForSelectFromTable $where);
}