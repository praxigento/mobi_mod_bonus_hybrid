<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\Bonus\Hybrid\Lib\Service\Period\Sub;

use Praxigento\Bonus\Hybrid\Lib\Service\Period\Response\BasedOnCompression as BasedOnCompressionResponse;
use Praxigento\Bonus\Hybrid\Lib\Service\Period\Response\BasedOnPvWriteOff as BasedOnPvWriteOffResponse;
use Praxigento\Bonus\Hybrid\Lib\Service\Period\Response\GetForDependentCalc as PeriodGetForDependentCalcResponse;
use Praxigento\BonusHybrid\Config as Cfg;

class BasedCalcs
{
    /** @var \Psr\Log\LoggerInterface */
    protected $_logger;
    /** @var  Db */
    private $_subDb;

    public function __construct(
        \Praxigento\Core\Fw\Logger\App $logger,
        Db $subDb
    ) {
        $this->_logger = $logger;
        $this->_subDb = $subDb;
    }

    /**
     *
     * @param $dependentCalcTypeCode
     * @param $baseCalcTypeCode
     *
     * @return PeriodGetForDependentCalcResponse
     */
    public function getDependentCalcData($dependentCalcTypeCode, $baseCalcTypeCode)
    {
        $result = new PeriodGetForDependentCalcResponse();
        /* get IDs for calculations codes */
        $dependentCalcTypeId = $this->_subDb->getCalcIdByCode($dependentCalcTypeCode);
        $baseCalcTypeId = $this->_subDb->getCalcIdByCode($baseCalcTypeCode);
        /* get the last base period data */
        $respBasePeriod = $this->_subDb->getLastPeriodData($baseCalcTypeId);
        $basePeriodData = $respBasePeriod->getPeriodData();
        if (is_null($basePeriodData)) {
            $this->_logger->warning("There is no period for '$baseCalcTypeCode' calculation  yet. '$dependentCalcTypeCode' could not be calculated.");
        } else {
            $result->setBasePeriodData($basePeriodData);
            $baseCalcData = $respBasePeriod->getCalcData();
            $result->setBaseCalcData($baseCalcData);
            $baseDsBegin = $basePeriodData->getDstampBegin();
            $baseDsEnd = $basePeriodData->getDstampEnd();
            if (
                $baseCalcData &&
                ($baseCalcData->getState() == Cfg::CALC_STATE_COMPLETE)
            ) {
                /* there is complete Base Calculation */
                $respDependentPeriod = $this->_subDb->getLastPeriodData($dependentCalcTypeId);
                $dependPeriodData = $respDependentPeriod->getPeriodData();
                $dependentCalcData = $respDependentPeriod->getCalcData();
                if (is_null($dependPeriodData)) {
                    /* there is no dependent period */
                    $this->_logger->warning("There is no period data for calculation '$dependentCalcTypeCode'. New period and related calculation will be created.");
                    $dependPeriodData = $this->_subDb->addNewPeriodAndCalc($dependentCalcTypeId, $baseDsBegin,
                        $baseDsEnd);
                    $result->setDependentPeriodData($dependPeriodData->getData(Db::DATA_PERIOD));
                    $result->setDependentCalcData($dependPeriodData->getData(Db::DATA_CALC));
                    $result->markSucceed();
                } else {
                    /* there is dependent period */
                    $dependentDsBegin = $dependPeriodData->getDstampBegin();
                    $dependentDsEnd = $dependPeriodData->getDstampEnd();
                    if (
                        ($dependentDsBegin == $baseDsBegin) &&
                        ($dependentDsEnd == $baseDsEnd)
                    ) {
                        /* dependent period has the same begin/end as related base period */
                        $this->_logger->info("There is base '$baseCalcTypeCode' period for dependent '$dependentCalcTypeCode' period ($dependentDsBegin-$dependentDsEnd).");
                        if (
                            $dependentCalcData &&
                            ($dependentCalcData->getState() == Cfg::CALC_STATE_COMPLETE)
                        ) {
                            /* complete dependent period for complete base period */
                            $this->_logger->warning("There is '$dependentCalcTypeCode' period with complete calculation. No more '$dependentCalcTypeCode' could be calculated.");
                        } else {
                            /* incomplete dependent period for complete base period */
                            $this->_logger->warning("There is '$dependentCalcTypeCode' period without complete calculation. Continue calculation for this period.");
                            $result->setDependentPeriodData($dependPeriodData);
                            $result->setDependentCalcData($dependentCalcData);
                            $result->markSucceed();
                        }
                    } else {
                        /* dependent period has different begin/end then related base period */
                        $this->_logger->warning("There is no period for '$dependentCalcTypeCode' calculation based on '$baseCalcTypeCode' ($baseDsBegin-$baseDsEnd). New period and related calculation will be created.");
                        $dependPeriodData = $this->_subDb->addNewPeriodAndCalc($dependentCalcTypeId, $baseDsBegin,
                            $baseDsEnd);
                        $result->setDependentPeriodData($dependPeriodData->getData(Db::DATA_PERIOD));
                        $result->setDependentCalcData($dependPeriodData->getData(Db::DATA_CALC));
                        $result->markSucceed();
                    }
                }
            } else {
                /* there is no complete Base Calculation */
                $this->_logger->warning("There is no complete base '$baseCalcTypeCode' calculation for dependent '$dependentCalcTypeCode' calculation. New period could not be created.");
            }
        }
        return $result;
    }


}