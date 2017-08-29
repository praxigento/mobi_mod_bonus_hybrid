<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc;

use Praxigento\BonusHybrid\Config as Cfg;

class SignupDebit
    extends \Praxigento\Core\Service\Base\Call
    implements \Praxigento\BonusHybrid\Service\Calc\ISignupDebit
{

    /** @var \Praxigento\BonusHybrid\Service\IPeriod */
    private $callPeriod;
    /** @var  \Praxigento\Core\Tool\IPeriod */
    private $hlpPeriod;
    /** @var \Praxigento\BonusBase\Service\Period\Calc\Get\IBasis */
    private $procPeriodGetBasis;
    /** @var \Praxigento\BonusHybrid\Repo\Query\MarkCalcComplete */
    private $qMarkComplete;
    /** @var  \Praxigento\BonusHybrid\Service\Calc\SignupDebit\GetOrders */
    private $subGetOrders;
    /** @var  \Praxigento\BonusHybrid\Service\Calc\SignupDebit\ProcessOrders */
    private $subProcessOrders;

    public function __construct(
        \Praxigento\Core\Fw\Logger\App $logger,
        \Praxigento\Core\Tool\IPeriod $hlpPeriod,
        \Magento\Framework\ObjectManagerInterface $manObj,
        \Praxigento\BonusHybrid\Service\IPeriod $callPeriod,
        \Praxigento\BonusBase\Service\Period\Calc\Get\IBasis $procPeriodGetBasis,
        \Praxigento\BonusHybrid\Repo\Query\MarkCalcComplete $queryMarkComplete,
        \Praxigento\BonusHybrid\Service\Calc\SignupDebit\GetOrders $subGetOrders,
        \Praxigento\BonusHybrid\Service\Calc\SignupDebit\ProcessOrders $subProcessOrders
    )
    {
        parent::__construct($logger, $manObj);
        $this->hlpPeriod = $hlpPeriod;
        $this->callPeriod = $callPeriod;
        $this->procPeriodGetBasis = $procPeriodGetBasis;
        $this->qMarkComplete = $queryMarkComplete;
        $this->subGetOrders = $subGetOrders;
        $this->subProcessOrders = $subProcessOrders;
    }

    public function exec(\Praxigento\BonusHybrid\Service\Calc\SignupDebit\Request $req)
    {
        $result = new \Praxigento\BonusHybrid\Service\Calc\SignupDebit\Response();
        $this->logger->info("'Sign Up Volume Debit' bonus is started.");
        /* Request calculation period for Sign Up Bonus (first calc in the chain) */
        $ctx = new \Praxigento\Core\Data();
        $ctx->set($this->procPeriodGetBasis::CTX_IN_CALC_CODE, Cfg::CODE_TYPE_CALC_BONUS_SIGNUP_DEBIT);
        $ctx->set($this->procPeriodGetBasis::CTX_IN_ASSET_TYPE_CODE, Cfg::CODE_TYPE_ASSET_PV);
        $ctx->set($this->procPeriodGetBasis::CTX_IN_PERIOD_TYPE, \Praxigento\Core\Tool\IPeriod::TYPE_MONTH);
        $this->procPeriodGetBasis->exec($ctx);
        $success = $ctx->get($this->procPeriodGetBasis::CTX_OUT_SUCCESS);
        /* analyze period creation result */
        if ($success) {
            /* get period|calc data for current 'Sign Up Volume Debit' bonus */
            $periodData = $ctx->get($this->procPeriodGetBasis::CTX_OUT_PERIOD_DATA);
            $calcData = $ctx->get($this->procPeriodGetBasis::CTX_OUT_CALC_DATA);
            if ($periodData) {
                $periodId = $periodData->getId();
                $calcId = $calcData->getId();
                $periodBegin = $periodData->getDstampBegin();
                $periodEnd = $periodData->getDstampEnd();
                $calcState = $calcData->getState();
                $this->logger->info("Processing period #$periodId ($periodBegin-$periodEnd), Sign Up Volume Debit calculation #$calcId ($calcState).");
                if ($calcState != Cfg::CALC_STATE_COMPLETE) {
                    $dateApplied = $this->hlpPeriod->getTimestampTo($periodEnd);
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
                    $this->qMarkComplete->exec($calcId);
                    $result->setPeriodId($periodId);
                    $result->setCalcId($calcId);
                    $result->markSucceed();
                }
            } else {
                $this->logger->warning("There is no period to calculate 'Sign Up Volume Debit' bonus.");
            }
        }
        $this->logMemoryUsage();
        $this->logger->info("'Sign Up Volume Debit' bonus is completed.");
        return $result;
    }
}