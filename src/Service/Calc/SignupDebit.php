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
    protected $callPeriod;
    /** @var  \Praxigento\BonusHybrid\Service\Calc\SignupDebit\GetOrders */
    protected $subGetOrders;
    /** @var  \Praxigento\Core\Tool\IPeriod */
    protected $toolPeriod;
    /** @var  \Praxigento\BonusHybrid\Service\Calc\SignupDebit\ProcessOrders */
    protected $subProcessOrders;

    public function __construct(
        \Praxigento\Core\Fw\Logger\App $logger,
        \Praxigento\Core\Tool\IPeriod $toolPeriod,
        \Magento\Framework\ObjectManagerInterface $manObj,
        \Praxigento\BonusHybrid\Service\IPeriod $callPeriod,
        \Praxigento\BonusHybrid\Service\Calc\SignupDebit\GetOrders $subGetOrders,
        \Praxigento\BonusHybrid\Service\Calc\SignupDebit\ProcessOrders $subProcessOrders
    ) {
        parent::__construct($logger, $manObj);
        $this->toolPeriod = $toolPeriod;
        $this->callPeriod = $callPeriod;
        $this->subGetOrders = $subGetOrders;
        $this->subProcessOrders = $subProcessOrders;
    }

    public function exec(\Praxigento\BonusHybrid\Service\Calc\SignupDebit\Request $req)
    {
        $result = new \Praxigento\BonusHybrid\Service\Calc\SignupDebit\Response();
        $this->_logger->info("'Sign Up Volume Debit' bonus is started.");
        /* PV based calculation. 'PV Write Off' calc should be started (backward dependency) */
        $reqGetPeriod = new \Praxigento\BonusHybrid\Service\Period\Request\GetForWriteOff();
        $respGetPeriod = $this->callPeriod->getForWriteOff($reqGetPeriod);
        if ($respGetPeriod->isSucceed()) {
            if ($respGetPeriod->hasNoPvTransactionsYet()) {
                $this->_logger->info("There is no PV transactions yet. Nothing to calculate.");
                $result->markSucceed();
            } else {
                /* get/create period and calc for 'Sign Up Volume Debit' bonus */
                $reqPeriodSignup = new \Praxigento\BonusHybrid\Service\Period\Request\GetForDependentCalc();
                $reqPeriodSignup->setBaseCalcTypeCode(Cfg::CODE_TYPE_CALC_PV_WRITE_OFF);
                $reqPeriodSignup->setDependentCalcTypeCode(Cfg::CODE_TYPE_CALC_BONUS_SIGNUP_DEBIT);
                $reqPeriodSignup->setAllowIncompleteBaseCalc(true); // PV Write Off period cannot be completed before this calc
                $respPeriodSignup = $this->callPeriod->getForDependentCalc($reqPeriodSignup);
                /* extract data for this period/calc */
                $periodData = $respPeriodSignup->getDependentPeriodData();
                $periodId = $periodData->getId();
                $calcData = $respPeriodSignup->getDependentCalcData();
                $calcId = $calcData->getId();
                $periodBegin = $periodData->getDstampBegin();
                $periodEnd = $periodData->getDstampEnd();
                $calcState = $calcData->getState();
                $this->_logger->info("Processing period #$periodId ($periodBegin-$periodEnd), Sign Up Volume Debit calculation #$calcId ($calcState).");
                if ($calcState != Cfg::CALC_STATE_COMPLETE) {
                    /* get first orders for just signed up customers */
                    $reqGetOrders = new \Praxigento\BonusHybrid\Service\Calc\SignupDebit\GetOrders\Request();
                    $reqGetOrders->dateFrom = $this->toolPeriod->getTimestampFrom($periodBegin);
                    $reqGetOrders->dateTo = $this->toolPeriod->getTimestampTo($periodEnd);
                    $orders = $this->subGetOrders->do($reqGetOrders);
                    $this->subProcessOrders->do($orders);
                }

//                $transData = $this->_subDb->getDataForWriteOff($calcId, $periodBegin, $periodEnd);
//                $updates = $this->_subCalc->pvWriteOff($transData);
//                $dateApplied = $this->_toolPeriod->getTimestampTo($periodEnd);
//                $operId = $this->_subDb->saveOperationPvWriteOff($updates, $datePerformed, $dateApplied);
//                $this->_subDb->saveLogPvWriteOff($transData, $operId, $calcId);
//                $this->_subDb->markCalcComplete($calcId);
//                $result->setPeriodId($periodId);
//                $result->setCalcId($calcId);
//                $result->markSucceed();
            }
        }
        $this->_logMemoryUsage();
        $this->_logger->info("'Sign Up Volume Debit' bonus is completed.");
        return $result;
    }
}