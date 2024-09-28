<?php

namespace totum\common\calculates;

trait FuncGomTrait
{
    protected function funcCheckGomDaemonds($params)
    {
        /** @var CalculateAction $this */
        $this->Table->getTotum()->addOnEnd(function () {
            $this->Table->getTotum()->getConfig()->proGoModuleSocketSend(['method' => 'CheckDaemons']);
        });
    }

}