<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\Bonus\Hybrid\Lib\Service\Period\Sub;

use Praxigento\Accounting\Data\Entity\Account;
use Praxigento\Accounting\Data\Entity\Transaction;
use Praxigento\Accounting\Data\Entity\Type\Asset as TypeAsset;
use Praxigento\BonusBase\Service\Period\Response\GetLatest as BonusBasePeriodGetLatestResponse;
use Praxigento\BonusBase\Service\Type\Calc\Response\GetByCode as BonusBaseTypeCalcResponse;
use Praxigento\Core\Lib\Service\Repo\Response\AddEntity as RepoAddEntityResponse;

include_once(__DIR__ . '/../../../../phpunit_bootstrap.php');

class Db_UnitTest extends \Praxigento\Core\Test\BaseCase\Mockery
{
    protected function setUp()
    {
        parent::setUp();
        $this->markTestSkipped('Test is deprecated after M1 & M2 merge is done.');
    }

    public function test_addNewPeriod()
    {
        /** === Test Data === */
        $CALC_TYPE_ID = 12;
        $DS_BEGIN = '20150101';
        $DS_END = '20150131';
        $ID_INSERTED = 1024;
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolDate = $this->_mockFor('\Praxigento\Core\Tool\IDate');
        $mToolbox = $this->_mockToolbox(null, $mToolDate);
        $mCallRepo = $this->_mockCallRepo();
        $mCallBonusBasePeriod = $this->_mockFor('\Praxigento\BonusBase\Service\IPeriod');
        $mCallTypeCalc = $this->_mockFor('\Praxigento\BonusBase\Service\ITypeCalc');

        // $respAdd = $this->_callRepo->addEntity($reqAdd);
        $mRespAdd = new RepoAddEntityResponse();
        $mCallRepo
            ->expects($this->exactly(2))
            ->method('addEntity')
            ->willReturn($mRespAdd);
        // if($respAdd->isSucceed())
        $mRespAdd->markSucceed();
        // $periodId = $respAdd->getIdInserted();
        $mRespAdd->setIdInserted($ID_INSERTED);
        /**
         * Prepare request and perform call.
         */
        /** @var  $sub Db */
        $sub = new Db($mLogger, $mDba, $mToolbox, $mCallRepo, $mCallBonusBasePeriod, $mCallTypeCalc);
        $data = $sub->addNewPeriodAndCalc($CALC_TYPE_ID, $DS_BEGIN, $DS_END);
        $this->assertInstanceOf(\Flancer32\Lib\DataObject::class, $data);
    }

    public function test_getCalcIdByCode()
    {
        /** === Test Data === */
        $CALC_CODE = 'code';
        $CALC_ID = 512;
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolbox = $this->_mockToolbox();
        $mCallRepo = $this->_mockCallRepo();
        $mCallBonusBasePeriod = $this->_mockFor('\Praxigento\BonusBase\Service\IPeriod');
        $mCallTypeCalc = $this->_mockFor('\Praxigento\BonusBase\Service\ITypeCalc');

        // $respTypeCalc = $this->_callTypeCalc->getByCode($reqTypeCalc);
        $mRespTypeCalc = new BonusBaseTypeCalcResponse();
        $mCallTypeCalc
            ->expects($this->once())
            ->method('getByCode')
            ->willReturn($mRespTypeCalc);
        // $result = $respTypeCalc->getId();
        $mRespTypeCalc->setId($CALC_ID);
        /**
         * Prepare request and perform call.
         */
        /** @var  $sub Db */
        $sub = new Db($mLogger, $mDba, $mToolbox, $mCallRepo, $mCallBonusBasePeriod, $mCallTypeCalc);
        $data = $sub->getCalcIdByCode($CALC_CODE);
        $this->assertEquals($CALC_ID, $data);
    }

    public function test_getFirstDateForPvTransactions()
    {
        /** === Test Data === */
        $TBL_ACC = 'account table';
        $TBL_TRANS = 'transaction table';
        $TBL_TYPE = 'type table';
        $RESULT_TS = 'date';
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolbox = $this->_mockToolbox();
        $mCallRepo = $this->_mockCallRepo();
        $mCallBonusBasePeriod = $this->_mockFor('\Praxigento\BonusBase\Service\IPeriod');
        $mCallTypeCalc = $this->_mockFor('\Praxigento\BonusBase\Service\ITypeCalc');

        // $tblAcc = $this->_resource->getTableName(Account::ENTITY_NAME);
        $mDba
            ->expects($this->at(0))
            ->method('getTableName')
            ->with(Account::ENTITY_NAME)
            ->willReturn($TBL_ACC);
        // $tblTrans = $this->_resource->getTableName(Transaction::ENTITY_NAME);
        $mDba
            ->expects($this->at(1))
            ->method('getTableName')
            ->with(Transaction::ENTITY_NAME)
            ->willReturn($TBL_TRANS);
        // $tblTrans = $this->_resource->getTableName(Transaction::ENTITY_NAME);
        $mDba
            ->expects($this->at(2))
            ->method('getTableName')
            ->with(TypeAsset::ENTITY_NAME)
            ->willReturn($TBL_TYPE);
        // $query = $this->_conn->select();
        $mQuery = $this->_mockDbSelect();
        $mConn
            ->expects($this->once())
            ->method('select')
            ->willReturn($mQuery);
        // $result = $this->_conn->fetchOne($query);
        $mConn
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn($RESULT_TS);
        /**
         * Prepare request and perform call.
         */
        /** @var  $sub Db */
        $sub = new Db($mLogger, $mDba, $mToolbox, $mCallRepo, $mCallBonusBasePeriod, $mCallTypeCalc);
        $resp = $sub->getFirstDateForPvTransactions();
        $this->assertEquals($RESULT_TS, $resp);
    }

    public function test_getLastPeriodData()
    {
        /** === Test Data === */
        $CALC_ID = 512;
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolbox = $this->_mockToolbox();
        $mCallRepo = $this->_mockCallRepo();
        $mCallBonusBasePeriod = $this->_mockFor('\Praxigento\BonusBase\Service\IPeriod');
        $mCallTypeCalc = $this->_mockFor('\Praxigento\BonusBase\Service\ITypeCalc');

        // $result = $this->_callBonusBasePeriod->getLatest($reqLastPeriod);
        $mResult = new BonusBasePeriodGetLatestResponse();
        $mResult->markSucceed();
        $mCallBonusBasePeriod
            ->expects($this->once())
            ->method('getLatest')
            ->willReturn($mResult);

        /**
         * Prepare request and perform call.
         */
        /** @var  $sub Db */
        $sub = new Db($mLogger, $mDba, $mToolbox, $mCallRepo, $mCallBonusBasePeriod, $mCallTypeCalc);
        $data = $sub->getLastPeriodData($CALC_ID);
        $this->assertTrue($data->isSucceed());
    }
}