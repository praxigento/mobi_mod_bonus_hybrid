<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Bonus\Courtesy;

use Praxigento\Accounting\Repo\Entity\Data\Transaction as ETrans;
use Praxigento\BonusHybrid\Config as Cfg;

/**
 * Prepare transaction data to register "Courtesy Bonus" operation.
 */
class PrepareTrans
{
    /**
     * Additional field in transaction object to bind transaction ID to donator ID on operation add. This link (between transaction & donated customer) will be registered in 'prxgt_bon_base_log_cust' later.
     */
    const REF_DONATOR_ID = 'refDonatorId';

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
     * @param \Praxigento\BonusHybrid\Service\Calc\Bonus\Team\Calc\Data[] $bonus
     * @param $dateApplied
     */
    public function exec($bonus, $dateApplied)
    {
        $assetTypeId = $this->repoAssetType->getIdByCode(Cfg::CODE_TYPE_ASSET_WALLET_ACTIVE);
        $represAccId = $this->repoAcc->getRepresentativeAccountId($assetTypeId);
        $result = [];
        /** @var \Praxigento\BonusHybrid\Service\Calc\Bonus\Team\Calc\Data $one */
        foreach ($bonus as $one) {
            $custId = $one->getCustomerRef();
            $donatorId = $one->getDonatorRef();
            $value = $one->getValue();
            if ($value > Cfg::DEF_ZERO) {
                /* get account ID for customer ID */
                $acc = $this->repoAcc->getByCustomerId($custId, $assetTypeId);
                $accId = $acc->getId();
                /* skip representative account */
                if ($accId == $represAccId) {
                    continue;
                }
                $tran = new ETrans();
                $tran->setDebitAccId($represAccId);
                $tran->setCreditAccId($accId);
                $tran->setDateApplied($dateApplied);
                $tran->setValue($value);
                $tran->set(self::REF_DONATOR_ID, $donatorId);
                $result[] = $tran;
            } else {
                /* skip zero amounts */
            }
        }
        return $result;
    }

}