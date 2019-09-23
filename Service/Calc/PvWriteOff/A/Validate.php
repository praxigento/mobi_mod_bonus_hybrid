<?php
/**
 * Authors: Alex Gusev <alex@flancer64.com>
 * Since: 2018
 */

namespace Praxigento\BonusHybrid\Service\Calc\PvWriteOff\A;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Service\Calc\PvWriteOff\A\Validate\A\Query\GetData as QGetData;

/**
 * Look up for accounts with negative balance or for non-distributor accounts, compose list & throw exception.
 */
class Validate
{
    /** @var \Praxigento\Accounting\Repo\Dao\Account */
    private $daoAcc;
    /** @var \Praxigento\Downline\Repo\Dao\Customer */
    private $daoDwnlCust;
    /** @var \Praxigento\Downline\Api\Helper\Config */
    private $hlpDwnlCfg;
    /** @var \Psr\Log\LoggerInterface */
    private $logger;
    /** @var \Praxigento\BonusHybrid\Service\Calc\PvWriteOff\A\Validate\A\Query\GetData */
    private $qGetData;

    public function __construct(
        \Praxigento\Core\Api\App\Logger\Main $logger,
        \Praxigento\Accounting\Repo\Dao\Account $daoAcc,
        \Praxigento\Downline\Repo\Dao\Customer $daoDwnlCust,
        \Praxigento\Downline\Api\Helper\Config $hlpDwnlCfg,
        \Praxigento\BonusHybrid\Service\Calc\PvWriteOff\A\Validate\A\Query\GetData $qGetData
    ) {
        $this->logger = $logger;
        $this->daoAcc = $daoAcc;
        $this->daoDwnlCust = $daoDwnlCust;
        $this->hlpDwnlCfg = $hlpDwnlCfg;
        $this->qGetData = $qGetData;
    }

    /**
     * @param array $balances [accId => pv_balance]
     * @throws \Exception
     */
    public function exec($balances)
    {
        $foundNegative = [];
        $foundNotDistr = [];
        $sysAccId = $this->daoAcc->getSystemAccountIdByAssetCode(Cfg::CODE_TYPE_ASSET_PV);
        $mapGroups = $this->getGroupsMap();
        $groupsAllowed = $this->hlpDwnlCfg->getDowngradeGroupsDistrs();
        foreach ($balances as $accId => $amount) {
            /* and don't catch system account PV */
            if ($accId != $sysAccId) {
                /* catch negative amounts */
                if (
                    /* don't catch negative "almost zero amounts" (-4.53453453*10-17), just real negatives */
                    (abs($amount) > Cfg::DEF_ZERO) && ($amount < 0)
                ) {
                    $foundNegative[$accId] = $amount;
                }
                /* catch not-distributors PV */
                $groupId = $mapGroups[$accId]; // exception is awaited if account has no mapping to the customer group
                if (!in_array($groupId, $groupsAllowed)) {
                    $foundNotDistr[$accId] = $groupId;
                }
            }
        }
        /* log results for negative balances */
        $totalNegative = count($foundNegative);
        if ($totalNegative > 0) {
            $this->logger->info("There are total '$totalNegative' entries with negative PV balance:");
            foreach ($foundNegative as $accId => $amount) {
                $accData = $this->daoAcc->getById($accId);
                $custId = $accData->getCustomerId();
                $custData = $this->daoDwnlCust->getById($custId);
                $mlmId = $custData->getMlmId();
                $this->logger->info("\t$mlmId/$custId: $amount (acc. id:$accId);");
            }
        }
        /* log results for not-distributors */
        $totalNotDistr = count($foundNotDistr);
        if ($totalNotDistr > 0) {
            $this->logger->info("There are total '$totalNotDistr' entries with not-distributors PV balances:");
            foreach ($foundNotDistr as $accId => $groupId) {
                $accData = $this->daoAcc->getById($accId);
                $custId = $accData->getCustomerId();
                $custData = $this->daoDwnlCust->getById($custId);
                $mlmId = $custData->getMlmId();
                $this->logger->info("\t$mlmId/$custId: $groupId (acc. id:$accId);");
            }
        }
        /* throw an exception */
        if (($totalNegative + $totalNotDistr) > 0) {
            throw new \Exception("There are errors due PV balances validation. Please, see the log for details.");
        }
    }

    /**
     * Get accountId to groupId map.
     *
     * @return array [accountId => groupId]
     */
    private function getGroupsMap()
    {
        $query = $this->qGetData->build();
        $conn = $query->getConnection();
        $rs = $conn->fetchAll($query);
        $result = [];
        foreach ($rs as $one) {
            $accId = $one[QGetData::A_ACC_ID];
            $groupId = $one[QGetData::A_GROUP_ID];
            $result[$accId] = $groupId;
        }
        return $result;
    }
}