<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\Bonus\Hybrid\Lib\Service\Period\Sub;

use Flancer32\Lib\DataObject;
use Praxigento\Bonus\Base\Lib\Entity\Calculation;
use Praxigento\Bonus\Base\Lib\Entity\Period;
use Praxigento\Bonus\Base\Lib\Service\Period\Response\GetLatest as BasePeriodGetLatestResponse;
use Praxigento\Bonus\Hybrid\Lib\Service\Period\Response\BasedOnCompression as BasedOnCompressionResponse;
use Praxigento\Bonus\Hybrid\Lib\Service\Period\Response\BasedOnPvWriteOff as BasedOnPvWriteOffResponse;
use Praxigento\BonusHybrid\Config as Cfg;

include_once(__DIR__ . '/../../../../phpunit_bootstrap.php');

class BasedCalcs_UnitTest extends \Praxigento\Core\Test\BaseMockeryCase
{
    protected function setUp()
    {
        parent::setUp();
        $this->markTestSkipped('Test is deprecated after M1 & M2 merge is done.');
    }

    public function test_getDependentCalcData_isBasePeriod_isBaseCalc_isDependPeriod_diffDates()
    {
        /** === Test Data === */
        $CALC_TYPE_CODE_BASE = Cfg::CODE_TYPE_CALC_VALUE_TV;
        $CALC_TYPE_CODE_DEPEND = Cfg::CODE_TYPE_CALC_BONUS_TEAM_DEF;
        $CALC_TYPE_ID_BASE = 4;
        $CALC_TYPE_ID_DEPEND = 16;
        $PERIOD_BEGIN = 'begin';
        $PERIOD_END = 'end';
        $PERIOD_DEPEND_ID = 32;
        $CALC_DEPEND_ID = 64;
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mToolbox = $this->_mockToolbox();
        $mSubDb = $this->_mockFor('\Praxigento\Bonus\Hybrid\Lib\Service\Period\Sub\Db');

        // $dependentCalcTypeId = $this->_subDb->getCalcIdByCode($dependentCalcTypeCode);
        $mSubDb
            ->expects($this->at(0))
            ->method('getCalcIdByCode')
            ->willReturn($CALC_TYPE_ID_DEPEND);
        // $baseCalcTypeId = $this->_subDb->getCalcIdByCode($baseCalcTypeCode);
        $mSubDb
            ->expects($this->at(1))
            ->method('getCalcIdByCode')
            ->willReturn($CALC_TYPE_ID_BASE);
        // $respBasePeriod = $this->_subDb->getLastPeriodData($baseCalcTypeId);
        $mRespBasePeriod = new BasePeriodGetLatestResponse();
        $mSubDb
            ->expects($this->at(2))
            ->method('getLastPeriodData')
            ->with($CALC_TYPE_ID_BASE)
            ->willReturn($mRespBasePeriod);
        // $basePeriodData = $respBasePeriod->getPeriodData();
        $mRespBasePeriod->setPeriodData([
            Period::ATTR_DSTAMP_BEGIN => $PERIOD_BEGIN,
            Period::ATTR_DSTAMP_END => $PERIOD_END
        ]);
        // $baseCalcData = $respBasePeriod->getCalcData();
        $mRespBasePeriod->setCalcData([
            Calculation::ATTR_STATE => Cfg::CALC_STATE_COMPLETE
        ]);
        // $respDependentPeriod = $this->_subDb->getLastPeriodData($dependentCalcTypeId);
        $mRespDependentPeriod = new BasePeriodGetLatestResponse();
        $mSubDb
            ->expects($this->at(3))
            ->method('getLastPeriodData')
            ->with($CALC_TYPE_ID_DEPEND)
            ->willReturn($mRespDependentPeriod);
        // $dependPeriodData = $respDependentPeriod->getPeriodData();
        $mRespDependentPeriod->setPeriodData([
            Period::ATTR_DSTAMP_BEGIN => $PERIOD_BEGIN . 'diff',
            Period::ATTR_DSTAMP_END => $PERIOD_END . 'diff'
        ]);
        // $dependentCalcData = $respDependentPeriod->getCalcData();
        $mRespDependentPeriod->setCalcData([]);
        // $dependPeriodData = $this->_subDb->addNewPeriodAndCalc($dependentCalcTypeId, $baseDsBegin, $baseDsEnd);
        $mDependPeriodData = new DataObject([
            Db::DATA_PERIOD => [Period::ATTR_ID => $PERIOD_DEPEND_ID],
            Db::DATA_CALC => [Calculation::ATTR_ID => $CALC_DEPEND_ID]
        ]);
        $mSubDb
            ->expects($this->at(4))
            ->method('addNewPeriodAndCalc')
            ->willReturn($mDependPeriodData);
        /**
         * Prepare request and perform call.
         */
        /** @var  $sub BasedCalcs */
        $sub = new BasedCalcs($mLogger, $mToolbox, $mSubDb);
        $resp = $sub->getDependentCalcData($CALC_TYPE_CODE_DEPEND, $CALC_TYPE_CODE_BASE);
        $this->assertTrue($resp->isSucceed());
        $this->assertEquals($PERIOD_DEPEND_ID, $resp->getData('DependentPeriodData/id'));
        $this->assertEquals($CALC_DEPEND_ID, $resp->getData('DependentCalcData/id'));
    }

    public function test_getDependentCalcData_isBasePeriod_isBaseCalc_isDependPeriod_sameDates_complete()
    {
        /** === Test Data === */
        $CALC_TYPE_CODE_BASE = Cfg::CODE_TYPE_CALC_VALUE_TV;
        $CALC_TYPE_CODE_DEPEND = Cfg::CODE_TYPE_CALC_BONUS_TEAM_DEF;
        $CALC_TYPE_ID_BASE = 4;
        $CALC_TYPE_ID_DEPEND = 16;
        $PERIOD_BEGIN = 'begin';
        $PERIOD_END = 'end';
        $PERIOD_DEPEND_ID = 32;
        $CALC_DEPEND_ID = 64;
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mToolbox = $this->_mockToolbox();
        $mSubDb = $this->_mockFor('\Praxigento\Bonus\Hybrid\Lib\Service\Period\Sub\Db');

        // $dependentCalcTypeId = $this->_subDb->getCalcIdByCode($dependentCalcTypeCode);
        $mSubDb
            ->expects($this->at(0))
            ->method('getCalcIdByCode')
            ->willReturn($CALC_TYPE_ID_DEPEND);
        // $baseCalcTypeId = $this->_subDb->getCalcIdByCode($baseCalcTypeCode);
        $mSubDb
            ->expects($this->at(1))
            ->method('getCalcIdByCode')
            ->willReturn($CALC_TYPE_ID_BASE);
        // $respBasePeriod = $this->_subDb->getLastPeriodData($baseCalcTypeId);
        $mRespBasePeriod = new BasePeriodGetLatestResponse();
        $mSubDb
            ->expects($this->at(2))
            ->method('getLastPeriodData')
            ->with($CALC_TYPE_ID_BASE)
            ->willReturn($mRespBasePeriod);
        // $basePeriodData = $respBasePeriod->getPeriodData();
        $mRespBasePeriod->setPeriodData([
            Period::ATTR_DSTAMP_BEGIN => $PERIOD_BEGIN,
            Period::ATTR_DSTAMP_END => $PERIOD_END
        ]);
        // $baseCalcData = $respBasePeriod->getCalcData();
        $mRespBasePeriod->setCalcData([
            Calculation::ATTR_STATE => Cfg::CALC_STATE_COMPLETE
        ]);
        // $respDependentPeriod = $this->_subDb->getLastPeriodData($dependentCalcTypeId);
        $mRespDependentPeriod = new BasePeriodGetLatestResponse();
        $mSubDb
            ->expects($this->at(3))
            ->method('getLastPeriodData')
            ->with($CALC_TYPE_ID_DEPEND)
            ->willReturn($mRespDependentPeriod);
        // $dependPeriodData = $respDependentPeriod->getPeriodData();
        $mRespDependentPeriod->setPeriodData([
            Period::ATTR_ID => $PERIOD_DEPEND_ID,
            Period::ATTR_DSTAMP_BEGIN => $PERIOD_BEGIN,
            Period::ATTR_DSTAMP_END => $PERIOD_END
        ]);
        // $dependentCalcData = $respDependentPeriod->getCalcData();
        $mRespDependentPeriod->setCalcData([
            Calculation::ATTR_ID => $CALC_DEPEND_ID,
            Calculation::ATTR_STATE => Cfg::CALC_STATE_COMPLETE
        ]);

        /**
         * Prepare request and perform call.
         */
        /** @var  $sub BasedCalcs */
        $sub = new BasedCalcs($mLogger, $mToolbox, $mSubDb);
        $resp = $sub->getDependentCalcData($CALC_TYPE_CODE_DEPEND, $CALC_TYPE_CODE_BASE);
        $this->assertFalse($resp->isSucceed());
    }

    public function test_getDependentCalcData_isBasePeriod_isBaseCalc_isDependPeriod_sameDates_incomplete()
    {
        /** === Test Data === */
        $CALC_TYPE_CODE_BASE = Cfg::CODE_TYPE_CALC_VALUE_TV;
        $CALC_TYPE_CODE_DEPEND = Cfg::CODE_TYPE_CALC_BONUS_TEAM_DEF;
        $CALC_TYPE_ID_BASE = 4;
        $CALC_TYPE_ID_DEPEND = 16;
        $PERIOD_BEGIN = 'begin';
        $PERIOD_END = 'end';
        $PERIOD_DEPEND_ID = 32;
        $CALC_DEPEND_ID = 64;
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mToolbox = $this->_mockToolbox();
        $mSubDb = $this->_mockFor('\Praxigento\Bonus\Hybrid\Lib\Service\Period\Sub\Db');

        // $dependentCalcTypeId = $this->_subDb->getCalcIdByCode($dependentCalcTypeCode);
        $mSubDb
            ->expects($this->at(0))
            ->method('getCalcIdByCode')
            ->willReturn($CALC_TYPE_ID_DEPEND);
        // $baseCalcTypeId = $this->_subDb->getCalcIdByCode($baseCalcTypeCode);
        $mSubDb
            ->expects($this->at(1))
            ->method('getCalcIdByCode')
            ->willReturn($CALC_TYPE_ID_BASE);
        // $respBasePeriod = $this->_subDb->getLastPeriodData($baseCalcTypeId);
        $mRespBasePeriod = new BasePeriodGetLatestResponse();
        $mSubDb
            ->expects($this->at(2))
            ->method('getLastPeriodData')
            ->with($CALC_TYPE_ID_BASE)
            ->willReturn($mRespBasePeriod);
        // $basePeriodData = $respBasePeriod->getPeriodData();
        $mRespBasePeriod->setPeriodData([
            Period::ATTR_DSTAMP_BEGIN => $PERIOD_BEGIN,
            Period::ATTR_DSTAMP_END => $PERIOD_END
        ]);
        // $baseCalcData = $respBasePeriod->getCalcData();
        $mRespBasePeriod->setCalcData([
            Calculation::ATTR_STATE => Cfg::CALC_STATE_COMPLETE
        ]);
        // $respDependentPeriod = $this->_subDb->getLastPeriodData($dependentCalcTypeId);
        $mRespDependentPeriod = new BasePeriodGetLatestResponse();
        $mSubDb
            ->expects($this->at(3))
            ->method('getLastPeriodData')
            ->with($CALC_TYPE_ID_DEPEND)
            ->willReturn($mRespDependentPeriod);
        // $dependPeriodData = $respDependentPeriod->getPeriodData();
        $mRespDependentPeriod->setPeriodData([
            Period::ATTR_ID => $PERIOD_DEPEND_ID,
            Period::ATTR_DSTAMP_BEGIN => $PERIOD_BEGIN,
            Period::ATTR_DSTAMP_END => $PERIOD_END
        ]);
        // $dependentCalcData = $respDependentPeriod->getCalcData();
        $mRespDependentPeriod->setCalcData([
            Calculation::ATTR_ID => $CALC_DEPEND_ID
        ]);

        /**
         * Prepare request and perform call.
         */
        /** @var  $sub BasedCalcs */
        $sub = new BasedCalcs($mLogger, $mToolbox, $mSubDb);
        $resp = $sub->getDependentCalcData($CALC_TYPE_CODE_DEPEND, $CALC_TYPE_CODE_BASE);
        $this->assertTrue($resp->isSucceed());
        $this->assertEquals($PERIOD_DEPEND_ID, $resp->getData('DependentPeriodData/id'));
        $this->assertEquals($CALC_DEPEND_ID, $resp->getData('DependentCalcData/id'));
    }

    public function test_getDependentCalcData_isBasePeriod_isBaseCalc_noDependPeriod()
    {
        /** === Test Data === */
        $CALC_TYPE_CODE_BASE = Cfg::CODE_TYPE_CALC_VALUE_TV;
        $CALC_TYPE_CODE_DEPEND = Cfg::CODE_TYPE_CALC_BONUS_TEAM_DEF;
        $CALC_TYPE_ID_BASE = 4;
        $CALC_TYPE_ID_DEPEND = 16;
        $PERIOD_BEGIN = 'begin';
        $PERIOD_END = 'end';
        $PERIOD_DEPEND_ID = 32;
        $CALC_DEPEND_ID = 64;
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mToolbox = $this->_mockToolbox();
        $mSubDb = $this->_mockFor('\Praxigento\Bonus\Hybrid\Lib\Service\Period\Sub\Db');

        // $dependentCalcTypeId = $this->_subDb->getCalcIdByCode($dependentCalcTypeCode);
        $mSubDb
            ->expects($this->at(0))
            ->method('getCalcIdByCode')
            ->willReturn($CALC_TYPE_ID_DEPEND);
        // $baseCalcTypeId = $this->_subDb->getCalcIdByCode($baseCalcTypeCode);
        $mSubDb
            ->expects($this->at(1))
            ->method('getCalcIdByCode')
            ->willReturn($CALC_TYPE_ID_BASE);
        // $respBasePeriod = $this->_subDb->getLastPeriodData($baseCalcTypeId);
        $mRespBasePeriod = new BasePeriodGetLatestResponse();
        $mSubDb
            ->expects($this->at(2))
            ->method('getLastPeriodData')
            ->with($CALC_TYPE_ID_BASE)
            ->willReturn($mRespBasePeriod);
        // $basePeriodData = $respBasePeriod->getPeriodData();
        $mRespBasePeriod->setPeriodData([
            Period::ATTR_DSTAMP_BEGIN => $PERIOD_BEGIN,
            Period::ATTR_DSTAMP_END => $PERIOD_END
        ]);
        // $baseCalcData = $respBasePeriod->getCalcData();
        $mRespBasePeriod->setCalcData([
            Calculation::ATTR_STATE => Cfg::CALC_STATE_COMPLETE
        ]);
        // $respDependentPeriod = $this->_subDb->getLastPeriodData($dependentCalcTypeId);
        $mRespDependentPeriod = new BasePeriodGetLatestResponse();
        $mSubDb
            ->expects($this->at(3))
            ->method('getLastPeriodData')
            ->with($CALC_TYPE_ID_DEPEND)
            ->willReturn($mRespDependentPeriod);
        // if(is_null($dependPeriodData)) {
        // $dependPeriodData = $this->_subDb->addNewPeriodAndCalc($dependentCalcTypeId, $baseDsBegin, $baseDsEnd);
        $mDependPeriodData = new DataObject([
            Db::DATA_PERIOD => [Period::ATTR_ID => $PERIOD_DEPEND_ID],
            Db::DATA_CALC => [Calculation::ATTR_ID => $CALC_DEPEND_ID]
        ]);
        $mSubDb
            ->expects($this->at(4))
            ->method('addNewPeriodAndCalc')
            ->willReturn($mDependPeriodData);
        /**
         * Prepare request and perform call.
         */
        /** @var  $sub BasedCalcs */
        $sub = new BasedCalcs($mLogger, $mToolbox, $mSubDb);
        $resp = $sub->getDependentCalcData($CALC_TYPE_CODE_DEPEND, $CALC_TYPE_CODE_BASE);
        $this->assertTrue($resp->isSucceed());
        $this->assertEquals($PERIOD_DEPEND_ID, $resp->getData('DependentPeriodData/id'));
        $this->assertEquals($CALC_DEPEND_ID, $resp->getData('DependentCalcData/id'));

    }

    public function test_getDependentCalcData_isBasePeriod_noBaseCalc()
    {
        /** === Test Data === */
        $CALC_CODE_BASE = Cfg::CODE_TYPE_CALC_VALUE_TV;
        $CALC_CODE_DEPEND = Cfg::CODE_TYPE_CALC_BONUS_TEAM_DEF;
        $CALC_ID_BASE = 4;
        $CALC_ID_DEPEND = 16;
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mToolbox = $this->_mockToolbox();
        $mSubDb = $this->_mockFor('\Praxigento\Bonus\Hybrid\Lib\Service\Period\Sub\Db');

        // $dependentCalcTypeId = $this->_subDb->getCalcIdByCode($dependentCalcTypeCode);
        $mSubDb
            ->expects($this->at(0))
            ->method('getCalcIdByCode')
            ->willReturn($CALC_ID_DEPEND);
        // $baseCalcTypeId = $this->_subDb->getCalcIdByCode($baseCalcTypeCode);
        $mSubDb
            ->expects($this->at(1))
            ->method('getCalcIdByCode')
            ->willReturn($CALC_ID_BASE);
        // $respBasePeriod = $this->_subDb->getLastPeriodData($baseCalcTypeId);
        $mRespBasePeriod = new BasePeriodGetLatestResponse();
        $mSubDb
            ->expects($this->once())
            ->method('getLastPeriodData')
            ->with($CALC_ID_BASE)
            ->willReturn($mRespBasePeriod);
        // $basePeriodData = $respBasePeriod->getPeriodData();
        $mRespBasePeriod->setPeriodData([
            Period::ATTR_DSTAMP_BEGIN => 'begin',
            Period::ATTR_DSTAMP_END => 'end'
        ]);
        /**
         * Prepare request and perform call.
         */
        /** @var  $sub BasedCalcs */
        $sub = new BasedCalcs($mLogger, $mToolbox, $mSubDb);
        $resp = $sub->getDependentCalcData($CALC_CODE_DEPEND, $CALC_CODE_BASE);
        $this->assertFalse($resp->isSucceed());
    }

    public function test_getDependentCalcData_noBasePeriod()
    {
        /** === Test Data === */
        $CALC_CODE_BASE = Cfg::CODE_TYPE_CALC_VALUE_TV;
        $CALC_CODE_DEPEND = Cfg::CODE_TYPE_CALC_BONUS_TEAM_DEF;
        $CALC_ID_BASE = 4;
        $CALC_ID_DEPEND = 16;
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mToolbox = $this->_mockToolbox();
        $mSubDb = $this->_mockFor('\Praxigento\Bonus\Hybrid\Lib\Service\Period\Sub\Db');

        // $dependentCalcTypeId = $this->_subDb->getCalcIdByCode($dependentCalcTypeCode);
        $mSubDb
            ->expects($this->at(0))
            ->method('getCalcIdByCode')
            ->willReturn($CALC_ID_DEPEND);
        // $baseCalcTypeId = $this->_subDb->getCalcIdByCode($baseCalcTypeCode);
        $mSubDb
            ->expects($this->at(1))
            ->method('getCalcIdByCode')
            ->willReturn($CALC_ID_BASE);
        // $respBasePeriod = $this->_subDb->getLastPeriodData($baseCalcTypeId);
        $mRespBasePeriod = new BasePeriodGetLatestResponse();
        $mSubDb
            ->expects($this->once())
            ->method('getLastPeriodData')
            ->with($CALC_ID_BASE)
            ->willReturn($mRespBasePeriod);
        /**
         * Prepare request and perform call.
         */
        /** @var  $sub BasedCalcs */
        $sub = new BasedCalcs($mLogger, $mToolbox, $mSubDb);
        $resp = $sub->getDependentCalcData($CALC_CODE_DEPEND, $CALC_CODE_BASE);
        $this->assertFalse($resp->isSucceed());
    }
}