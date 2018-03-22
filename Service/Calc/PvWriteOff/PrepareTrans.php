<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\PvWriteOff;

use Praxigento\Accounting\Repo\Data\Transaction as ETrans;
use Praxigento\BonusHybrid\Config as Cfg;

/**
 * Prepare transaction data to register "PV Write Off" operation.
 *
 * TODO: merge with \Praxigento\BonusHybrid\Service\Calc\Personal\PrepareTrans
 */
class PrepareTrans
{
    /** @var \Praxigento\Accounting\Repo\Dao\Account */
    private $repoAcc;
    /** @var \Praxigento\Accounting\Repo\Dao\Type\Asset */
    private $repoAssetType;

    public function __construct(
        \Praxigento\Accounting\Repo\Dao\Account $repoAcc,
        \Praxigento\Accounting\Repo\Dao\Type\Asset $repoAssetType
    )
    {
        $this->repoAcc = $repoAcc;
        $this->repoAssetType = $repoAssetType;
    }

    /**
     * @param array $turnover [$accId => $turnover]; see ..\PvWriteOff::groupPvTrans
     */
    public function exec($turnover, $dateApplied)
    {
        $assetTypeId = $this->repoAssetType->getIdByCode(Cfg::CODE_TYPE_ASSET_PV);
        $sysAccId = $this->repoAcc->getSystemAccountId($assetTypeId);
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
                $tran->setValue($value);
                $result[] = $tran;
            } else {
                /* skip zero amounts */
            }
        }
        return $result;
    }

}