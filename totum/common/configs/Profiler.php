<?php

namespace totum\common\configs;

class Profiler
{
    protected $data = [
        'restarts' => 0
    ];
    protected string|null $startHash = null;

    public function __construct($path, protected ProfilerSaveObjectInterface $SaveObject)
    {
        $this->data['path'] = $path;
        $this->data['start'] = round(microtime(true), 2);
    }

    public function saveStartLine()
    {
        $this->save(true);
    }

    protected function save($isStart = false)
    {
        if ($this->startHash) {
            $this->SaveObject->update($this->data, $this->startHash);
        } elseif ($isStart) {
            $this->startHash = $this->SaveObject->insert($this->data);
        } else {
            $this->SaveObject->insert($this->data);
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