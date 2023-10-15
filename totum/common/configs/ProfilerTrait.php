<?php

namespace totum\common\configs;

trait ProfilerTrait
{
    public function profilingStart($path)
    {
        if (empty($GLOBALS[static::$GlobProfilerVarName])) {
            if ($this->getSettings('h_pro_profiling') && ($this->getSettings('h_pro_profiling')['on'] ?? false)) {
                $Profiler = new Profiler($path, $this->getProfilerSaveObject());
                if ($this->getSettings('h_pro_profiling')['withErrors'] ?? false) {
                    set_error_handler([$Profiler, 'errorHandler']);
                }
                if (($this->getSettings('h_pro_profiling')['withBroken'] ?? false)) {
                    $Profiler->saveStartLine();
                }
                $GLOBALS[static::$GlobProfilerVarName] = $Profiler;
            }
        }
    }

    public function getProfilerSaveObject(): ProfilerSaveObjectInterface
    {
        return new ProfileSaveObjectPostgres($this->getSql(false, true)->getPDO());
    }
}