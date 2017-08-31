<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\PvWriteOff;

use Praxigento\Accounting\Repo\Entity\Data\Transaction as ETrans;
use Praxigento\BonusHybrid\Config as Cfg;

/**
 * Prepare transaction data to register "PV Write Off" operation.
 */
class PrepareTrans
{
    /** @var \Praxigento\Accounting\Repo\Entity\Account */
    private $repoAcc;
    /** @var \Praxigento\Accounting\Repo\Entity\Type\Asset */
    private $repoAssetType;

    public function __construct(
        \Praxigento\Accounting\Repo\Entity\Account $repoAcc,
        \Praxigento\Accounting\Repo\Entity\Type\Asset $repoAssetType
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
        $represAccId = $this->repoAcc->getRepresentativeAccountId($assetTypeId);
        $result = [];
        foreach ($turnover as $accId => $value) {
            if ($value > Cfg::DEF_ZERO) {
                /* skip representative account */
                if ($accId == $represAccId) {
                    continue;
                }
                $tran = new ETrans();
                $tran->setDebitAccId($accId);
                $tran->setCreditAccId($represAccId);
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