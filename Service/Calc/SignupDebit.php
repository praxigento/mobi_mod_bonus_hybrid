<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc;

use Praxigento\BonusHybrid\Config as Cfg;

class SignupDebit
    implements \Praxigento\BonusHybrid\Service\Calc\ISignupDebit
{
    /** @var  \Praxigento\Core\Api\Helper\Period */
    private $hlpPeriod;
    /** @var \Praxigento\Core\Api\App\Logger\Main */
    private $logger;
    /** @var \Praxigento\BonusBase\Service\Period\Calc\Get\IBasis */
    private $procPeriodGetBasis;
    /** @var \Praxigento\BonusBase\Repo\Dao\Calculation */
    private $repoCalc;
    /** @var  \Praxigento\BonusHybrid\Service\Calc\SignupDebit\GetOrders */
    private $subGetOrders;
    /** @var  \Praxigento\BonusHybrid\Service\Calc\SignupDebit\ProcessOrders */
    private $subProcessOrders;

    public function __construct(
        \Praxigento\Core\Api\App\Logger\Main $logger,
        \Praxigento\Core\Api\Helper\Period $hlpPeriod,
        \Praxigento\BonusBase\Repo\Dao\Calculation $repoCalc,
        \Praxigento\BonusBase\Service\Period\Calc\Get\IBasis $procPeriodGetBasis,
        \Praxigento\BonusHybrid\Service\Calc\SignupDebit\GetOrders $subGetOrders,
        \Praxigento\BonusHybrid\Service\Calc\SignupDebit\ProcessOrders $subProcessOrders
    )
    {
        $this->logger = $logger;
        $this->hlpPeriod = $hlpPeriod;
        $this->repoCalc = $repoCalc;
        $this->procPeriodGetBasis = $procPeriodGetBasis;
        $this->subGetOrders = $subGetOrders;
        $this->subProcessOrders = $subProcessOrders;
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
            $dateApplied = $this->hlpPeriod->getTimestampUpTo($periodEnd);
            /* get first orders for just signed up customers */
            $reqGetOrders = new \Praxigento\BonusHybrid\Service\Calc\SignupDebit\GetOrders\Request();
            $reqGetOrders->dateFrom = $this->hlpPeriod->getTimestampFrom($periodBegin);
            $reqGetOrders->dateTo = $this->hlpPeriod->getTimestampTo($periodEnd);
            $orders = $this->subGetOrders->exec($reqGetOrders);
            $this->subProcessOrders->exec([
                \Praxigento\BonusHybrid\Service\Calc\SignupDebit\ProcessOrders::OPT_CALC_ID => $calcId,
                \Praxigento\BonusHybrid\Service\Calc\SignupDebit\ProcessOrders::OPT_ORDERS => $orders,
                \Praxigento\BonusHybrid\Service\Calc\SignupDebit\ProcessOrders::OPT_DATE_APPLIED => $dateApplied
            ]);
            /* mark this calculation complete */
            $this->repoCalc->markComplete($calcId);
            /* mark process as successful */
            $ctx->set(self::CTX_OUT_SUCCESS, true);
        }
        $this->logger->info("'Sign Up Volume Debit' bonus is completed.");
    }

    /**
     * Get data for dependent calculation.
     *
     * @return array [$periodData, $calcData]
     */
    private function getCalcData()
    {
        /* get period & calc data (first calc in the chain) */
        $ctx = new \Praxigento\Core\Data();
        $ctx->set($this->procPeriodGetBasis::CTX_IN_CALC_CODE, Cfg::CODE_TYPE_CALC_BONUS_SIGN_UP_DEBIT);
        $ctx->set($this->procPeriodGetBasis::CTX_IN_ASSET_TYPE_CODE, Cfg::CODE_TYPE_ASSET_PV);
        $ctx->set($this->procPeriodGetBasis::CTX_IN_PERIOD_TYPE, \Praxigento\Core\Api\Helper\Period::TYPE_MONTH);
        $this->procPeriodGetBasis->exec($ctx);
        $periodData = $ctx->get($this->procPeriodGetBasis::CTX_OUT_PERIOD_DATA);
        $calcData = $ctx->get($this->procPeriodGetBasis::CTX_OUT_CALC_DATA);
        $result = [$periodData, $calcData];
        return $result;
    }
}