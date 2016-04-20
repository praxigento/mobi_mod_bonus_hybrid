<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\Bonus\Hybrid\Lib\Service\Period;

use Flancer32\Lib\DataObject;
use Praxigento\Bonus\Base\Lib\Entity\Calculation;
use Praxigento\Bonus\Base\Lib\Entity\Period;
use Praxigento\Bonus\Base\Lib\Service\Period\Response\GetLatest as BonusBasePeriodGetLatestResponse;
use Praxigento\Bonus\Base\Lib\Service\Period\Response\GetLatest as PeriodGetLatestResponse;
use Praxigento\Bonus\Hybrid\Lib\Service\Period\Response\GetForDependentCalc as PeriodGetForDependentCalcResponse;
use Praxigento\BonusHybrid\Config as Cfg;

include_once(__DIR__ . '/../../../phpunit_bootstrap.php');

class Call_UnitTest extends \Praxigento\Core\Test\BaseMockeryCase
{
    protected function setUp()
    {
        parent::setUp();
        $this->markTestSkipped('Test is deprecated after M1 & M2 merge is done.');
    }

    public function test_getForDependentCalc()
    {
        /** === Test Data === */
        $CALC_TYPE_CODE_BASE = 1024;
        $CALC_TYPE_CODE_DEPEND = 512;
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolbox = $this->_mockToolbox();
        $mCallRepo = $this->_mockCallRepo();
        $mSubDb = $this->_mockFor('Praxigento\Bonus\Hybrid\Lib\Service\Period\Sub\Db');
        $mSubWriteOff = $this->_mockFor('\Praxigento\Bonus\Hybrid\Lib\Service\Period\Sub\BasedCalcs');

        // $result = $this->_subBasedCalcs->getDependentCalcData($dependentCalcTypeCode, $baseCalcTypeCode);
        $mResult = new PeriodGetForDependentCalcResponse();
        $mResult->markSucceed();
        $mSubWriteOff
            ->expects($this->once())
            ->method('getDependentCalcData')
            ->willReturn($mResult);

        /**
         * Prepare request and perform call.
         */
        /** @var  $call Call */
        $call = new Call(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mSubDb,
            $mSubWriteOff
        );
        $req = new Request\GetForDependentCalc();
        $req->setBaseCalcTypeCode($CALC_TYPE_CODE_BASE);
        $req->setDependentCalcTypeCode($CALC_TYPE_CODE_DEPEND);
        $resp = $call->getForDependentCalc($req);
        $this->assertTrue($resp->isSucceed());
    }

    public function test_getForWriteOff_isPeriodData_calcComplete()
    {
        /** === Test Data === */
        $CALC_TYPE_ID = 1024;
        $PERIOD_MONTH = '201512';
        $DS_BEGIN = '2015-12-01 00:00:00';
        $DS_END = '2015-12-31 23:59:59';
        $FOUND_PERIOD_ID = 2048;
        $FOUND_PERIOD_END = '20151231';
        $FOUND_CALC_ID = 4096;
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolPeriod = $this->_mockFor('Praxigento\Core\Tool\IPeriod');
        $mToolbox = $this->_mockToolbox(null, null, null, $mToolPeriod);
        $mCallRepo = $this->_mockCallRepo();
        $mSubDb = $this->_mockFor('\Praxigento\Bonus\Hybrid\Lib\Service\Period\Sub\Db');
        $mSubWriteOff = $this->_mockFor('\Praxigento\Bonus\Hybrid\Lib\Service\Period\Sub\BasedCalcs');

        // $calcWriteOffId = $this->_subDb->getCalcIdByCode($calcWriteOffCode);
        $mSubDb
            ->expects($this->once())
            ->method('getCalcIdByCode')
            ->willReturn($CALC_TYPE_ID);
        // $respWriteOffLastPeriod = $this->_subDb->getLastPeriodData($calcWriteOffId);
        $mRespLastPeriod = new BonusBasePeriodGetLatestResponse ();
        $mRespLastPeriod->markSucceed();
        $mRespLastPeriod->setPeriodData([
            Period::ATTR_ID => $FOUND_PERIOD_ID,
            Period::ATTR_DSTAMP_END => $FOUND_PERIOD_END
        ]);
        $mRespLastPeriod->setCalcData([
            Calculation::ATTR_ID => $FOUND_CALC_ID,
            Calculation::ATTR_STATE => Cfg::CALC_STATE_COMPLETE
        ]);
        $mSubDb
            ->expects($this->once())
            ->method('getLastPeriodData')
            ->willReturn($mRespLastPeriod);
        // $periodNext = $toolPeriod->getPeriodNext($periodEnd, ToolPeriod::TYPE_MONTH);
        $mToolPeriod
            ->expects($this->once())
            ->method('getPeriodNext')
            ->willReturn($PERIOD_MONTH);
        // $dsBegin = $toolPperiod->getPeriodFirstDate($periodMonth);
        $mToolPeriod
            ->expects($this->once())
            ->method('getPeriodFirstDate')
            ->with($PERIOD_MONTH)
            ->willReturn($DS_BEGIN);
        // $dsEnd = $toolPperiod->getPeriodLastDate($periodMonth);
        $mToolPeriod
            ->expects($this->once())
            ->method('getPeriodLastDate')
            ->with($PERIOD_MONTH)
            ->willReturn($DS_END);
        // $periodWriteOffData = $this->_subDb->addNewPeriodAndCalc($calcWriteOffId, $dsBegin, $dsEnd);
        $mSubDb
            ->expects($this->once())
            ->method('addNewPeriodAndCalc')
            ->willReturn(new DataObject());
        /**
         * Prepare request and perform call.
         */
        /** @var  $call Call */
        $call = new Call(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mSubDb,
            $mSubWriteOff
        );
        $req = new Request\GetForWriteOff();
        $resp = $call->getForWriteOff($req);
        $this->assertTrue($resp->isSucceed());
    }

    public function test_getForWriteOff_isPeriodData_calcIncomplete()
    {
        /** === Test Data === */
        $CALC_TYPE_ID = 1024;
        $FOUND_PERIOD_ID = 2048;
        $FOUND_PERIOD_END = '20151231';
        $FOUND_CALC_ID = 4096;
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolPeriod = $this->_mockFor('Praxigento\Core\Tool\IPeriod');
        $mToolbox = $this->_mockToolbox(null, null, null, $mToolPeriod);
        $mCallRepo = $this->_mockCallRepo();
        $mSubDb = $this->_mockFor('\Praxigento\Bonus\Hybrid\Lib\Service\Period\Sub\Db');
        $mSubWriteOff = $this->_mockFor('\Praxigento\Bonus\Hybrid\Lib\Service\Period\Sub\BasedCalcs');

        // $calcWriteOffId = $this->_subDb->getCalcIdByCode($calcWriteOffCode);
        $mSubDb
            ->expects($this->once())
            ->method('getCalcIdByCode')
            ->willReturn($CALC_TYPE_ID);
        // $respWriteOffLastPeriod = $this->_subDb->getLastPeriodData($calcWriteOffId);
        $mRespLastPeriod = new BonusBasePeriodGetLatestResponse ();
        $mRespLastPeriod->markSucceed();
        $mRespLastPeriod->setPeriodData([
            Period::ATTR_ID => $FOUND_PERIOD_ID,
            Period::ATTR_DSTAMP_END => $FOUND_PERIOD_END
        ]);
        $mRespLastPeriod->setCalcData([
            Calculation::ATTR_ID => $FOUND_CALC_ID,
            Calculation::ATTR_STATE => Cfg::CALC_STATE_STARTED
        ]);
        $mSubDb
            ->expects($this->once())
            ->method('getLastPeriodData')
            ->willReturn($mRespLastPeriod);
        /**
         * Prepare request and perform call.
         */
        /** @var  $call Call */
        $call = new Call(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mSubDb,
            $mSubWriteOff
        );
        $req = new Request\GetForWriteOff();
        $resp = $call->getForWriteOff($req);
        $this->assertTrue($resp->isSucceed());
        $this->assertTrue(is_array($resp->getPeriodData()));
        $this->assertTrue(is_array($resp->getCalcData()));
    }

    public function test_getForWriteOff_isPeriodData_noCalc()
    {
        /** === Test Data === */
        $CALC_TYPE_ID = 1024;
        $FOUND_PERIOD_ID = 2048;
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolPeriod = $this->_mockFor('Praxigento\Core\Tool\IPeriod');
        $mToolbox = $this->_mockToolbox(null, null, null, $mToolPeriod);
        $mCallRepo = $this->_mockCallRepo();
        $mSubDb = $this->_mockFor('\Praxigento\Bonus\Hybrid\Lib\Service\Period\Sub\Db');
        $mSubWriteOff = $this->_mockFor('\Praxigento\Bonus\Hybrid\Lib\Service\Period\Sub\BasedCalcs');

        // $calcWriteOffId = $this->_subDb->getCalcIdByCode($calcWriteOffCode);
        $mSubDb
            ->expects($this->once())
            ->method('getCalcIdByCode')
            ->willReturn($CALC_TYPE_ID);
        // $respWriteOffLastPeriod = $this->_subDb->getLastPeriodData($calcWriteOffId);
        $mRespLastPeriod = new BonusBasePeriodGetLatestResponse ();
        $mRespLastPeriod->markSucceed();
        $mRespLastPeriod->setPeriodData([
            Period::ATTR_ID => $FOUND_PERIOD_ID
        ]);
        $mRespLastPeriod->setCalcData(false);
        $mSubDb
            ->expects($this->once())
            ->method('getLastPeriodData')
            ->willReturn($mRespLastPeriod);
        /**
         * Prepare request and perform call.
         */
        /** @var  $call Call */
        $call = new Call(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mSubDb,
            $mSubWriteOff
        );
        $req = new Request\GetForWriteOff();
        $resp = $call->getForWriteOff($req);
        $this->assertTrue($resp->isSucceed());
    }

    public function test_getForWriteOff_noPeriodData_isTransactions()
    {
        /** === Test Data === */
        $CALC_TYPE_ID = 1024;
        $DATE_FIRST_PV_TRANS = 'first PV trans timestamp';
        $PERIOD_MONTH = '201512';
        $DS_BEGIN = '2015-12-01 00:00:00';
        $DS_END = '2015-12-31 23:59:59';
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolPeriod = $this->_mockFor('Praxigento\Core\Tool\IPeriod');
        $mToolbox = $this->_mockToolbox(null, null, null, $mToolPeriod);
        $mCallRepo = $this->_mockCallRepo();
        $mSubDb = $this->_mockFor('\Praxigento\Bonus\Hybrid\Lib\Service\Period\Sub\Db');
        $mSubWriteOff = $this->_mockFor('\Praxigento\Bonus\Hybrid\Lib\Service\Period\Sub\BasedCalcs');

        // $calcWriteOffId = $this->_subDb->getCalcIdByCode($calcWriteOffCode);
        $mSubDb
            ->expects($this->once())
            ->method('getCalcIdByCode')
            ->willReturn($CALC_TYPE_ID);
        // $respWriteOffLastPeriod = $this->_subDb->getLastPeriodData($calcWriteOffId);
        $mRespWriteOffLastPeriod = new PeriodGetLatestResponse();
        $mRespWriteOffLastPeriod->markSucceed();
        $mSubDb
            ->expects($this->once())
            ->method('getLastPeriodData')
            ->willReturn($mRespWriteOffLastPeriod);
        // $ts = $this->_subDb->getFirstDateForPvTransactions();
        $mSubDb
            ->expects($this->once())
            ->method('getFirstDateForPvTransactions')
            ->willReturn($DATE_FIRST_PV_TRANS);
        // $periodMonth = $toolPperiod->getPeriodCurrent($ts, ToolPeriod::TYPE_MONTH);
        $mToolPeriod
            ->expects($this->once())
            ->method('getPeriodCurrent')
            ->willReturn($PERIOD_MONTH);
        // $dsBegin = $toolPperiod->getPeriodFirstDate($periodMonth);
        $mToolPeriod
            ->expects($this->once())
            ->method('getPeriodFirstDate')
            ->willReturn($DS_BEGIN);
        // $dsEnd = $toolPperiod->getPeriodLastDate($periodMonth);
        $mToolPeriod
            ->expects($this->once())
            ->method('getPeriodLastDate')
            ->willReturn($DS_END);
        // $periodWriteOffData = $this->_subDb->addNewPeriodAndCalc($calcWriteOffId, $dsBegin, $dsEnd);
        $mSubDb
            ->expects($this->once())
            ->method('addNewPeriodAndCalc')
            ->willReturn(new DataObject());
        /**
         * Prepare request and perform call.
         */
        /** @var  $call Call */
        $call = new Call(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mSubDb,
            $mSubWriteOff
        );
        $req = new Request\GetForWriteOff();
        $resp = $call->getForWriteOff($req);
        $this->assertTrue($resp->isSucceed());
    }

    public function test_getForWriteOff_noPeriodData_noTransactions()
    {
        /** === Test Data === */
        $CALC_TYPE_ID = 1024;
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolPeriod = $this->_mockFor('Praxigento\Core\Tool\IPeriod');
        $mToolbox = $this->_mockToolbox(null, null, null, $mToolPeriod);
        $mCallRepo = $this->_mockCallRepo();
        $mSubDb = $this->_mockFor('Praxigento\Bonus\Hybrid\Lib\Service\Period\Sub\Db');
        $mSubWriteOff = $this->_mockFor('\Praxigento\Bonus\Hybrid\Lib\Service\Period\Sub\BasedCalcs');

        // $calcWriteOffId = $this->_subDb->getCalcIdByCode($calcWriteOffCode);
        $mSubDb
            ->expects($this->once())
            ->method('getCalcIdByCode')
            ->willReturn($CALC_TYPE_ID);
        // $respWriteOffLastPeriod = $this->_subDb->getLastPeriodData($calcWriteOffId);
        $mRespLastPeriod = new BonusBasePeriodGetLatestResponse ();
        $mRespLastPeriod->markSucceed();
        $mSubDb
            ->expects($this->once())
            ->method('getLastPeriodData')
            ->willReturn($mRespLastPeriod);
        // $ts = $this->_subDb->getFirstDateForPvTransactions();
        $mSubDb
            ->expects($this->once())
            ->method('getFirstDateForPvTransactions')
            ->willReturn(false);
        /**
         * Prepare request and perform call.
         */
        /** @var  $call Call */
        $call = new Call(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mSubDb,
            $mSubWriteOff
        );
        $req = new Request\GetForWriteOff();
        $resp = $call->getForWriteOff($req);
        $this->assertTrue($resp->isSucceed());
        $this->assertTrue($resp->hasNoPvTransactionsYet());
    }
}