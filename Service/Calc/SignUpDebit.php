<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Service\Calc\SignUpDebit\A\Repo\Query\GetOrders as QBGetOrders;

class SignUpDebit
    implements \Praxigento\Core\Api\App\Service\Process
{
    /** @var \Praxigento\BonusBase\Repo\Dao\Calculation */
    private $daoCalc;
    /** @var  \Praxigento\Core\Api\Helper\Period */
    private $hlpPeriod;
    /** @var \Praxigento\Core\Api\App\Logger\Main */
    private $logger;
    /** @var  \Praxigento\BonusHybrid\Service\Calc\SignUpDebit\A\ProcessOrders */
    private $ownProcessOrders;
    /** @var \Praxigento\BonusHybrid\Service\Calc\SignUpDebit\A\Repo\Query\GetOrders */
    private $qbGetOrders;
    /** @var \Praxigento\BonusBase\Service\Period\Calc\Get\IBasis */
    private $servPeriodGetBasis;

    public function __construct(
        \Praxigento\Core\Api\App\Logger\Main $logger,
        \Praxigento\Core\Api\Helper\Period $hlpPeriod,
        \Praxigento\BonusBase\Repo\Dao\Calculation $daoCalc,
        \Praxigento\BonusHybrid\Service\Calc\SignUpDebit\A\Repo\Query\GetOrders $qbGetOrders,
        \Praxigento\BonusBase\Service\Period\Calc\Get\IBasis $servPeriodGetBasis,
        \Praxigento\BonusHybrid\Service\Calc\SignUpDebit\A\ProcessOrders $ownProcessOrders
    ) {
        $this->logger = $logger;
        $this->hlpPeriod = $hlpPeriod;
        $this->daoCalc = $daoCalc;
        $this->qbGetOrders = $qbGetOrders;
        $this->servPeriodGetBasis = $servPeriodGetBasis;
        $this->ownProcessOrders = $ownProcessOrders;
    }

    public function exec(\Praxigento\Core\Data $ctx)
    {
        /**
         * perform processing
         */
        $ctx->set(self::CTX_OUT_SUCCESS, false);
        /* get dependent calculation data */
        list($periodData, $calcData) = $this->getCalcData();
        $this->logger->info("'Sign Up Volume Debit' bonus is started.");
        $periodId = $periodData->getId();
        $calcId = $calcData->getId();
        $periodBegin = $periodData->getDstampBegin();
        $periodEnd = $periodData->getDstampEnd();
        $calcState = $calcData->getState();
        $this->logger->info("Processing period #$periodId ($periodBegin-$periodEnd), Sign Up Volume Debit calculation #$calcId ($calcState).");
        if ($calcState != Cfg::CALC_STATE_COMPLETE) {
            $dateApplied = $this->hlpPeriod->getTimestampLastSecond($periodEnd);
            /* get first orders for just signed up customers */
            $orders = $this->getOrders($periodBegin, $periodEnd);
            $this->ownProcessOrders->exec($orders, $dateApplied, $calcId);
            /* mark this calculation complete */
            $this->daoCalc->markComplete($calcId);
            /* mark process as successful */
            $ctx->set(self::CTX_OUT_SUCCESS, true);
        }
        $this->logger->info("'Sign Up Volume Debit' bonus is completed.");
    }

    /**
     * Get data for dependent calculation.
     *
     * @return array [$periodData, $calcData]
     * @throws \Exception
     */
    private function getCalcData()
    {
        /* get period & calc data (first calc in the chain) */
        $ctx = new \Praxigento\Core\Data();
        $ctx->set($this->servPeriodGetBasis::CTX_IN_CALC_CODE, Cfg::CODE_TYPE_CALC_BONUS_SIGN_UP_DEBIT);
        $ctx->set($this->servPeriodGetBasis::CTX_IN_ASSET_TYPE_CODE, Cfg::CODE_TYPE_ASSET_PV);
        $ctx->set($this->servPeriodGetBasis::CTX_IN_PERIOD_TYPE, \Praxigento\Core\Api\Helper\Period::TYPE_MONTH);
        $this->servPeriodGetBasis->exec($ctx);
        $periodData = $ctx->get($this->servPeriodGetBasis::CTX_OUT_PERIOD_DATA);
        $calcData = $ctx->get($this->servPeriodGetBasis::CTX_OUT_CALC_DATA);
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
