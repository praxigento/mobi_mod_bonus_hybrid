<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Service\Calc;

use Flancer32\Lib\Data as DataObject;
use Praxigento\BonusBase\Data\Entity\Calculation;
use Praxigento\BonusBase\Data\Entity\Period;
use Praxigento\BonusHybrid\Defaults as Def;
use Praxigento\BonusHybrid\Service\Calc\Sub\Calc;
use Praxigento\BonusHybrid\Service\Period\Response\GetForDependentCalc as BonusPersonalPeriodGetForDependentCalcResponse;
use Praxigento\BonusHybrid\Service\Period\Response\GetForWriteOff as BonusPersonalPeriodGetForWriteOffResponse;

include_once(__DIR__ . '/../../../phpunit_bootstrap.php');

class Call_UnitTest extends \Praxigento\BonusHybrid\Test\BaseTestCase
{
    const PV_COMPRESSION_LEVEL_DEF = 50;
    const PV_COMPRESSION_LEVEL_EU = 100;

    protected function setUp()
    {
        parent::setUp();
        $this->markTestSkipped('Test is deprecated after M1 & M2 merge is done.');
    }

    public function test_bonusCourtesy_isPeriod()
    {
        /** === Test Data === */
        $COURTESY_BONUS_PERCENT = 0.05;
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolbox = $this->_mockToolbox();
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Service\IAccount');
        $mCallBonusPersonalPeriod = $this->_mockFor('Praxigento\BonusHybrid\Service\IPeriod');
        $mSubDb = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Db');
        $mSubCalc = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Calc');

        // $respGetPeriod = $this->_callPeriod->getForDependentCalc($reqGetPeriod);
        $mRespGetPeriod = new BonusPersonalPeriodGetForDependentCalcResponse();
        $mCallBonusPersonalPeriod
            ->expects($this->once())
            ->method('getForDependentCalc')
            ->willReturn($mRespGetPeriod);
        // if($respGetPeriod->isSucceed())
        $mRespGetPeriod->markSucceed();
        // $updates = $this->_subCalc->bonusCourtesy($compressPtc, $courtesyPercent, $levelsPersonal, $levelsTeam);
        $mSubCalc
            ->expects($this->once())
            ->method('bonusCourtesy')
            ->willReturn([2 => [[Calc::A_VALUE => 10, Calc::A_OTHER_ID => 4]]]);
        //  $respAdd = $this->_subDb->saveOperationWalletActive(...)
        $mRespAdd = new DataObject();
        $mSubDb
            ->expects($this->once())
            ->method('saveOperationWalletActive')
            ->willReturn($mRespAdd);
        // $operId = $respAdd->getOperationId();
        $mRespAdd->setOperationId(21);
        // $transIds = $respAdd->getTransactionsIds();
        $mRespAdd->setTransactionsIds([]);
        // $this->_conn->commit();
        $mConn
            ->expects($this->once())
            ->method('commit');

        /**
         * Prepare request and perform call.
         */
        /** @var  $call Call */
        $call = new Call(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallBonusPersonalPeriod,
            $mSubDb,
            $mSubCalc
        );
        $req = new Request\BonusCourtesy();
        $req->setCourtesyBonusPercent($COURTESY_BONUS_PERCENT);
        $resp = $call->bonusCourtesy($req);
        $this->assertTrue($resp->isSucceed());
    }

    public function test_bonusCourtesy_isPeriod_exception()
    {
        /** === Test Data === */
        $COURTESY_BONUS_PERCENT = 0.05;
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolbox = $this->_mockToolbox();
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Service\IAccount');
        $mCallBonusPersonalPeriod = $this->_mockFor('Praxigento\BonusHybrid\Service\IPeriod');
        $mSubDb = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Db');
        $mSubCalc = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Calc');

        // $respGetPeriod = $this->_callPeriod->getForDependentCalc($reqGetPeriod);
        $mRespGetPeriod = new BonusPersonalPeriodGetForDependentCalcResponse();
        $mCallBonusPersonalPeriod
            ->expects($this->once())
            ->method('getForDependentCalc')
            ->willReturn($mRespGetPeriod);
        // if($respGetPeriod->isSucceed())
        $mRespGetPeriod->markSucceed();
        // $this->_getConn()->beginTransaction();
        $mConn
            ->expects($this->once())
            ->method('beginTransaction');
        //  $ptcCompressCalcId = $this->_subDb->getLastCalculationIdForPeriod(
        $mSubDb
            ->expects($this->once())
            ->method('getLastCalculationIdForPeriod')
            ->willThrowException(new \Exception());
        // $this->_getConn()->rollBack();
        $mConn
            ->expects($this->once())
            ->method('rollBack');
        /**
         * Prepare request and perform call.
         */
        /** @var  $call Call */
        $call = new Call(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallBonusPersonalPeriod,
            $mSubDb,
            $mSubCalc
        );
        $req = new Request\BonusCourtesy();
        $req->setCourtesyBonusPercent($COURTESY_BONUS_PERCENT);
        $resp = $call->bonusCourtesy($req);
        $this->assertFalse($resp->isSucceed());
    }

    public function test_bonusCourtesy_noPeriod()
    {
        /** === Test Data === */
        $COURTESY_BONUS_PERCENT = 0.05;
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolbox = $this->_mockToolbox();
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Service\IAccount');
        $mCallBonusPersonalPeriod = $this->_mockFor('Praxigento\BonusHybrid\Service\IPeriod');
        $mSubDb = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Db');
        $mSubCalc = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Calc');

        // $respGetPeriod = $this->_callPeriod->getForDependentCalc($reqGetPeriod);
        $mRespGetPeriod = new BonusPersonalPeriodGetForDependentCalcResponse();
        $mCallBonusPersonalPeriod
            ->expects($this->once())
            ->method('getForDependentCalc')
            ->willReturn($mRespGetPeriod);

        /**
         * Prepare request and perform call.
         */
        /** @var  $call Call */
        $call = new Call(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallBonusPersonalPeriod,
            $mSubDb,
            $mSubCalc
        );
        $req = new Request\BonusCourtesy();
        $req->setCourtesyBonusPercent($COURTESY_BONUS_PERCENT);
        $resp = $call->bonusCourtesy($req);
        $this->assertFalse($resp->isSucceed());
    }

    public function test_bonusInfinity_isPeriod_Def()
    {
        /** === Test Data === */
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolbox = $this->_mockToolbox();
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Service\IAccount');
        $mCallBonusPersonalPeriod = $this->_mockFor('Praxigento\BonusHybrid\Service\IPeriod');
        $mSubDb = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Db');
        $mSubCalc = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Calc');

        // $respGetPeriod = $this->_callPeriod->getForDependentCalc($reqGetPeriod);
        $mRespGetPeriod = new BonusPersonalPeriodGetForDependentCalcResponse();
        $mCallBonusPersonalPeriod
            ->expects($this->once())
            ->method('getForDependentCalc')
            ->willReturn($mRespGetPeriod);
        // if($respGetPeriod->isSucceed())
        $mRespGetPeriod->markSucceed();
        // $updates = $this->_subCalc->bonusOverride($compressOi, $scheme, $cfgOverride);
        $mSubCalc
            ->expects($this->once())
            ->method('bonusInfinity')
            ->willReturn([
                [
                    Calc::A_CUST_ID => 1,
                    Calc::A_PV => 100,
                    Calc::A_ENTRIES => [
                        [Calc::A_VALUE => 10, Calc::A_OTHER_ID => 4]
                    ]
                ]
            ]);
        // $respAdd = $this->_subDb->saveOperationWalletActive(...)
        $mRespAdd = new DataObject();
        $mSubDb
            ->expects($this->once())
            ->method('saveOperationWalletActive')
            ->willReturn($mRespAdd);
        //  $operId = $respAdd->getOperationId();
        $mRespAdd->getOperationId(32);
        $mRespAdd->getTransactionsIds([2, 3]);
        // $this->_conn->commit();
        $mConn
            ->expects($this->once())
            ->method('commit');

        /**
         * Prepare request and perform call.
         */
        /** @var  $call Call */
        $call = new Call(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallBonusPersonalPeriod,
            $mSubDb,
            $mSubCalc
        );
        $req = new Request\BonusInfinity();
        $resp = $call->bonusInfinity($req);
        $this->assertTrue($resp->isSucceed());
    }

    public function test_bonusInfinity_isPeriod_exception()
    {
        /** === Test Data === */
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolbox = $this->_mockToolbox();
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Service\IAccount');
        $mCallBonusPersonalPeriod = $this->_mockFor('Praxigento\BonusHybrid\Service\IPeriod');
        $mSubDb = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Db');
        $mSubCalc = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Calc');

        // $respGetPeriod = $this->_callPeriod->getForDependentCalc($reqGetPeriod);
        $mRespGetPeriod = new BonusPersonalPeriodGetForDependentCalcResponse();
        $mCallBonusPersonalPeriod
            ->expects($this->once())
            ->method('getForDependentCalc')
            ->willReturn($mRespGetPeriod);
        // if($respGetPeriod->isSucceed())
        $mRespGetPeriod->markSucceed();
        // $updates = $this->_subCalc->bonusInfinity($compressOi, $scheme, $cfgOverride);
        $mSubCalc
            ->expects($this->once())
            ->method('bonusInfinity')
            ->willThrowException(new \Exception());
        // $this->_conn->rollback();
        $mConn
            ->expects($this->once())
            ->method('rollback');

        /**
         * Prepare request and perform call.
         */
        /** @var  $call Call */
        $call = new Call(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallBonusPersonalPeriod,
            $mSubDb,
            $mSubCalc
        );
        $req = new Request\BonusInfinity();
        $resp = $call->bonusInfinity($req);
        $this->assertFalse($resp->isSucceed());
    }

    public function test_bonusInfinity_noPeriod_Eu()
    {
        /** === Test Data === */
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolbox = $this->_mockToolbox();
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Service\IAccount');
        $mCallBonusPersonalPeriod = $this->_mockFor('Praxigento\BonusHybrid\Service\IPeriod');
        $mSubDb = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Db');
        $mSubCalc = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Calc');

        // $respGetPeriod = $this->_callPeriod->getForDependentCalc($reqGetPeriod);
        $mRespGetPeriod = new BonusPersonalPeriodGetForDependentCalcResponse();
        $mCallBonusPersonalPeriod
            ->expects($this->once())
            ->method('getForDependentCalc')
            ->willReturn($mRespGetPeriod);

        /**
         * Prepare request and perform call.
         */
        /** @var  $call Call */
        $call = new Call(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallBonusPersonalPeriod,
            $mSubDb,
            $mSubCalc
        );
        $req = new Request\BonusInfinity();
        $req->setScheme(Def::SCHEMA_EU);
        $resp = $call->bonusInfinity($req);
        $this->assertFalse($resp->isSucceed());
    }

    public function test_bonusOverride_isPeriod_Def()
    {
        /** === Test Data === */
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolbox = $this->_mockToolbox();
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Service\IAccount');
        $mCallBonusPersonalPeriod = $this->_mockFor('Praxigento\BonusHybrid\Service\IPeriod');
        $mSubDb = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Db');
        $mSubCalc = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Calc');

        // $respGetPeriod = $this->_callPeriod->getForDependentCalc($reqGetPeriod);
        $mRespGetPeriod = new BonusPersonalPeriodGetForDependentCalcResponse();
        $mCallBonusPersonalPeriod
            ->expects($this->once())
            ->method('getForDependentCalc')
            ->willReturn($mRespGetPeriod);
        // if($respGetPeriod->isSucceed())
        $mRespGetPeriod->markSucceed();
        // $updates = $this->_subCalc->bonusOverride($compressOi, $scheme, $cfgOverride);
        $mSubCalc
            ->expects($this->once())
            ->method('bonusOverride')
            ->willReturn([
                [
                    Calc::A_CUST_ID => 1,
                    Calc::A_ENTRIES => [
                        [Calc::A_VALUE => 10, Calc::A_OTHER_ID => 4]
                    ]
                ]
            ]);
        // $respAdd = $this->_subDb->saveOperationWalletActive(...)
        $mRespAdd = new DataObject();
        $mSubDb
            ->expects($this->once())
            ->method('saveOperationWalletActive')
            ->willReturn($mRespAdd);
        //  $operId = $respAdd->getOperationId();
        $mRespAdd->getOperationId(32);
        $mRespAdd->getTransactionsIds([2, 3]);
        // $this->_conn->commit();
        $mConn
            ->expects($this->once())
            ->method('commit');

        /**
         * Prepare request and perform call.
         */
        /** @var  $call Call */
        $call = new Call(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallBonusPersonalPeriod,
            $mSubDb,
            $mSubCalc
        );
        $req = new Request\BonusOverride();
        $resp = $call->bonusOverride($req);
        $this->assertTrue($resp->isSucceed());
    }

    public function test_bonusOverride_isPeriod_exception()
    {
        /** === Test Data === */
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolbox = $this->_mockToolbox();
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Service\IAccount');
        $mCallBonusPersonalPeriod = $this->_mockFor('Praxigento\BonusHybrid\Service\IPeriod');
        $mSubDb = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Db');
        $mSubCalc = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Calc');

        // $respGetPeriod = $this->_callPeriod->getForDependentCalc($reqGetPeriod);
        $mRespGetPeriod = new BonusPersonalPeriodGetForDependentCalcResponse();
        $mCallBonusPersonalPeriod
            ->expects($this->once())
            ->method('getForDependentCalc')
            ->willReturn($mRespGetPeriod);
        // if($respGetPeriod->isSucceed())
        $mRespGetPeriod->markSucceed();
        // $updates = $this->_subCalc->bonusOverride($compressOi, $scheme, $cfgOverride);
        $mSubCalc
            ->expects($this->once())
            ->method('bonusOverride')
            ->willThrowException(new \Exception());
        // $this->_conn->rollback();
        $mConn
            ->expects($this->once())
            ->method('rollback');

        /**
         * Prepare request and perform call.
         */
        /** @var  $call Call */
        $call = new Call(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallBonusPersonalPeriod,
            $mSubDb,
            $mSubCalc
        );
        $req = new Request\BonusOverride();
        $resp = $call->bonusOverride($req);
        $this->assertFalse($resp->isSucceed());
    }

    public function test_bonusOverride_noPeriod_Eu()
    {
        /** === Test Data === */
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolbox = $this->_mockToolbox();
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Service\IAccount');
        $mCallBonusPersonalPeriod = $this->_mockFor('Praxigento\BonusHybrid\Service\IPeriod');
        $mSubDb = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Db');
        $mSubCalc = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Calc');

        // $respGetPeriod = $this->_callPeriod->getForDependentCalc($reqGetPeriod);
        $mRespGetPeriod = new BonusPersonalPeriodGetForDependentCalcResponse();
        $mCallBonusPersonalPeriod
            ->expects($this->once())
            ->method('getForDependentCalc')
            ->willReturn($mRespGetPeriod);

        /**
         * Prepare request and perform call.
         */
        /** @var  $call Call */
        $call = new Call(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallBonusPersonalPeriod,
            $mSubDb,
            $mSubCalc
        );
        $req = new Request\BonusOverride();
        $req->setScheme(Def::SCHEMA_EU);
        $resp = $call->bonusOverride($req);
        $this->assertFalse($resp->isSucceed());
    }

    public function test_bonusPersonal_isPeriod_Def()
    {
        /** === Test Data === */
        $PERIOD_BONUS_ID = 1;
        $CALC_BONUS_ID = 2;
        $OPERATION_ID = 3;
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolDate = $this->_mockFor('Praxigento\Core\Tool\IDate');
        $mToolPeriod = $this->_mockFor('Praxigento\Core\Tool\IPeriod');
        $mToolbox = $this->_mockToolbox(null, $mToolDate, null, $mToolPeriod);
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Service\IAccount');
        $mCallBonusPersonalPeriod = $this->_mockFor('Praxigento\BonusHybrid\Service\IPeriod');
        $mSubDb = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Db');
        $mSubCalc = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Calc');

        // $respGetPeriod = $this->_callPeriod->getForDependentCalc($reqGetPeriod);
        $mRespGetPeriod = new BonusPersonalPeriodGetForDependentCalcResponse();
        $mCallBonusPersonalPeriod
            ->expects($this->once())
            ->method('getForDependentCalc')
            ->willReturn($mRespGetPeriod);
        // if($respGetPeriod->isSucceed())
        $mRespGetPeriod->markSucceed();
        // $this->_conn->beginTransaction();
        $mConn
            ->expects($this->once())
            ->method('beginTransaction');
        // $persBonusCalcData = $respGetPeriod->getDependentCalcData();
        // $persBonusCalcId = $persBonusCalcData[Calculation::ATTR_ID];
        $mRespGetPeriod->setDependentCalcData([
            Calculation::ATTR_ID => $CALC_BONUS_ID
        ]);
        // $persBonusPeriodData = $respGetPeriod->getDependentPeriodData();
        // $persBonusPeriodId = $persBonusPeriodData[Period::ATTR_ID];
        $mRespGetPeriod->setDependentPeriodData([
            Period::ATTR_ID => $PERIOD_BONUS_ID,
            Period::ATTR_DSTAMP_BEGIN => 'from',
            Period::ATTR_DSTAMP_END => 'to'
        ]);
        // $pvCompressPeriodData = $respGetPeriod->getBasePeriodData();
        // $pvCompressDsBegin = $pvCompressPeriodData[Period::ATTR_DSTAMP_BEGIN];
        // $pvCompressDsEnd = $pvCompressPeriodData[Period::ATTR_DSTAMP_END];
        $mRespGetPeriod->setBasePeriodData([
            Period::ATTR_DSTAMP_BEGIN => 'from',
            Period::ATTR_DSTAMP_END => 'to'
        ]);
        // $respAdd = $this->_subDb->saveOperationWalletActive(..)
        $mRespAdd = new DataObject();
        $mSubDb
            ->expects($this->once())
            ->method('saveOperationWalletActive')
            ->willReturn($mRespAdd);
        // $operId = $respAdd->getOperationId();
        $mRespAdd->setOperationId($OPERATION_ID);
        /**
         * Prepare request and perform call.
         */
        /** @var  $call Call */
        $call = new Call(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallBonusPersonalPeriod,
            $mSubDb,
            $mSubCalc
        );
        $req = new Request\BonusPersonal();
        $resp = $call->bonusPersonal($req);
        $this->assertTrue($resp->isSucceed());
        $this->assertEquals($PERIOD_BONUS_ID, $resp->getPeriodId());
        $this->assertEquals($CALC_BONUS_ID, $resp->getCalcId());
    }

    public function test_bonusPersonal_isPeriod_Eu()
    {
        /** === Test Data === */
        $PERIOD_BONUS_ID = 1;
        $CALC_BONUS_ID = 2;
        $OPERATION_ID = 3;
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolDate = $this->_mockFor('Praxigento\Core\Tool\IDate');
        $mToolPeriod = $this->_mockFor('Praxigento\Core\Tool\IPeriod');
        $mToolbox = $this->_mockToolbox(null, $mToolDate, null, $mToolPeriod);
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Service\IAccount');
        $mCallBonusPersonalPeriod = $this->_mockFor('Praxigento\BonusHybrid\Service\IPeriod');
        $mSubDb = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Db');
        $mSubCalc = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Calc');

        // $respGetPeriod = $this->_callPeriod->getForDependentCalc($reqGetPeriod);
        $mRespGetPeriod = new BonusPersonalPeriodGetForDependentCalcResponse();
        $mCallBonusPersonalPeriod
            ->expects($this->once())
            ->method('getForDependentCalc')
            ->willReturn($mRespGetPeriod);
        // if($respGetPeriod->isSucceed())
        $mRespGetPeriod->markSucceed();
        // $this->_conn->beginTransaction();
        $mConn
            ->expects($this->once())
            ->method('beginTransaction');
        // $persBonusCalcData = $respGetPeriod->getDependentCalcData();
        // $persBonusCalcId = $persBonusCalcData[Calculation::ATTR_ID];
        $mRespGetPeriod->setDependentCalcData([
            Calculation::ATTR_ID => $CALC_BONUS_ID
        ]);
        // $persBonusPeriodData = $respGetPeriod->getDependentPeriodData();
        // $persBonusPeriodId = $persBonusPeriodData[Period::ATTR_ID];
        $mRespGetPeriod->setDependentPeriodData([
            Period::ATTR_ID => $PERIOD_BONUS_ID,
            Period::ATTR_DSTAMP_BEGIN => 'from',
            Period::ATTR_DSTAMP_END => 'to'
        ]);
        // $pvCompressPeriodData = $respGetPeriod->getBasePeriodData();
        // $pvCompressDsBegin = $pvCompressPeriodData[Period::ATTR_DSTAMP_BEGIN];
        // $pvCompressDsEnd = $pvCompressPeriodData[Period::ATTR_DSTAMP_END];
        $mRespGetPeriod->setBasePeriodData([
            Period::ATTR_DSTAMP_BEGIN => 'from',
            Period::ATTR_DSTAMP_END => 'to'
        ]);
        // $respAdd = $this->_subDb->saveOperationWalletActive(..)
        $mRespAdd = new DataObject();
        $mSubDb
            ->expects($this->once())
            ->method('saveOperationWalletActive')
            ->willReturn($mRespAdd);
        // $operId = $respAdd->getOperationId();
        $mRespAdd->setOperationId($OPERATION_ID);
        /**
         * Prepare request and perform call.
         */
        /** @var  $call Call */
        $call = new Call(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallBonusPersonalPeriod,
            $mSubDb,
            $mSubCalc
        );
        $req = new Request\BonusPersonal();
        $req->setScheme(Def::SCHEMA_EU);
        $resp = $call->bonusPersonal($req);
        $this->assertTrue($resp->isSucceed());
        $this->assertEquals($PERIOD_BONUS_ID, $resp->getPeriodId());
        $this->assertEquals($CALC_BONUS_ID, $resp->getCalcId());
    }

    public function test_bonusPersonal_isPeriod_exception()
    {
        /** === Test Data === */
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolDate = $this->_mockFor('Praxigento\Core\Tool\IDate');
        $mToolPeriod = $this->_mockFor('Praxigento\Core\Tool\IPeriod');
        $mToolbox = $this->_mockToolbox(null, $mToolDate, null, $mToolPeriod);
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Service\IAccount');
        $mCallBonusPersonalPeriod = $this->_mockFor('Praxigento\BonusHybrid\Service\IPeriod');
        $mSubDb = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Db');
        $mSubCalc = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Calc');

        // $respGetPeriod = $this->_callPeriod->getForDependentCalc($reqGetPeriod);
        $mRespGetPeriod = new BonusPersonalPeriodGetForDependentCalcResponse();
        $mCallBonusPersonalPeriod
            ->expects($this->once())
            ->method('getForDependentCalc')
            ->willReturn($mRespGetPeriod);
        // if($respGetPeriod->isSucceed())
        $mRespGetPeriod->markSucceed();
        // $this->_conn->beginTransaction();
        $mConn
            ->expects($this->once())
            ->method('beginTransaction');
        // $compressPtc = $this->_subDb->getCompressedPtcData($baseCalcId);
        $mSubDb
            ->expects($this->once())
            ->method('getCompressedPtcData')
            ->willThrowException(new \Exception());
        /**
         * Prepare request and perform call.
         */
        /** @var  $call Call */
        $call = new Call(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallBonusPersonalPeriod,
            $mSubDb,
            $mSubCalc
        );
        $req = new Request\BonusPersonal();
        $resp = $call->bonusPersonal($req);
        $this->assertFalse($resp->isSucceed());
    }

    public function test_bonusPersonal_noPeriod()
    {
        /** === Test Data === */
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolbox = $this->_mockToolbox();
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Service\IAccount');
        $mCallBonusPersonalPeriod = $this->_mockFor('Praxigento\BonusHybrid\Service\IPeriod');
        $mSubDb = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Db');
        $mSubCalc = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Calc');

        // $respGetPeriod = $this->_callPeriod->getForDependentCalc($reqGetPeriod);
        $mRespGetPeriod = new BonusPersonalPeriodGetForDependentCalcResponse();
        $mCallBonusPersonalPeriod
            ->expects($this->once())
            ->method('getForDependentCalc')
            ->willReturn($mRespGetPeriod);

        /**
         * Prepare request and perform call.
         */
        /** @var  $call Call */
        $call = new Call(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallBonusPersonalPeriod,
            $mSubDb,
            $mSubCalc
        );
        $req = new Request\BonusPersonal();
        $resp = $call->bonusPersonal($req);
        $this->assertFalse($resp->isSucceed());
    }

    public function test_bonusTeam_isPeriod_Def()
    {
        /** === Test Data === */
        $OPERATION_ID = 3;
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolbox = $this->_mockToolbox();
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Service\IAccount');
        $mCallBonusPersonalPeriod = $this->_mockFor('Praxigento\BonusHybrid\Service\IPeriod');
        $mSubDb = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Db');
        $mSubCalc = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Calc');

        // $respGetPeriod = $this->_callPeriod->getForDependentCalc($reqGetPeriod);
        $mRespGetPeriod = new BonusPersonalPeriodGetForDependentCalcResponse();
        $mCallBonusPersonalPeriod
            ->expects($this->once())
            ->method('getForDependentCalc')
            ->willReturn($mRespGetPeriod);
        // if($respGetPeriod->isSucceed())
        $mRespGetPeriod->markSucceed();
        // $respAdd = $this->_subDb->saveOperationWalletActive(..)
        $mRespAdd = new DataObject();
        $mSubDb
            ->expects($this->once())
            ->method('saveOperationWalletActive')
            ->willReturn($mRespAdd);
        // $operId = $respAdd->getOperationId();
        $mRespAdd->setOperationId($OPERATION_ID);
        // $this->_conn->commit();
        $mConn
            ->expects($this->once())
            ->method('commit');
        /**
         * Prepare request and perform call.
         */
        /** @var  $call Call */
        $call = new Call(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallBonusPersonalPeriod,
            $mSubDb,
            $mSubCalc
        );
        $req = new Request\BonusTeam();
        $resp = $call->bonusTeam($req);
        $this->assertTrue($resp->isSucceed());
    }

    public function test_bonusTeam_isPeriod_Eu()
    {
        /** === Test Data === */
        $OPERATION_ID = 3;
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolbox = $this->_mockToolbox();
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Service\IAccount');
        $mCallBonusPersonalPeriod = $this->_mockFor('Praxigento\BonusHybrid\Service\IPeriod');
        $mSubDb = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Db');
        $mSubCalc = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Calc');

        // $respGetPeriod = $this->_callPeriod->getForDependentCalc($reqGetPeriod);
        $mRespGetPeriod = new BonusPersonalPeriodGetForDependentCalcResponse();
        $mCallBonusPersonalPeriod
            ->expects($this->once())
            ->method('getForDependentCalc')
            ->willReturn($mRespGetPeriod);
        // if($respGetPeriod->isSucceed())
        $mRespGetPeriod->markSucceed();
        // $respAdd = $this->_subDb->saveOperationWalletActive(..)
        $mRespAdd = new DataObject();
        $mSubDb
            ->expects($this->once())
            ->method('saveOperationWalletActive')
            ->willReturn($mRespAdd);
        // $operId = $respAdd->getOperationId();
        $mRespAdd->setOperationId($OPERATION_ID);
        // $this->_conn->commit();
        $mConn
            ->expects($this->once())
            ->method('commit');
        /**
         * Prepare request and perform call.
         */
        /** @var  $call Call */
        $call = new Call(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallBonusPersonalPeriod,
            $mSubDb,
            $mSubCalc
        );
        $req = new Request\BonusTeam();
        $req->setScheme(Def::SCHEMA_EU);
        $resp = $call->bonusTeam($req);
        $this->assertTrue($resp->isSucceed());
    }

    public function test_bonusTeam_isPeriod_exception()
    {
        /** === Test Data === */
        $OPERATION_ID = 3;
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolbox = $this->_mockToolbox();
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Service\IAccount');
        $mCallBonusPersonalPeriod = $this->_mockFor('Praxigento\BonusHybrid\Service\IPeriod');
        $mSubDb = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Db');
        $mSubCalc = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Calc');

        // $respGetPeriod = $this->_callPeriod->getForDependentCalc($reqGetPeriod);
        $mRespGetPeriod = new BonusPersonalPeriodGetForDependentCalcResponse();
        $mCallBonusPersonalPeriod
            ->expects($this->once())
            ->method('getForDependentCalc')
            ->willReturn($mRespGetPeriod);
        // if($respGetPeriod->isSucceed())
        $mRespGetPeriod->markSucceed();
        // $respAdd = $this->_subDb->saveOperationWalletActive(..)
        $mRespAdd = new DataObject();
        $mSubDb
            ->expects($this->once())
            ->method('saveOperationWalletActive')
            ->willReturn($mRespAdd);
        // $operId = $respAdd->getOperationId();
        $mRespAdd->setOperationId($OPERATION_ID);
        // $this->_conn->commit();
        $mConn
            ->expects($this->once())
            ->method('commit')
            ->willThrowException(new \Exception());
        /**
         * Prepare request and perform call.
         */
        /** @var  $call Call */
        $call = new Call(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallBonusPersonalPeriod,
            $mSubDb,
            $mSubCalc
        );
        $req = new Request\BonusTeam();
        $resp = $call->bonusTeam($req);
        $this->assertFalse($resp->isSucceed());
    }

    public function test_bonusTeam_noPeriod()
    {
        /** === Test Data === */
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolbox = $this->_mockToolbox();
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Service\IAccount');
        $mCallBonusPersonalPeriod = $this->_mockFor('Praxigento\BonusHybrid\Service\IPeriod');
        $mSubDb = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Db');
        $mSubCalc = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Calc');

        // $respGetPeriod = $this->_callPeriod->getForDependentCalc($reqGetPeriod);
        $mRespGetPeriod = new BonusPersonalPeriodGetForDependentCalcResponse();
        $mCallBonusPersonalPeriod
            ->expects($this->once())
            ->method('getForDependentCalc')
            ->willReturn($mRespGetPeriod);

        /**
         * Prepare request and perform call.
         */
        /** @var  $call Call */
        $call = new Call(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallBonusPersonalPeriod,
            $mSubDb,
            $mSubCalc
        );
        $req = new Request\BonusTeam();
        $resp = $call->bonusTeam($req);
        $this->assertFalse($resp->isSucceed());
    }

    public function test_compressOi_isPeriod_Def()
    {
        /** === Test Data === */
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolbox = $this->_mockToolbox();
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Service\IAccount');
        $mCallBonusPersonalPeriod = $this->_mockFor('Praxigento\BonusHybrid\Service\IPeriod');
        $mSubDb = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Db');
        $mSubCalc = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Calc');

        // $respGetPeriod = $this->_callPeriod->getForDependentCalc($reqGetPeriod);
        $mRespGetPeriod = new BonusPersonalPeriodGetForDependentCalcResponse();
        $mCallBonusPersonalPeriod
            ->expects($this->once())
            ->method('getForDependentCalc')
            ->willReturn($mRespGetPeriod);
        // if($respGetPeriod->isSucceed())
        $mRespGetPeriod->markSucceed();
        // $this->_conn->commit();
        $mConn
            ->expects($this->once())
            ->method('commit');
        /**
         * Prepare request and perform call.
         */
        /** @var  $call Call */
        $call = new Call(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallBonusPersonalPeriod,
            $mSubDb,
            $mSubCalc
        );
        $req = new Request\CompressOi();
        $resp = $call->compressOi($req);
        $this->assertTrue($resp->isSucceed());
    }

    public function test_compressOi_isPeriod_Eu_exception()
    {
        /** === Test Data === */
        $CALC_ID = 21;
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolDate = $this->_mockFor('Praxigento\Core\Tool\IDate');
        $mToolPeriod = $this->_mockFor('Praxigento\Core\Tool\IPeriod');
        $mToolbox = $this->_mockToolbox(null, $mToolDate, null, $mToolPeriod);
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Service\IAccount');
        $mCallBonusPersonalPeriod = $this->_mockFor('Praxigento\BonusHybrid\Service\IPeriod');
        $mSubDb = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Db');
        $mSubCalc = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Calc');

        // $respGetPeriod = $this->_callPeriod->getForDependentCalc($reqGetPeriod);
        $mRespGetPeriod = new BonusPersonalPeriodGetForDependentCalcResponse();
        $mCallBonusPersonalPeriod
            ->expects($this->once())
            ->method('getForDependentCalc')
            ->willReturn($mRespGetPeriod);
        // if($respGetPeriod->isSucceed())
        $mRespGetPeriod->markSucceed();
        // $calcData = $respGetPeriod->getCalcData();
        $mRespGetPeriod->setCalcData([Calculation::ATTR_ID => $CALC_ID]);
        // $this->_conn->beginTransaction();
        $mConn
            ->expects($this->once())
            ->method('beginTransaction');
        // $compressPtc = $this->_subDb->getCompressedPtcData($ptcCompressCalcId);
        $mSubDb
            ->expects($this->once())
            ->method('getCompressedPtcData')
            ->willThrowException(new \Exception());
        // $this->_conn->rollback();
        $mConn
            ->expects($this->once())
            ->method('rollback');
        /**
         * Prepare request and perform call.
         */
        /** @var  $call Call */
        $call = new Call(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallBonusPersonalPeriod,
            $mSubDb,
            $mSubCalc
        );
        $req = new Request\CompressOi();
        $req->setScheme(Def::SCHEMA_EU);
        $resp = $call->compressOi($req);
        $this->assertFalse($resp->isSucceed());
    }

    public function test_compressOi_noPeriod()
    {
        /** === Test Data === */
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolbox = $this->_mockToolbox();
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Service\IAccount');
        $mCallBonusPersonalPeriod = $this->_mockFor('Praxigento\BonusHybrid\Service\IPeriod');
        $mSubDb = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Db');
        $mSubCalc = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Calc');

        // $respGetPeriod = $this->_callPeriod->getForDependentCalc($reqGetPeriod);
        $mRespGetPeriod = new BonusPersonalPeriodGetForDependentCalcResponse();
        $mCallBonusPersonalPeriod
            ->expects($this->once())
            ->method('getForDependentCalc')
            ->willReturn($mRespGetPeriod);

        /**
         * Prepare request and perform call.
         */
        /** @var  $call Call */
        $call = new Call(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallBonusPersonalPeriod,
            $mSubDb,
            $mSubCalc
        );
        $req = new Request\CompressOi();
        $resp = $call->compressOi($req);
        $this->assertFalse($resp->isSucceed());
    }

    public function test_compressPtc_isPeriod()
    {
        /** === Test Data === */
        $CALC_ID = 21;
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolDate = $this->_mockFor('Praxigento\Core\Tool\IDate');
        $mToolPeriod = $this->_mockFor('Praxigento\Core\Tool\IPeriod');
        $mToolbox = $this->_mockToolbox(null, $mToolDate, null, $mToolPeriod);
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Service\IAccount');
        $mCallBonusPersonalPeriod = $this->_mockFor('Praxigento\BonusHybrid\Service\IPeriod');
        $mSubDb = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Db');
        $mSubCalc = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Calc');

        // $respGetPeriod = $this->_callPeriod->getForDependentCalc($reqGetPeriod);
        $mRespGetPeriod = new BonusPersonalPeriodGetForDependentCalcResponse();
        $mCallBonusPersonalPeriod
            ->expects($this->once())
            ->method('getForDependentCalc')
            ->willReturn($mRespGetPeriod);
        // if($respGetPeriod->isSucceed())
        $mRespGetPeriod->markSucceed();
        // $calcData = $respGetPeriod->getCalcData();
        $mRespGetPeriod->setCalcData([Calculation::ATTR_ID => $CALC_ID]);
        // $this->_conn->beginTransaction();
        $mConn
            ->expects($this->once())
            ->method('beginTransaction');
        // $updates = $this->_subCalc->compressPtc($downlineSnap, $transData, $compressLevel);
        $mUpdates = new DataObject();
        $mUpdates->set(Sub\Calc::DATA_SNAP, []);
        $mUpdates->set(Sub\Calc::DATA_PV, []);
        $mSubCalc
            ->expects($this->once())
            ->method('compressPtc')
            ->willReturn($mUpdates);
        // $this->_conn->commit();
        $mConn
            ->expects($this->once())
            ->method('commit');
        /**
         * Prepare request and perform call.
         */
        /** @var  $call Call */
        $call = new Call(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallBonusPersonalPeriod,
            $mSubDb,
            $mSubCalc
        );
        $req = new Request\CompressPtc();
        $resp = $call->compressPtc($req);
        $this->assertTrue($resp->isSucceed());
    }

    public function test_compressPtc_isPeriod_exception()
    {
        /** === Test Data === */
        $CALC_ID = 21;
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolDate = $this->_mockFor('Praxigento\Core\Tool\IDate');
        $mToolPeriod = $this->_mockFor('Praxigento\Core\Tool\IPeriod');
        $mToolbox = $this->_mockToolbox(null, $mToolDate, null, $mToolPeriod);
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Service\IAccount');
        $mCallBonusPersonalPeriod = $this->_mockFor('Praxigento\BonusHybrid\Service\IPeriod');
        $mSubDb = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Db');
        $mSubCalc = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Calc');

        // $respGetPeriod = $this->_callPeriod->getForDependentCalc($reqGetPeriod);
        $mRespGetPeriod = new BonusPersonalPeriodGetForDependentCalcResponse();
        $mCallBonusPersonalPeriod
            ->expects($this->once())
            ->method('getForDependentCalc')
            ->willReturn($mRespGetPeriod);
        // if($respGetPeriod->isSucceed())
        $mRespGetPeriod->markSucceed();
        // $calcData = $respGetPeriod->getCalcData();
        $mRespGetPeriod->setCalcData([Calculation::ATTR_ID => $CALC_ID]);
        // $this->_conn->beginTransaction();
        $mConn
            ->expects($this->once())
            ->method('beginTransaction');
        // $downlineSnap = $this->_subDb->getDownlineSnapshot($periodEnd);
        $mSubDb
            ->expects($this->once())
            ->method('getDownlineSnapshot')
            ->willThrowException(new \Exception());
        // $this->_conn->rollback();
        $mConn
            ->expects($this->once())
            ->method('rollback');
        /**
         * Prepare request and perform call.
         */
        /** @var  $call Call */
        $call = new Call(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallBonusPersonalPeriod,
            $mSubDb,
            $mSubCalc
        );
        $req = new Request\CompressPtc();
        $resp = $call->compressPtc($req);
        $this->assertFalse($resp->isSucceed());
    }

    public function test_compressPtc_noPeriod()
    {
        /** === Test Data === */
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolbox = $this->_mockToolbox();
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Service\IAccount');
        $mCallBonusPersonalPeriod = $this->_mockFor('Praxigento\BonusHybrid\Service\IPeriod');
        $mSubDb = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Db');
        $mSubCalc = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Calc');

        // $respGetPeriod = $this->_callPeriod->getForDependentCalc($reqGetPeriod);
        $mRespGetPeriod = new BonusPersonalPeriodGetForDependentCalcResponse();
        $mCallBonusPersonalPeriod
            ->expects($this->once())
            ->method('getForDependentCalc')
            ->willReturn($mRespGetPeriod);

        /**
         * Prepare request and perform call.
         */
        /** @var  $call Call */
        $call = new Call(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallBonusPersonalPeriod,
            $mSubDb,
            $mSubCalc
        );
        $req = new Request\CompressPtc();
        $resp = $call->compressPtc($req);
        $this->assertFalse($resp->isSucceed());
    }

    public function test_pvWriteOff_isPeriod()
    {
        /** === Test Data === */
        $PERIOD_ID = 1;
        $PERIOD_BEGIN = '20151201';
        $PERIOD_END = '20151231';
        $CALC_ID = 2;
        $UPDATES = [];
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolDate = $this->_mockFor('Praxigento\Core\Tool\IDate');
        $mToolPeriod = $this->_mockFor('Praxigento\Core\Tool\IPeriod');
        $mToolbox = $this->_mockToolbox(null, $mToolDate, null, $mToolPeriod);
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Service\IAccount');
        $mCallBonusPersonalPeriod = $this->_mockFor('Praxigento\BonusHybrid\Service\IPeriod');
        $mSubDb = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Db');
        $mSubCalc = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Calc');

        // $respGetPeriod = $this->_callBonusPersonalPeriod->getForWriteOff($reqGetPeriod);
        $mRespGetPeriod = new BonusPersonalPeriodGetForWriteOffResponse();
        $mRespGetPeriod->markSucceed();
        $mRespGetPeriod->setPeriodData([
            Period::ATTR_ID => $PERIOD_ID,
            Period::ATTR_DSTAMP_BEGIN => $PERIOD_BEGIN,
            Period::ATTR_DSTAMP_END => $PERIOD_END
        ]);
        $mRespGetPeriod->setCalcData([Calculation::ATTR_ID => $CALC_ID]);
        $mCallBonusPersonalPeriod
            ->expects($this->once())
            ->method('getForWriteOff')
            ->willReturn($mRespGetPeriod);
        // $updates = $this->_subCalc->pvWriteOff($transData);
        $mSubCalc
            ->expects($this->once())
            ->method('pvWriteOff')
            ->willReturn($UPDATES);
        /**
         * Prepare request and perform call.
         */
        /** @var  $call Call */
        $call = new Call(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallBonusPersonalPeriod,
            $mSubDb,
            $mSubCalc
        );
        $req = new Request\PvWriteOff();
        $resp = $call->pvWriteOff($req);
        $this->assertTrue($resp->isSucceed());
        $this->assertEquals($PERIOD_ID, $resp->getPeriodId());
        $this->assertEquals($CALC_ID, $resp->getCalcId());
    }

    public function test_pvWriteOff_isPeriod_exception()
    {
        /** === Test Data === */
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolDate = $this->_mockFor('Praxigento\Core\Tool\IDate');
        $mToolPeriod = $this->_mockFor('Praxigento\Core\Tool\IPeriod');
        $mToolbox = $this->_mockToolbox(null, $mToolDate, null, $mToolPeriod);
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Service\IAccount');
        $mCallBonusPersonalPeriod = $this->_mockFor('Praxigento\BonusHybrid\Service\IPeriod');
        $mSubDb = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Db');
        $mSubCalc = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Calc');

        // $respGetPeriod = $this->_callBonusPersonalPeriod->getForWriteOff($reqGetPeriod);
        $mRespGetPeriod = new BonusPersonalPeriodGetForWriteOffResponse();
        $mRespGetPeriod->markSucceed();
        $mCallBonusPersonalPeriod
            ->expects($this->once())
            ->method('getForWriteOff')
            ->willReturn($mRespGetPeriod);
        // $transData = $this->_subDb->getDataForWriteOff(...)
        $mSubDb
            ->expects($this->once())
            ->method('getDataForWriteOff')
            ->willThrowException(new \Exception());
        // $this->_conn->rollback();
        $mConn
            ->expects($this->once())
            ->method('rollback');
        /**
         * Prepare request and perform call.
         */
        /** @var  $call Call */
        $call = new Call(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallBonusPersonalPeriod,
            $mSubDb,
            $mSubCalc
        );
        $req = new Request\PvWriteOff();
        $resp = $call->pvWriteOff($req);
        $this->assertFalse($resp->isSucceed());
    }

    public function test_pvWriteOff_isPeriod_noTransactions()
    {
        /** === Test Data === */
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolbox = $this->_mockToolbox();
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Service\IAccount');
        $mCallBonusPersonalPeriod = $this->_mockFor('Praxigento\BonusHybrid\Service\IPeriod');
        $mSubDb = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Db');
        $mSubCalc = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Calc');

        // $respGetPeriod = $this->_callBonusPersonalPeriod->getForWriteOff($reqGetPeriod);
        $mRespGetPeriod = new BonusPersonalPeriodGetForWriteOffResponse();
        $mRespGetPeriod->markSucceed();
        $mRespGetPeriod->setHasNoPvTransactionsYet();
        $mCallBonusPersonalPeriod
            ->expects($this->once())
            ->method('getForWriteOff')
            ->willReturn($mRespGetPeriod);

        /**
         * Prepare request and perform call.
         */
        /** @var  $call Call */
        $call = new Call(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallBonusPersonalPeriod,
            $mSubDb,
            $mSubCalc
        );
        $req = new Request\PvWriteOff();
        $resp = $call->pvWriteOff($req);
        $this->assertTrue($resp->isSucceed());
    }

    public function test_pvWriteOff_noPeriod()
    {
        /** === Test Data === */
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolbox = $this->_mockToolbox();
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Service\IAccount');
        $mCallBonusPersonalPeriod = $this->_mockFor('Praxigento\BonusHybrid\Service\IPeriod');
        $mSubDb = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Db');
        $mSubCalc = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Calc');

        // $respGetPeriod = $this->_callBonusPersonalPeriod->get($reqGetPeriod);
        $mRespGetPeriod = new BonusPersonalPeriodGetForWriteOffResponse();
        $mCallBonusPersonalPeriod
            ->expects($this->once())
            ->method('getForWriteOff')
            ->willReturn($mRespGetPeriod);

        /**
         * Prepare request and perform call.
         */
        /** @var  $call Call */
        $call = new Call(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallBonusPersonalPeriod,
            $mSubDb,
            $mSubCalc
        );
        $req = new Request\PvWriteOff();
        $resp = $call->pvWriteOff($req);
        $this->assertFalse($resp->isSucceed());
    }

    public function test_valueOv_isPeriod()
    {
        /** === Test Data === */
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolbox = $this->_mockToolbox();
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Service\IAccount');
        $mCallBonusPersonalPeriod = $this->_mockFor('Praxigento\BonusHybrid\Service\IPeriod');
        $mSubDb = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Db');
        $mSubCalc = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Calc');

        // $respGetPeriod = $this->_callPeriod->getForDependentCalc($reqGetPeriod);
        $mRespGetPeriod = new BonusPersonalPeriodGetForDependentCalcResponse();
        $mCallBonusPersonalPeriod
            ->expects($this->once())
            ->method('getForDependentCalc')
            ->willReturn($mRespGetPeriod);
        // if($respGetPeriod->isSucceed())
        $mRespGetPeriod->markSucceed();
        // $this->_conn->commit();
        $mConn
            ->expects($this->once())
            ->method('commit');
        /**
         * Prepare request and perform call.
         */
        /** @var  $call Call */
        $call = new Call(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallBonusPersonalPeriod,
            $mSubDb,
            $mSubCalc
        );
        $req = new Request\ValueOv();
        $resp = $call->valueOv($req);
        $this->assertTrue($resp->isSucceed());
    }

    public function test_valueOv_isPeriod_exception()
    {
        /** === Test Data === */
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolbox = $this->_mockToolbox();
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Service\IAccount');
        $mCallBonusPersonalPeriod = $this->_mockFor('Praxigento\BonusHybrid\Service\IPeriod');
        $mSubDb = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Db');
        $mSubCalc = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Calc');

        // $respGetPeriod = $this->_callPeriod->getForDependentCalc($reqGetPeriod);
        $mRespGetPeriod = new BonusPersonalPeriodGetForDependentCalcResponse();
        $mCallBonusPersonalPeriod
            ->expects($this->once())
            ->method('getForDependentCalc')
            ->willReturn($mRespGetPeriod);
        // if($respGetPeriod->isSucceed())
        $mRespGetPeriod->markSucceed();
        // $this->_conn->commit();
        $mConn
            ->expects($this->once())
            ->method('commit')
            ->willThrowException(new \Exception());
        /**
         * Prepare request and perform call.
         */
        /** @var  $call Call */
        $call = new Call(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallBonusPersonalPeriod,
            $mSubDb,
            $mSubCalc
        );
        $req = new Request\ValueOv();
        $resp = $call->valueOv($req);
        $this->assertFalse($resp->isSucceed());
    }

    public function test_valueOv_noPeriod()
    {
        /** === Test Data === */
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolbox = $this->_mockToolbox();
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Service\IAccount');
        $mCallBonusPersonalPeriod = $this->_mockFor('Praxigento\BonusHybrid\Service\IPeriod');
        $mSubDb = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Db');
        $mSubCalc = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Calc');

        // $respGetPeriod = $this->_callPeriod->getForDependentCalc($reqGetPeriod);
        $mRespGetPeriod = new BonusPersonalPeriodGetForDependentCalcResponse();
        $mCallBonusPersonalPeriod
            ->expects($this->once())
            ->method('getForDependentCalc')
            ->willReturn($mRespGetPeriod);

        /**
         * Prepare request and perform call.
         */
        /** @var  $call Call */
        $call = new Call(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallBonusPersonalPeriod,
            $mSubDb,
            $mSubCalc
        );
        $req = new Request\ValueOv();
        $resp = $call->valueOv($req);
        $this->assertFalse($resp->isSucceed());
    }

    public function test_valueTv_isPeriod()
    {
        /** === Test Data === */
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolbox = $this->_mockToolbox();
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Service\IAccount');
        $mCallBonusPersonalPeriod = $this->_mockFor('Praxigento\BonusHybrid\Service\IPeriod');
        $mSubDb = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Db');
        $mSubCalc = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Calc');

        // $respGetPeriod = $this->_callPeriod->getForDependentCalc($reqGetPeriod);
        $mRespGetPeriod = new BonusPersonalPeriodGetForDependentCalcResponse();
        $mCallBonusPersonalPeriod
            ->expects($this->once())
            ->method('getForDependentCalc')
            ->willReturn($mRespGetPeriod);
        // if($respGetPeriod->isSucceed())
        $mRespGetPeriod->markSucceed();
        // $this->_conn->commit();
        $mConn
            ->expects($this->once())
            ->method('commit');
        /**
         * Prepare request and perform call.
         */
        /** @var  $call Call */
        $call = new Call(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallBonusPersonalPeriod,
            $mSubDb,
            $mSubCalc
        );
        $req = new Request\ValueTv();
        $resp = $call->valueTv($req);
        $this->assertTrue($resp->isSucceed());
    }

    public function test_valueTv_isPeriod_exception()
    {
        /** === Test Data === */
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolbox = $this->_mockToolbox();
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Service\IAccount');
        $mCallBonusPersonalPeriod = $this->_mockFor('Praxigento\BonusHybrid\Service\IPeriod');
        $mSubDb = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Db');
        $mSubCalc = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Calc');

        // $respGetPeriod = $this->_callPeriod->getForDependentCalc($reqGetPeriod);
        $mRespGetPeriod = new BonusPersonalPeriodGetForDependentCalcResponse();
        $mCallBonusPersonalPeriod
            ->expects($this->once())
            ->method('getForDependentCalc')
            ->willReturn($mRespGetPeriod);
        // if($respGetPeriod->isSucceed())
        $mRespGetPeriod->markSucceed();
        // $this->_conn->commit();
        $mConn
            ->expects($this->once())
            ->method('commit')
            ->willThrowException(new \Exception());
        /**
         * Prepare request and perform call.
         */
        /** @var  $call Call */
        $call = new Call(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallBonusPersonalPeriod,
            $mSubDb,
            $mSubCalc
        );
        $req = new Request\ValueTv();
        $resp = $call->valueTv($req);
        $this->assertFalse($resp->isSucceed());
    }

    public function test_valueTv_noPeriod()
    {
        /** === Test Data === */
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolbox = $this->_mockToolbox();
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Service\IAccount');
        $mCallBonusPersonalPeriod = $this->_mockFor('Praxigento\BonusHybrid\Service\IPeriod');
        $mSubDb = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Db');
        $mSubCalc = $this->_mockFor('Praxigento\BonusHybrid\Service\Calc\Sub\Calc');

        // $respGetPeriod = $this->_callPeriod->getForDependentCalc($reqGetPeriod);
        $mRespGetPeriod = new BonusPersonalPeriodGetForDependentCalcResponse();
        $mCallBonusPersonalPeriod
            ->expects($this->once())
            ->method('getForDependentCalc')
            ->willReturn($mRespGetPeriod);

        /**
         * Prepare request and perform call.
         */
        /** @var  $call Call */
        $call = new Call(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallBonusPersonalPeriod,
            $mSubDb,
            $mSubCalc
        );
        $req = new Request\ValueTv();
        $resp = $call->valueTv($req);
        $this->assertFalse($resp->isSucceed());
    }

}