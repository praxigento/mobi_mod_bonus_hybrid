<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\SignUp;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Service\Calc\SignUp\Debit\A\Repo\Query\GetOrders as QBGetOrders;

/**
 * PV debits calculation for "Sign Up" bonus.
 */
class Debit
    implements \Praxigento\Core\Api\App\Service\Process
{
    /** @var  \Praxigento\BonusHybrid\Service\Calc\SignUp\Debit\A\ProcessDebits */
    private $aProcess;
    /** @var \Praxigento\BonusBase\Repo\Dao\Calculation */
    private $daoCalc;
    /** @var  \Praxigento\Core\Api\Helper\Period */
    private $hlpPeriod;
    /** @var \Praxigento\Core\Api\App\Logger\Main */
    private $logger;
    /** @var \Praxigento\BonusHybrid\Service\Calc\SignUp\Debit\A\Repo\Query\GetOrders */
    private $qbGetOrders;
    /** @var \Praxigento\BonusBase\Api\Service\Period\Calc\Get\Basis */
    private $servPeriodGetBasis;

    public function __construct(
        \Praxigento\Core\Api\App\Logger\Main $logger,
        \Praxigento\Core\Api\Helper\Period $hlpPeriod,
        \Praxigento\BonusBase\Repo\Dao\Calculation $daoCalc,
        \Praxigento\BonusBase\Api\Service\Period\Calc\Get\Basis $servPeriodGetBasis,
        \Praxigento\BonusHybrid\Service\Calc\SignUp\Debit\A\Repo\Query\GetOrders $qbGetOrders,
        \Praxigento\BonusHybrid\Service\Calc\SignUp\Debit\A\ProcessDebits $aProcess
    ) {
        $this->logger = $logger;
        $this->hlpPeriod = $hlpPeriod;
        $this->daoCalc = $daoCalc;
        $this->servPeriodGetBasis = $servPeriodGetBasis;
        $this->qbGetOrders = $qbGetOrders;
        $this->aProcess = $aProcess;
    }

    /**
     * PV debits calculation for "Sign Up" bonus.
     *
     * @inheritDoc
     */
    public function exec(\Praxigento\Core\Data $ctx)
    {
        /**
         * perform processing
         */
        $ctx->set(self::CTX_OUT_SUCCESS, false);
        /* get dependent calculation data */
        [$periodData, $calcData] = $this->getCalcData();
        $this->logger->info("'Sign Up Volume Debit' bonus is started.");
        $calcState = $calcData->getState();
        if ($calcState != Cfg::CALC_STATE_COMPLETE) {
            $periodId = $periodData->getId();
            $calcId = $calcData->getId();
            $periodBegin = $periodData->getDstampBegin();
            $periodEnd = $periodData->getDstampEnd();
            $this->logger->info("Processing period #$periodId ($periodBegin-$periodEnd), Sign Up PV Debit calculation #$calcId ($calcState).");
            /* get first orders for just signed up customers */
            $orders = $this->getOrders($periodBegin, $periodEnd);
            $total = count($orders);
            $this->logger->info("There are '$total' orders in Sign Up PV Debit calculation.");
            /* create PV debit operation */
            $dateApplied = $this->hlpPeriod->getTimestampLastSecond($periodEnd);
            $this->aProcess->exec($orders, $dateApplied, $calcId);
            /* mark this calculation complete */
            $this->daoCalc->markComplete($calcId);
            /* mark process as successful */
            $ctx->set(self::CTX_OUT_SUCCESS, true);
        }
        $this->logger->info("'Sign Up Volume Debit' bonus is completed.");
    }

    /**
     * Get data for base calculation.
     *
     * @return array [$periodData, $calcData]
     * @throws \Exception
     */
    private function getCalcData()
    {
        /* get period & calc data (first calc in the chain of all calcs) */
        $req = new \Praxigento\BonusBase\Api\Service\Period\Calc\Get\Basis\Request();
        $req->setCalcCode(Cfg::CODE_TYPE_CALC_BONUS_SIGN_UP_DEBIT);
        $req->setAssetTypeCode(Cfg::CODE_TYPE_ASSET_PV);
        $req->setPeriodType(\Praxigento\Core\Api\Helper\Period::TYPE_MONTH);
        $resp = $this->servPeriodGetBasis->execute($req);
        $periodData = $resp->getPeriodData();
        $calcData = $resp->getCalcData();
        $result = [$periodData, $calcData];
        return $result;
    }

    /**
     * @param string $dateFrom
     * @param string $dateTo
     * @return array
     */
    private function getOrders($dateFrom, $dateTo)
    {
        /** @var  $query */
        $query = $this->qbGetOrders->build();
        $conn = $query->getConnection();
        /** TODO: fix backward dependency for customer group ID
         * (bonus module should not be dependent from project module - \Praxigento\Santegra\Helper\...)
         */
        $from = $this->hlpPeriod->getTimestampFrom($dateFrom);
        $upTo = $this->hlpPeriod->getTimestampNextFrom($dateTo);
        $rs = $conn->fetchAll($query, [
            QBGetOrders::BND_DATE_FROM => $from,
            QBGetOrders::BND_DATE_TO => $upTo,
            /* TODO: reversed dependency from project module (create helper for the group ID)*/
            QBGetOrders::BND_CUST_GROUP_ID => \Praxigento\Santegra\Helper\Odoo\BusinessCodes::M_CUST_GROUP_DISTRIBUTOR
        ]);
        /* only customer's first order should be included in the result set (apply to the bonus) */
        $result = [];
        foreach ($rs as $one) {
            $custId = $one[QBGetOrders::A_CUST_ID];
            if (!isset($result[$custId])) {
                $result[$custId] = $one;
            }
        }
        return $result;
    }
}
