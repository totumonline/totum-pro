<?php


namespace totum\common\configs;

use totum\common\criticalErrorException;
use totum\common\errorException;

trait MultiTrait
{
    public function getFilesDir()
    {
        $dir = $this->baseDir . 'http/fls/' . ($this->getHostForDir($this->getFullHostName())) . '/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }
    public function getSecureFilesDir(): string
    {
        $dir = $this->baseDir . 'secureFiles/' . ($this->getHostForDir($this->getFullHostName())) . '/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }

    public function getSomeHost()
    {
        if($this->hostName){
            return $this->hostName;
        }

        foreach ($this->getSchemas() as $host=>$schema){
            return $host;
        }

        throw new criticalErrorException('Totum has not any hosts');
    }

    protected function getHostForDir($host)
    {
        return preg_replace(
            '`^(www.)?(.+)$`',
            '$2',
            $host
        );
    }
    public function setHostSchema($hostName = null, $schemaName = null, $withProfilingStart = true)
    {
        if ($hostName) {
            $this->hostName = $hostName;
            $this->schemaName = $schemaName ?? $this->getSchemas()[$hostName] ?? die($this->getLangObj()->translate('Scheme not found.'));
        } elseif ($schemaName) {
            $this->schemaName = $schemaName;
            $this->hostName = $hostName ?? array_flip($this->getSchemas())[$schemaName] ?? die($this->getLangObj()->translate('Scheme not found.'));
        }

        if ($withProfilingStart){
            $this->profilingStart();
        }
    }
    public function getClearConf()
    {
        if (!empty($GLOBALS[static::$GlobProfilerVarName]) && is_a($GLOBALS[static::$GlobProfilerVarName] ?? false, Profiler::class)) {
            $GLOBALS[static::$GlobProfilerVarName]->increaseRestarts();
        }

        $Conf= new static($this->env, false);
        $Conf->setHostSchema($this->hostName, $this->schemaName);
        $Conf->setInterfaceData($this->getInterfaceData());
        return $Conf;
    }

    public function getMainHostName()
    {
        $host = $this->hostName;
        if ($this->getHiddenHosts()[$this->hostName] ?? false) {
            foreach (static::getSchemas() as $host => $schema) {
                if ($schema === $this->schemaName) {
                    return $host;
                }
            }
        }
        return $host;
    }
}
