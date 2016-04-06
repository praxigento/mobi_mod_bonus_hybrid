<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\Bonus\Hybrid\Lib\Service\Period;

use Praxigento\Bonus\Base\Lib\Entity\Calculation;
use Praxigento\Bonus\Base\Lib\Entity\Period;
use Praxigento\Bonus\Hybrid\Lib\Service\IPeriod;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\Core\Service\Base\Call as BaseCall;
use Praxigento\Core\Tool\IPeriod as ToolPeriod;

class Call extends BaseCall implements IPeriod
{

    /** @var  \Praxigento\Core\Tool\IPeriod */
    protected $_toolPeriod;
    /** @var  Sub\Db */
    private $_subDb;
    /** @var  Sub\BasedCalcs */
    private $_subBasedCalcs;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Praxigento\Core\Tool\IPeriod $toolPeriod,
        Sub\Db $subDb,
        Sub\BasedCalcs $subBasedCalcs
    ) {
        parent::__construct($logger);
        $this->_toolPeriod = $toolPeriod;
        $this->_subDb = $subDb;
        $this->_subBasedCalcs = $subBasedCalcs;

    }

    /**
     * @param Request\GetForDependentCalc $request
     *
     * @return Response\GetForDependentCalc
     */
    public function getForDependentCalc(Request\GetForDependentCalc $request)
    {
        // $result = new Response\GetForDependentCalc();
        $dependentCalcTypeCode = $request->getDependentCalcTypeCode();
        $baseCalcTypeCode = $request->getBaseCalcTypeCode();
        $this->_logger->info("'Get latest period for Dependent Calculation' operation is started (dependent=$dependentCalcTypeCode, base=$baseCalcTypeCode).");
        $result = $this->_subBasedCalcs->getDependentCalcData($dependentCalcTypeCode, $baseCalcTypeCode);
        $this->_logger->info("'Get latest period for Dependent Calculation' operation is completed.");
        return $result;
    }

    /**
     * @param Request\GetForWriteOff $request
     *
     * @return Response\GetForWriteOff
     */
    public function getForWriteOff(Request\GetForWriteOff $request)
    {
        $result = new Response\GetForWriteOff();
        $this->_logger->info("'Get latest period for Write Off calculation' operation is started.");
        /* get the last Write Off period data */
        $calcWriteOffCode = Cfg::CODE_TYPE_CALC_PV_WRITE_OFF;
        $calcWriteOffId = $this->_subDb->getCalcIdByCode($calcWriteOffCode);
        $respWriteOffLastPeriod = $this->_subDb->getLastPeriodData($calcWriteOffId);
        $periodWriteOffData = $respWriteOffLastPeriod->getPeriodData();
        if (is_null($periodWriteOffData)) {
            $this->_logger->info("There is no period for PV Write Off calculation  yet.");
            /* calc period for PV Write Off */
            $ts = $this->_subDb->getFirstDateForPvTransactions();
            if ($ts === false) {
                $this->_logger->info("There is no PV transactions yet. Nothing to do.");
                $result->setHasNoPvTransactionsYet();
            } else {
                $this->_logger->info("First PV transaction was performed at '$ts'.");
                $periodMonth = $this->_toolPeriod->getPeriodCurrent($ts, ToolPeriod::TYPE_MONTH);
                $dsBegin = $this->_toolPeriod->getPeriodFirstDate($periodMonth);
                $dsEnd = $this->_toolPeriod->getPeriodLastDate($periodMonth);
                $periodWriteOffData = $this->_subDb->addNewPeriodAndCalc($calcWriteOffId, $dsBegin, $dsEnd);
                $result->setPeriodData($periodWriteOffData->getData(Sub\Db::DATA_PERIOD));
                $result->setCalcData($periodWriteOffData->getData(Sub\Db::DATA_CALC));
                $result->setAsSucceed();
            }
        } else {
            $result->setPeriodData($periodWriteOffData);
            $periodId = $periodWriteOffData[Period::ATTR_ID];
            $this->_logger->info("There is registered period #$periodId for '$calcWriteOffCode' calculation.");
            $calcData = $respWriteOffLastPeriod->getCalcData();
            if ($calcData === false) {
                $this->_logger->info("There is no calculation data for existing period. Use existing period data.");
                $result->setAsSucceed();
            } else {
                if (
                    is_array($calcData) &&
                    isset($calcData[Calculation::ATTR_STATE]) &&
                    ($calcData[Calculation::ATTR_STATE] == Cfg::CALC_STATE_COMPLETE)
                ) {
                    $this->_logger->info("There is complete calculation for existing period. Create new period.");
                    $periodEnd = $periodWriteOffData[Period::ATTR_DSTAMP_END];
                    /* calculate new period bounds */
                    $periodNext = $this->_toolPeriod->getPeriodNext($periodEnd, ToolPeriod::TYPE_MONTH);
                    $dsNextBegin = $this->_toolPeriod->getPeriodFirstDate($periodNext);
                    $dsNextEnd = $this->_toolPeriod->getPeriodLastDate($periodNext);
                    $periodWriteOffData = $this->_subDb->addNewPeriodAndCalc($calcWriteOffId, $dsNextBegin, $dsNextEnd);
                    $result->setPeriodData($periodWriteOffData->getData(Sub\Db::DATA_PERIOD));
                    $result->setCalcData($periodWriteOffData->getData(Sub\Db::DATA_CALC));
                    $result->setAsSucceed();
                } else {
                    $this->_logger->info("There is no complete calculation for existing period. Use existing period data.");
                    $result->setCalcData($calcData);
                    $result->setAsSucceed();
                }
            }
        }
        $this->_logger->info("'Get latest period for Write Off calculation' operation is completed.");
        return $result;
    }
}