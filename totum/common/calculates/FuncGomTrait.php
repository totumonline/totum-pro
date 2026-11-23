<?php

namespace totum\common\calculates;

trait FuncGomTrait
{
    protected function funcCheckGomDaemonds($params)
    {
        if ($this->dryRunReplaced('gom', 'checkGomDaemonds', [], $mock)) {
            return $mock;
        }
        $this->Table->getTotum()->getConfig()->proGoModuleSocketSend(['method' => 'CheckLicense'], true);
        /** @var CalculateAction $this */
        $this->Table->getTotum()->addOnEnd(function () {
           $this->Table->getTotum()->getConfig()->proGoModuleSocketSend(['method' => 'CheckDaemons']);
        });
    }

}