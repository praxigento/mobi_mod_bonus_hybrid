<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\PvWriteOff\A;

use Praxigento\Accounting\Repo\Data\Transaction as ETrans;
use Praxigento\BonusHybrid\Config as Cfg;

/**
 * Prepare transaction data to register "PV Write Off" operation.
 */
class PrepareTrans
{
    /** @var \Praxigento\Accounting\Repo\Dao\Account */
    private $daoAcc;
    /** @var \Praxigento\Accounting\Repo\Dao\Type\Asset */
    private $daoTypeAsset;

    public function __construct(
        \Praxigento\Accounting\Repo\Dao\Account $daoAcc,
        \Praxigento\Accounting\Repo\Dao\Type\Asset $daoTypeAsset
    )
    {
        $this->daoAcc = $daoAcc;
        $this->daoTypeAsset = $daoTypeAsset;
    }

    /**
     * @param array $turnover [$accId => $turnover]; see ..\PvWriteOff::groupPvTrans
     * @param string $dateApplied '2017-01-31 23:59:59'
     * @return array
     * @throws \Exception
     */
    public function exec($turnover, $dateApplied)
    {
        $assetTypeId = $this->daoTypeAsset->getIdByCode(Cfg::CODE_TYPE_ASSET_PV);
        $sysAccId = $this->daoAcc->getSystemAccountId($assetTypeId);
        $period = substr($dateApplied, 0, 7);
        $period = str_replace('-', '', $period);
        $note = "PV Write Off ($period)";
        $result = [];
        foreach ($turnover as $accId => $value) {
            if ($value > Cfg::DEF_ZERO) {
                /* skip system account */
                if ($accId == $sysAccId) {
                    continue;
                }
                $tran = new ETrans();
                $tran->setDebitAccId($accId);
                $tran->setCreditAccId($sysAccId);
                $tran->setDateApplied($dateApplied);
                $tran->setNote($note);
                $tran->setValue($value);
                $result[] = $tran;
            } else {
                /* skip zero amounts */
            }
        }
        return $result;
    }

}