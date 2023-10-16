<?php

namespace totum\common\configs;

trait ProfilerTrait
{
    public function profilingStart($path, callable|null $extraData = null)
    {
        if (empty($GLOBALS[static::$GlobProfilerVarName])) {
            if ($this->getSettings('h_pro_profiling') && ($this->getSettings('h_pro_profiling')['on'] ?? false)) {

                if (!($this->getSettings('h_pro_profiling')['withNotif'] ?? false)) {
                    if (('POST' === ($_SERVER['REQUEST_METHOD'] ?? false)) &&
                        str_ends_with($path, 'checkForNotifications') || str_ends_with($path, 'checkTableIsChanged')) {
                        return;
                    }
                }

                $Profiler = new Profiler($path, function () {
                    return $this->getProfilerSaveObject();
                }, $extraData ? $extraData() : []);
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