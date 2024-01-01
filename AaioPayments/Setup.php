<?php

namespace DCS\AaioPayments;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;

class Setup extends AbstractSetup
{
    use StepRunnerInstallTrait;
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstallTrait;

    public function installStep1()
    {
        $this->db()->insertBulk('xf_payment_provider',
            [
                [
                    'provider_id' => 'dcsAaio',
                    'provider_class' => 'DCS\\AaioPayments:Aaio',
                    'addon_id' => 'DCS/AaioPayments'
                ]
            ], 'provider_id');
    }

    public function uninstallStep1()
    {
        $this->db()->delete('xf_payment_provider', "provider_id LIKE 'dcsAaio'");
    }

}

