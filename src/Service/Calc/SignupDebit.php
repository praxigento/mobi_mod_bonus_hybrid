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
    /** @var \Praxigento\BonusHybrid\Repo\Query\MarkCalcComplete */
    protected $queryMarkComplete;
    /** @var  \Praxigento\BonusHybrid\Service\Calc\SignupDebit\GetOrders */
    protected $subGetOrders;
    /** @var  \Praxigento\BonusHybrid\Service\Calc\SignupDebit\ProcessOrders */
    protected $subProcessOrders;
    /** @var  \Praxigento\Core\Tool\IPeriod */
    protected $toolPeriod;

    public function __construct(
        \Praxigento\Core\Fw\Logger\App $logger,
        \Praxigento\Core\Tool\IPeriod $toolPeriod,
        \Magento\Framework\ObjectManagerInterface $manObj,
        \Praxigento\BonusHybrid\Service\IPeriod $callPeriod,
        \Praxigento\BonusHybrid\Repo\Query\MarkCalcComplete $queryMarkComplete,
        \Praxigento\BonusHybrid\Service\Calc\SignupDebit\GetOrders $subGetOrders,
        \Praxigento\BonusHybrid\Service\Calc\SignupDebit\ProcessOrders $subProcessOrders
    ) {
        parent::__construct($logger, $manObj);
        $this->toolPeriod = $toolPeriod;
        $this->callPeriod = $callPeriod;
        $this->queryMarkComplete = $queryMarkComplete;
        $this->subGetOrders = $subGetOrders;
        $this->subProcessOrders = $subProcessOrders;
    }

    public function exec(\Praxigento\BonusHybrid\Service\Calc\SignupDebit\Request $req)
    {
        $result = new \Praxigento\BonusHybrid\Service\Calc\SignupDebit\Response();
        $this->logger->info("'Sign Up Volume Debit' bonus is started.");
        /* PV based calculation. 'PV Write Off' calc should be started (backward dependency) */
        $reqGetPeriod = new \Praxigento\BonusHybrid\Service\Period\Request\GetForWriteOff();
        $respGetPeriod = $this->callPeriod->getForWriteOff($reqGetPeriod);
        if ($respGetPeriod->isSucceed()) {
            if ($respGetPeriod->hasNoPvTransactionsYet()) {
                $this->logger->info("There is no PV transactions yet. Nothing to calculate.");
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
                if ($periodData) {
                    $periodId = $periodData->getId();
                    $calcData = $respPeriodSignup->getDependentCalcData();
                    $calcId = $calcData->getId();
                    $periodBegin = $periodData->getDstampBegin();
                    $periodEnd = $periodData->getDstampEnd();
                    $calcState = $calcData->getState();
                    $this->logger->info("Processing period #$periodId ($periodBegin-$periodEnd), Sign Up Volume Debit calculation #$calcId ($calcState).");
                    if ($calcState != Cfg::CALC_STATE_COMPLETE) {
                        $dateApplied = $this->toolPeriod->getTimestampTo($periodEnd);
                        /* get first orders for just signed up customers */
                        $reqGetOrders = new \Praxigento\BonusHybrid\Service\Calc\SignupDebit\GetOrders\Request();
                        $reqGetOrders->dateFrom = $this->toolPeriod->getTimestampFrom($periodBegin);
                        $reqGetOrders->dateTo = $this->toolPeriod->getTimestampTo($periodEnd);
                        $orders = $this->subGetOrders->exec($reqGetOrders);
                        $this->subProcessOrders->exec([
                            \Praxigento\BonusHybrid\Service\Calc\SignupDebit\ProcessOrders::OPT_CALC_ID => $calcId,
                            \Praxigento\BonusHybrid\Service\Calc\SignupDebit\ProcessOrders::OPT_ORDERS => $orders,
                            \Praxigento\BonusHybrid\Service\Calc\SignupDebit\ProcessOrders::OPT_DATE_APPLIED => $dateApplied
                        ]);
                        $this->queryMarkComplete->exec($calcId);
                        $result->setPeriodId($periodId);
                        $result->setCalcId($calcId);
                        $result->markSucceed();
                    }
                } else {
                    $this->logger->warning("There is no period to calculate 'Sign Up Volume Debit' bonus.");
                }
            }
        }
        $this->logMemoryUsage();
        $this->logger->info("'Sign Up Volume Debit' bonus is completed.");
        return $result;
    }
}