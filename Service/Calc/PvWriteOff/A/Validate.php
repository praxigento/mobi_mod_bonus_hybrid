<?php
/**
 * Authors: Alex Gusev <alex@flancer64.com>
 * Since: 2018
 */

namespace Praxigento\BonusHybrid\Service\Calc\PvWriteOff\A;

use Praxigento\BonusHybrid\Config as Cfg;

/**
 * Look up fo accounts with negative balance, compose list & throw exception.
 */
class Validate
{
    /** @var \Praxigento\Accounting\Repo\Dao\Account */
    private $daoAcc;
    /** @var \Praxigento\Downline\Repo\Dao\Customer */
    private $daoDwnlCust;
    /** @var \Psr\Log\LoggerInterface */
    private $logger;

    public function __construct(
        \Praxigento\Core\Api\App\Logger\Main $logger,
        \Praxigento\Accounting\Repo\Dao\Account $daoAcc,
        \Praxigento\Downline\Repo\Dao\Customer $daoDwnlCust
    ) {
        $this->logger = $logger;
        $this->daoAcc = $daoAcc;
        $this->daoDwnlCust = $daoDwnlCust;
    }

    /**
     * @param array $turnover [$accId => $turnover]; see ..\PvWriteOff::groupPvTrans
     * @param string $dateApplied '2017-01-31 23:59:59'
     * @return array
     * @throws \Exception
     */
    public function exec($balances)
    {
        $found = [];
        $sysAccId = $this->daoAcc->getSystemAccountIdByAssetCode(Cfg::CODE_TYPE_ASSET_PV);
        foreach ($balances as $accId => $amount) {
            if (
                /* don't catch negative "almost zero amounts" (-4.53453453*10-17), just real negatives */
                (abs($amount) > Cfg::DEF_ZERO) &&
                ($amount < 0) &&
                /* and don't catch system account PV */
                ($accId != $sysAccId)
            ) {

                $found[$accId] = $amount;
            }
        }
        /* log results and throw an exception */
        $total = count($found);
        if ($total > 0) {
            $this->logger->info("There are total '$total' entries with negative PV balance:");
            foreach ($found as $accId => $amount) {
                $accData = $this->daoAcc->getById($accId);
                $custId = $accData->getCustomerId();
                $custData = $this->daoDwnlCust->getById($custId);
                $mlmId = $custData->getMlmId();
                $this->logger->info("\t$mlmId: $amount (acc. id:$accId);");
            }
            throw new \Exception("There are balances with negative PV values. Please, see the log for details.");
        }
    }

}