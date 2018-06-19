<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Bonus\Z\Helper;

use Praxigento\Accounting\Repo\Data\Transaction as ETrans;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Service\Calc\Bonus\Z\Data\Bonus as DBonus;

/**
 * Prepare transaction data to register bonus operation.
 */
class PrepareTrans
{
    /**
     * Additional field in transaction object to bind transaction ID to donator ID on operation add. This link (between
     * transaction & donated customer) will be registered in 'prxgt_bon_base_log_cust' later.
     */
    const REF_DONATOR_ID = 'refDonatorId';

    /** @var \Praxigento\Accounting\Repo\Dao\Account */
    private $daoAcc;
    /** @var \Praxigento\Accounting\Repo\Dao\Type\Asset */
    private $daoAssetType;

    public function __construct(
        \Praxigento\Accounting\Repo\Dao\Account $daoAcc,
        \Praxigento\Accounting\Repo\Dao\Type\Asset $daoAssetType
    )
    {
        $this->daoAcc = $daoAcc;
        $this->daoAssetType = $daoAssetType;
    }

    /**
     * @param DBonus[] $bonus
     * @param string $dateApplied
     * @param string $note transaction note
     * @return array
     * @throws \Exception
     */
    public function exec($bonus, $dateApplied, $note)
    {
        $assetTypeId = $this->daoAssetType->getIdByCode(Cfg::CODE_TYPE_ASSET_BONUS);
        $sysAccId = $this->daoAcc->getSystemAccountId($assetTypeId);
        $result = [];
        /** @var DBonus $one */
        foreach ($bonus as $one) {
            $custId = $one->getCustomerRef();
            $donatorId = $one->getDonatorRef();
            $value = $one->getValue();
            if ($value > Cfg::DEF_ZERO) {
                /* get account ID for customer ID */
                $acc = $this->daoAcc->getByCustomerId($custId, $assetTypeId);
                $accId = $acc->getId();
                /* skip system account */
                if ($accId == $sysAccId) {
                    continue;
                }
                $tran = new ETrans();
                $tran->setDebitAccId($sysAccId);
                $tran->setCreditAccId($accId);
                $tran->setDateApplied($dateApplied);
                $tran->setValue($value);
                if (empty($donatorId)) {
                    $tran->setNote($note);
                } else {
                    $tran->setNote($note . ': cust. #' . $donatorId);
                    $tran->set(self::REF_DONATOR_ID, $donatorId);
                }
                $result[] = $tran;
            } else {
                /* skip zero amounts */
            }
        }
        return $result;
    }

}