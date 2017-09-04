<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Bonus\Personal;

use Praxigento\Accounting\Repo\Entity\Data\Transaction as ETrans;
use Praxigento\BonusHybrid\Config as Cfg;

/**
 * Prepare transaction data to register "Personal Bonus" operation.
 *
 * TODO: merge with \Praxigento\BonusHybrid\Service\Calc\PvWriteOff\PrepareTrans
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
     * @param array $turnover [$custId => $turnover]; see ..\Personal::calcBonus
     *
     * TODO: we need flag for index - is it a 'accountId' or ' customerId' data?
     * TODO: we should use asset type code as input parameter
     * TODO: should we move this process into the Accounting module?
     */
    public function exec($turnover, $dateApplied)
    {
        $assetTypeId = $this->repoAssetType->getIdByCode(Cfg::CODE_TYPE_ASSET_WALLET_ACTIVE);
        $represAccId = $this->repoAcc->getRepresentativeAccountId($assetTypeId);
        $result = [];
        foreach ($turnover as $custId => $value) {
            if ($value > Cfg::DEF_ZERO) {
                /* get account ID for customer ID */
                $acc = $this->repoAcc->getByCustomerId($custId, $assetTypeId);
                $accId = $acc->getId();
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