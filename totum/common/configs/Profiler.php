<?php

namespace totum\common\configs;

class Profiler
{
    protected $data = [
        'restarts' => 0
    ];
    protected string|null $startHash = null;
    /**
     * @var callable
     */
    protected $SaveObjectFunc;

    public function __construct($path, callable $SaveObjectFunc, $extraData = [])
    {
        $this->data = $extraData;
        $this->data['path'] = $path;
        $this->data['start'] = round(microtime(true), 2);
        $this->SaveObjectFunc = $SaveObjectFunc;
    }

    public function saveStartLine()
    {
        $this->save(true);
    }

    protected function save($isStart = false)
    {
        if ($this->startHash) {
            ($this->SaveObjectFunc)()->update($this->data, $this->startHash);
        } elseif ($isStart) {
            $this->startHash = ($this->SaveObjectFunc)()->insert($this->data);
        } else {
            ($this->SaveObjectFunc)()->insert($this->data);
        }

    }

    public function setUserId($userId)
    {
        $this->data['userId'] = $userId;
    }

    public function increaseRestarts()
    {
        $this->data['restarts']++;
    }

    public function __destruct()
    {
        $this->data['stop'] = round(microtime(true), 2);
        $this->data['time'] = round($this->data['stop'] - $this->data['start'], 2);
        $this->data['RAM'] = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
        $this->save();
    }

    public function errorHandler(int    $errno,
                                 string $errstr,
                                 string $errfile = '',
                                 int    $errline = 0)
    {
        $this->data['errors'] = $this->data['errors'] ?? [];
        $this->data['errors'][] = [
            'no' => $errno,
            'str' => $errstr,
            'file' => $errfile . ':' . $errline,
        ];
    }

}