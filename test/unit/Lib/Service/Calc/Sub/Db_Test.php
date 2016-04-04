<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\Bonus\Hybrid\Lib\Service\Calc\Sub;

use Flancer32\Lib\DataObject;
use Praxigento\Accounting\Data\Entity\Account;
use Praxigento\Accounting\Data\Entity\Operation;
use Praxigento\Accounting\Lib\Service\Account\Response\Get as AccountGetResponse;
use Praxigento\Accounting\Lib\Service\Account\Response\GetRepresentative as AccountGetRepresentativeResponse;
use Praxigento\Accounting\Lib\Service\Operation\Response\Add as OperationAddResponse;
use Praxigento\Accounting\Lib\Service\Type\Asset\Response\GetByCode as TypeAssetGetByCodeResponse;
use Praxigento\Accounting\Lib\Service\Type\Operation\Response\GetByCode as TypeOperationGetByCodeResponse;
use Praxigento\Bonus\Base\Lib\Entity\Level;
use Praxigento\Bonus\Base\Lib\Service\Type\Calc\Response\GetByCode as TypeCalcGetByCodeResponse;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\Bonus\Hybrid\Lib\Entity\Cfg\Override as CfgOverride;
use Praxigento\Bonus\Hybrid\Lib\Entity\Cfg\Param as CfgParam;
use Praxigento\Bonus\Hybrid\Lib\Entity\Compression\Oi as OiCompress;
use Praxigento\Bonus\Hybrid\Lib\Entity\Compression\Ptc as PtcCompress;
use Praxigento\Core\Lib\Service\Repo\Response\AddEntity as RepoAddEntityResponse;
use Praxigento\Core\Lib\Service\Repo\Response\GetEntities as RepoGetEntitiesResponse;
use Praxigento\Core\Lib\Service\Repo\Response\UpdateEntity as RepoUpdateEntityResponse;
use Praxigento\Downline\Lib\Service\Snap\Response\GetStateOnDate as DownlineSnapGetStateOnDateResponse;
use Praxigento\Pv\Data\Entity\Sale as PvSale;

include_once(__DIR__ . '/../../../../phpunit_bootstrap.php');

class Db_UnitTest extends \Praxigento\Core\Lib\Test\BaseTestCase {

    public function test_getBonusLevels() {
        /** === Test Data === */
        $CALC_ID = 4;
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolDate = $this->_mockFor('Praxigento\Core\Lib\Tool\Date');
        $mToolbox = $this->_mockToolbox(null, $mToolDate);
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Lib\Service\IAccount');
        $mCallOper = $this->_mockFor('Praxigento\Accounting\Lib\Service\IOperation');
        $mCallTypeAsset = $this->_mockFor('Praxigento\Accounting\Lib\Service\ITypeAsset');
        $mCallTypeCalc = $this->_mockFor('Praxigento\Bonus\Base\Lib\Service\ITypeCalc');
        $mCallTypeOper = $this->_mockFor('Praxigento\Accounting\Lib\Service\ITypeOperation');
        $mCallDownlineSnap = $this->_mockFor('Praxigento\Downline\Lib\Service\ISnap');

        // $respCalcType = $this->_callTypeCalc->getByCode(new TypeCalcGetByCodeRequest(Cfg::CODE_TYPE_CALC_BONUS_PERSONAL_FLAT));
        $mRespCalcType = new TypeCalcGetByCodeResponse();
        $mCallTypeCalc
            ->expects($this->once())
            ->method('getByCode')
            ->willReturn($mRespCalcType);
        // $calcTypeId = $respCalcType->getId();
        $mRespCalcType->setId($CALC_ID);
        // $resp = $this->_callRepo->getEntities($req);
        $mResp = new RepoGetEntitiesResponse();
        $mCallRepo
            ->expects($this->once())
            ->method('getEntities')
            ->willReturn($mResp);
        // $data = $resp->getData();
        $mResp->setData([
            [ Level::ATTR_LEVEL => 100, Level::ATTR_PERCENT => 0.1 ]
        ]);
        /**
         * Prepare request and perform call.
         */
        /** @var  $sub Db */
        $sub = new Db(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallOper,
            $mCallTypeAsset,
            $mCallTypeCalc,
            $mCallTypeOper,
            $mCallDownlineSnap
        );
        $resp = $sub->getBonusLevels('CALC_TYPE_CODE');
        $this->assertTrue(is_array($resp));
        $this->assertEquals(0.1, $resp[100]);

    }

    public function test_getCfgOverride() {
        /** === Test Data === */
        $SCHEME = 'scheme';
        $RANK_ID = 1;
        $GENERATION = 3;
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolbox = $this->_mockToolbox();
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Lib\Service\IAccount');
        $mCallOper = $this->_mockFor('Praxigento\Accounting\Lib\Service\IOperation');
        $mCallTypeAsset = $this->_mockFor('Praxigento\Accounting\Lib\Service\ITypeAsset');
        $mCallTypeCalc = $this->_mockFor('Praxigento\Bonus\Base\Lib\Service\ITypeCalc');
        $mCallTypeOper = $this->_mockFor('Praxigento\Accounting\Lib\Service\ITypeOperation');
        $mCallDownlineSnap = $this->_mockFor('Praxigento\Downline\Lib\Service\ISnap');


        // $resp = $this->_callRepo->getEntities($req);
        $mResp = new DataObject();
        $mResp->setData([
            [
                CfgOverride::ATTR_SCHEME     => $SCHEME,
                CfgParam::ATTR_RANK_ID       => $RANK_ID,
                CfgOverride::ATTR_GENERATION => $GENERATION
            ]
        ]);
        $mCallRepo
            ->expects($this->once())
            ->method('getEntities')
            ->willReturn($mResp);

        /**
         * Prepare request and perform call.
         */
        /** @var  $sub Db */
        $sub = new Db(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallOper,
            $mCallTypeAsset,
            $mCallTypeCalc,
            $mCallTypeOper,
            $mCallDownlineSnap
        );
        $resp = $sub->getCfgOverride();
        $this->assertTrue(is_array($resp));
    }

    public function test_getCfgParams() {
        /** === Test Data === */
        $SCHEME = 'scheme';
        $RANK_ID = 1;
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolbox = $this->_mockToolbox();
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Lib\Service\IAccount');
        $mCallOper = $this->_mockFor('Praxigento\Accounting\Lib\Service\IOperation');
        $mCallTypeAsset = $this->_mockFor('Praxigento\Accounting\Lib\Service\ITypeAsset');
        $mCallTypeCalc = $this->_mockFor('Praxigento\Bonus\Base\Lib\Service\ITypeCalc');
        $mCallTypeOper = $this->_mockFor('Praxigento\Accounting\Lib\Service\ITypeOperation');
        $mCallDownlineSnap = $this->_mockFor('Praxigento\Downline\Lib\Service\ISnap');


        // $resp = $this->_callRepo->getEntities($req);
        $mResp = new DataObject();
        $mResp->setData([
            [ CfgParam::ATTR_SCHEME => $SCHEME, CfgParam::ATTR_RANK_ID => $RANK_ID ]
        ]);
        $mCallRepo
            ->expects($this->once())
            ->method('getEntities')
            ->willReturn($mResp);

        /**
         * Prepare request and perform call.
         */
        /** @var  $sub Db */
        $sub = new Db(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallOper,
            $mCallTypeAsset,
            $mCallTypeCalc,
            $mCallTypeOper,
            $mCallDownlineSnap
        );
        $resp = $sub->getCfgParams();
        $this->assertTrue(is_array($resp));
    }

    public function test_getCompressedOiData() {
        /** === Test Data === */
        $CALC_ID = 16;
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolbox = $this->_mockToolbox();
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Lib\Service\IAccount');
        $mCallOper = $this->_mockFor('Praxigento\Accounting\Lib\Service\IOperation');
        $mCallTypeAsset = $this->_mockFor('Praxigento\Accounting\Lib\Service\ITypeAsset');
        $mCallTypeCalc = $this->_mockFor('Praxigento\Bonus\Base\Lib\Service\ITypeCalc');
        $mCallTypeOper = $this->_mockFor('Praxigento\Accounting\Lib\Service\ITypeOperation');
        $mCallDownlineSnap = $this->_mockFor('Praxigento\Downline\Lib\Service\ISnap');

        // $query = $this->_getConn()->select();
        $mQuery = $this->_mockDbSelect();
        $mConn
            ->expects($this->once())
            ->method('select')
            ->willReturn($mQuery);
        // $result = $this->_getConn()->fetchAll($query);
        $mConn
            ->expects($this->once())
            ->method('fetchAll')
            ->willReturn([ ]);

        /**
         * Prepare request and perform call.
         */
        /** @var  $sub Db */
        $sub = new Db(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallOper,
            $mCallTypeAsset,
            $mCallTypeCalc,
            $mCallTypeOper,
            $mCallDownlineSnap
        );
        $resp = $sub->getCompressedOiData($CALC_ID);
        $this->assertTrue(is_array($resp));
    }

    public function test_getCompressedPtcData() {
        /** === Test Data === */
        $CALC_ID = 16;
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolbox = $this->_mockToolbox();
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Lib\Service\IAccount');
        $mCallOper = $this->_mockFor('Praxigento\Accounting\Lib\Service\IOperation');
        $mCallTypeAsset = $this->_mockFor('Praxigento\Accounting\Lib\Service\ITypeAsset');
        $mCallTypeCalc = $this->_mockFor('Praxigento\Bonus\Base\Lib\Service\ITypeCalc');
        $mCallTypeOper = $this->_mockFor('Praxigento\Accounting\Lib\Service\ITypeOperation');
        $mCallDownlineSnap = $this->_mockFor('Praxigento\Downline\Lib\Service\ISnap');

        // $query = $this->_getConn()->select();
        $mQuery = $this->_mockDbSelect();
        $mConn
            ->expects($this->once())
            ->method('select')
            ->willReturn($mQuery);
        // $result = $this->_getConn()->fetchAll($query);
        $mConn
            ->expects($this->once())
            ->method('fetchAll')
            ->willReturn([ ]);

        /**
         * Prepare request and perform call.
         */
        /** @var  $sub Db */
        $sub = new Db(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallOper,
            $mCallTypeAsset,
            $mCallTypeCalc,
            $mCallTypeOper,
            $mCallDownlineSnap
        );
        $resp = $sub->getCompressedPtcData($CALC_ID);
        $this->assertTrue(is_array($resp));
    }

    public function test_getDownlineCustomersData() {
        /** === Test Data === */
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolbox = $this->_mockToolbox();
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Lib\Service\IAccount');
        $mCallOper = $this->_mockFor('Praxigento\Accounting\Lib\Service\IOperation');
        $mCallTypeAsset = $this->_mockFor('Praxigento\Accounting\Lib\Service\ITypeAsset');
        $mCallTypeCalc = $this->_mockFor('Praxigento\Bonus\Base\Lib\Service\ITypeCalc');
        $mCallTypeOper = $this->_mockFor('Praxigento\Accounting\Lib\Service\ITypeOperation');
        $mCallDownlineSnap = $this->_mockFor('Praxigento\Downline\Lib\Service\ISnap');

        // $resp = $this->_callRepo->getEntities($req);
        $mResp = new DataObject();
        $mCallRepo
            ->expects($this->once())
            ->method('getEntities')
            ->willReturn($mResp);
        // $result = $resp->getData();
        $mResp->setData([ ]);

        /**
         * Prepare request and perform call.
         */
        /** @var  $sub Db */
        $sub = new Db(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallOper,
            $mCallTypeAsset,
            $mCallTypeCalc,
            $mCallTypeOper,
            $mCallDownlineSnap
        );
        $data = $sub->getDownlineCustomersData();
        $this->assertTrue(is_array($data));
    }

    public function test_getDownlineSnapshot() {
        /** === Test Data === */
        $DS = '20150101';
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolDate = $this->_mockFor('Praxigento\Core\Lib\Tool\Date');
        $mToolbox = $this->_mockToolbox(null, $mToolDate);
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Lib\Service\IAccount');
        $mCallOper = $this->_mockFor('Praxigento\Accounting\Lib\Service\IOperation');
        $mCallTypeAsset = $this->_mockFor('Praxigento\Accounting\Lib\Service\ITypeAsset');
        $mCallTypeCalc = $this->_mockFor('Praxigento\Bonus\Base\Lib\Service\ITypeCalc');
        $mCallTypeOper = $this->_mockFor('Praxigento\Accounting\Lib\Service\ITypeOperation');
        $mCallDownlineSnap = $this->_mockFor('Praxigento\Downline\Lib\Service\ISnap');

        // $resp = $this->_callDownlineSnap->getStateOnDate($req);
        $mResp = new DownlineSnapGetStateOnDateResponse();
        $mResp->setData([ ]);
        $mCallDownlineSnap
            ->expects($this->once())
            ->method('getStateOnDate')
            ->willReturn($mResp);

        /**
         * Prepare request and perform call.
         */
        /** @var  $sub Db */
        $sub = new Db(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallOper,
            $mCallTypeAsset,
            $mCallTypeCalc,
            $mCallTypeOper,
            $mCallDownlineSnap
        );
        $data = $sub->getDownlineSnapshot($DS);
        $this->assertTrue(is_array($data));
    }

    public function test_getLastCalculationIdForPeriod() {
        /** === Test Data === */
        $CALC_TYPE_CODE = 'calc_code';
        $CALC_TYPE_ID = 32;
        $PERIOD_BEGIN = '20150101';
        $PERIOD_END = '20150131';
        $RESULT = 'some result';
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolbox = $this->_mockToolbox();
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Lib\Service\IAccount');
        $mCallOper = $this->_mockFor('Praxigento\Accounting\Lib\Service\IOperation');
        $mCallTypeAsset = $this->_mockFor('Praxigento\Accounting\Lib\Service\ITypeAsset');
        $mCallTypeCalc = $this->_mockFor('Praxigento\Bonus\Base\Lib\Service\ITypeCalc');
        $mCallTypeOper = $this->_mockFor('Praxigento\Accounting\Lib\Service\ITypeOperation');
        $mCallDownlineSnap = $this->_mockFor('Praxigento\Downline\Lib\Service\ISnap');

        // $respCalcType = $this->_callTypeCalc->getByCode(new TypeCalcGetByCodeRequest($calcTypeCode));
        $mRespCalcType = new TypeCalcGetByCodeResponse();
        $mCallTypeCalc
            ->expects($this->once())
            ->method('getByCode')
            ->willReturn($mRespCalcType);
        // $calcTypeId = $respCalcType->getId();
        $mRespCalcType->setId($CALC_TYPE_ID);
        // $query = $this->_conn->select();
        $mQuery = $this->_mockDbSelect();
        $mConn
            ->expects($this->once())
            ->method('select')
            ->willReturn($mQuery);
        // $result = $this->_conn->fetchOne(...)
        $mConn
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn($RESULT);
        /**
         * Prepare request and perform call.
         */
        /** @var  $sub Db */
        $sub = new Db(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallOper,
            $mCallTypeAsset,
            $mCallTypeCalc,
            $mCallTypeOper,
            $mCallDownlineSnap
        );
        $resp = $sub->getLastCalculationIdForPeriod($CALC_TYPE_CODE, $PERIOD_BEGIN, $PERIOD_END);
        $this->assertEquals($RESULT, $resp);
    }

    public function test_getOperationsForPvCompression() {
        /** === Test Data === */
        $WRITE_OFF_CALC_ID = 1024;
        $WRITE_OFF_ASSET_TYPE_ID = 2;
        $RESULT = 'array is here...';
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolbox = $this->_mockToolbox();
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Lib\Service\IAccount');
        $mCallOper = $this->_mockFor('Praxigento\Accounting\Lib\Service\IOperation');
        $mCallTypeAsset = $this->_mockFor('Praxigento\Accounting\Lib\Service\ITypeAsset');
        $mCallTypeCalc = $this->_mockFor('Praxigento\Bonus\Base\Lib\Service\ITypeCalc');
        $mCallTypeOper = $this->_mockFor('Praxigento\Accounting\Lib\Service\ITypeOperation');
        $mCallDownlineSnap = $this->_mockFor('Praxigento\Downline\Lib\Service\ISnap');

        // $respGetByCode = $this->_callTypeOper->getByCode(new TypeOperGetByCodeRequest(Cfg::CODE_TYPE_OPER_PV_WRITE_OFF));
        $mRespGetByCode = new TypeOperationGetByCodeResponse();
        $mCallTypeOper
            ->expects($this->once())
            ->method('getByCode')
            ->willReturn($mRespGetByCode);
        // $operPvWriteOffId = $respGetByCode->getId();
        $mRespGetByCode->setId($WRITE_OFF_ASSET_TYPE_ID);
        // $query = $this->_conn->select();
        $mQuery = $this->_mockDbSelect();
        $mConn
            ->expects($this->once())
            ->method('select')
            ->willReturn($mQuery);
        // $result = $this->_conn->fetchAll($query);
        $mConn
            ->expects($this->once())
            ->method('fetchAll')
            ->willReturn($RESULT);
        /**
         * Prepare request and perform call.
         */
        /** @var  $sub Db */
        $sub = new Db(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallOper,
            $mCallTypeAsset,
            $mCallTypeCalc,
            $mCallTypeOper,
            $mCallDownlineSnap
        );
        $res = $sub->getDataForPvCompression($WRITE_OFF_CALC_ID);
        $this->assertEquals($RESULT, $res);
    }

    public function test_getOperationsForWriteOff() {
        /** === Test Data === */
        $CALC_ID = 1024;
        $TS_FROM = '2015-12-01 00:00:00';
        $TS_TO = '2015-12-31 23:59:59';
        $ASSET_TYPE_ID = 2;
        $OPERATION_ID = 4;
        $RESULT = 'array is here...';
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolbox = $this->_mockToolbox();
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Lib\Service\IAccount');
        $mCallOper = $this->_mockFor('Praxigento\Accounting\Lib\Service\IOperation');
        $mCallTypeAsset = $this->_mockFor('Praxigento\Accounting\Lib\Service\ITypeAsset');
        $mCallTypeCalc = $this->_mockFor('Praxigento\Bonus\Base\Lib\Service\ITypeCalc');
        $mCallTypeOper = $this->_mockFor('Praxigento\Accounting\Lib\Service\ITypeOperation');
        $mCallDownlineSnap = $this->_mockFor('Praxigento\Downline\Lib\Service\ISnap');

        // $respGetByCode = $this->_callTypeAsset->getByCode(new TypeAssetGetByCodeRequest(Cfg::CODE_TYPE_ASSET_PV));
        $mRespGetAssetByCode = new TypeAssetGetByCodeResponse();
        $mRespGetAssetByCode->setId($ASSET_TYPE_ID);
        $mCallTypeAsset
            ->expects($this->once())
            ->method('getByCode')
            ->willReturn($mRespGetAssetByCode);
        // $respGetByCode = $this->_callTypeOper->getByCode(new TypeOperGetByCodeRequest(Cfg::CODE_TYPE_OPER_PV_WRITE_OFF));
        $mRespGetOperByCode = new TypeOperationGetByCodeResponse();
        $mRespGetOperByCode->setId($OPERATION_ID);
        $mCallTypeOper
            ->expects($this->once())
            ->method('getByCode')
            ->willReturn($mRespGetOperByCode);
        // $query = $this->_conn->select();
        $mQuery = $this->_mockDbSelect();
        $mConn
            ->expects($this->once())
            ->method('select')
            ->willReturn($mQuery);
        // $result = $this->_conn->fetchAll($query);
        $mConn
            ->expects($this->once())
            ->method('fetchAll')
            ->willReturn($RESULT);
        /**
         * Prepare request and perform call.
         */
        /** @var  $sub Db */
        $sub = new Db(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallOper,
            $mCallTypeAsset,
            $mCallTypeCalc,
            $mCallTypeOper,
            $mCallDownlineSnap
        );
        $res = $sub->getDataForWriteOff($CALC_ID, $TS_FROM, $TS_TO);
        $this->assertEquals($RESULT, $res);
    }

    public function test_getSaleOrdersForRebate() {
        /** === Test Data === */
        $DS_BEGIN = '20150101';
        $DS_END = '20150131';
        $CUST_ID = 1;
        $SALE_ID = 2;
        $GRAND_TOTAL = 23.34;
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolPeriod = $this->_mockFor('Praxigento\Core\Lib\Tool\Period');
        $mToolbox = $this->_mockToolbox(null, null, null, $mToolPeriod);
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Lib\Service\IAccount');
        $mCallOper = $this->_mockFor('Praxigento\Accounting\Lib\Service\IOperation');
        $mCallTypeAsset = $this->_mockFor('Praxigento\Accounting\Lib\Service\ITypeAsset');
        $mCallTypeCalc = $this->_mockFor('Praxigento\Bonus\Base\Lib\Service\ITypeCalc');
        $mCallTypeOper = $this->_mockFor('Praxigento\Accounting\Lib\Service\ITypeOperation');
        $mCallDownlineSnap = $this->_mockFor('Praxigento\Downline\Lib\Service\ISnap');

        // $resp = $this->_callDownlineSnap->getStateOnDate($req);
        // $query = $this->_conn->select();
        $mQuery = $this->_mockDbSelect();
        $mConn
            ->expects($this->once())
            ->method('select')
            ->willReturn($mQuery);
        // $data = $this->_getConn()->fetchAll($query);
        $mConn
            ->expects($this->once())
            ->method('fetchAll')
            ->willReturn([
                [
                    Cfg::E_CUSTOMER_A_ENTITY_ID          => $CUST_ID,
                    PvSale::ATTR_SALE_ID                 => $SALE_ID,
                    Cfg::E_SALE_ORDER_A_BASE_GRAND_TOTAL => $GRAND_TOTAL
                ]
            ]);

        /**
         * Prepare request and perform call.
         */
        /** @var  $sub Db */
        $sub = new Db(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallOper,
            $mCallTypeAsset,
            $mCallTypeCalc,
            $mCallTypeOper,
            $mCallDownlineSnap
        );
        $data = $sub->getSaleOrdersForRebate($DS_BEGIN, $DS_END);
        $this->assertTrue(is_array($data));
        $this->assertEquals($GRAND_TOTAL, $data[$CUST_ID][$SALE_ID]);

    }

    public function test_markCalcComplete() {
        /** === Test Data === */
        $CALC_ID = 4;
        $TS_NOW = '2015-12-29 01:02:03';
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolDate = $this->_mockFor('Praxigento\Core\Lib\Tool\Date');
        $mToolbox = $this->_mockToolbox(null, $mToolDate);
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Lib\Service\IAccount');
        $mCallOper = $this->_mockFor('Praxigento\Accounting\Lib\Service\IOperation');
        $mCallTypeAsset = $this->_mockFor('Praxigento\Accounting\Lib\Service\ITypeAsset');
        $mCallTypeCalc = $this->_mockFor('Praxigento\Bonus\Base\Lib\Service\ITypeCalc');
        $mCallTypeOper = $this->_mockFor('Praxigento\Accounting\Lib\Service\ITypeOperation');
        $mCallDownlineSnap = $this->_mockFor('Praxigento\Downline\Lib\Service\ISnap');

        // $tsEnded = $this->_toolbox->getDate()->getUtcNowForDb();
        $mToolDate
            ->expects($this->once())
            ->method('getUtcNowForDb')
            ->willReturn($TS_NOW);
        // $resp = $this->_callRepo->updateEntity($req);
        $mRespUpdate = new RepoUpdateEntityResponse();
        $mRespUpdate->setAsSucceed();
        $mCallRepo
            ->expects($this->once())
            ->method('updateEntity')
            ->willReturn($mRespUpdate);
        /**
         * Prepare request and perform call.
         */
        /** @var  $sub Db */
        $sub = new Db(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallOper,
            $mCallTypeAsset,
            $mCallTypeCalc,
            $mCallTypeOper,
            $mCallDownlineSnap
        );
        $res = $sub->markCalcComplete($CALC_ID);
        $this->assertTrue($res);
    }

    public function test_saveCompressedOi() {
        /** === Test Data === */
        $DATA = [
            [
                OiCompress::ATTR_CUSTOMER_ID => 1,
                OiCompress::ATTR_PARENT_ID   => 1
            ]
        ];
        $CALC_ID = 512;
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolDate = $this->_mockFor('Praxigento\Core\Lib\Tool\Date');
        $mToolbox = $this->_mockToolbox(null, $mToolDate);
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Lib\Service\IAccount');
        $mCallOper = $this->_mockFor('Praxigento\Accounting\Lib\Service\IOperation');
        $mCallTypeAsset = $this->_mockFor('Praxigento\Accounting\Lib\Service\ITypeAsset');
        $mCallTypeCalc = $this->_mockFor('Praxigento\Bonus\Base\Lib\Service\ITypeCalc');
        $mCallTypeOper = $this->_mockFor('Praxigento\Accounting\Lib\Service\ITypeOperation');
        $mCallDownlineSnap = $this->_mockFor('Praxigento\Downline\Lib\Service\ISnap');

        // $this->_callRepo->addEntity($req);
        $mCallRepo
            ->expects($this->exactly(1))
            ->method('addEntity');

        /**
         * Prepare request and perform call.
         */
        /** @var  $sub Db */
        $sub = new Db(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallOper,
            $mCallTypeAsset,
            $mCallTypeCalc,
            $mCallTypeOper,
            $mCallDownlineSnap
        );
        $sub->saveCompressedOi($DATA, $CALC_ID);
    }

    public function test_saveCompressedPtc() {
        /** === Test Data === */
        $DATA = [
            [
                'without ID'                => 1,
                PtcCompress::ATTR_PARENT_ID => 1
            ], [
                PtcCompress::ATTR_CUSTOMER_ID => 1,
                PtcCompress::ATTR_PARENT_ID   => 1
            ]
        ];
        $CALC_ID = 512;
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolDate = $this->_mockFor('Praxigento\Core\Lib\Tool\Date');
        $mToolbox = $this->_mockToolbox(null, $mToolDate);
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Lib\Service\IAccount');
        $mCallOper = $this->_mockFor('Praxigento\Accounting\Lib\Service\IOperation');
        $mCallTypeAsset = $this->_mockFor('Praxigento\Accounting\Lib\Service\ITypeAsset');
        $mCallTypeCalc = $this->_mockFor('Praxigento\Bonus\Base\Lib\Service\ITypeCalc');
        $mCallTypeOper = $this->_mockFor('Praxigento\Accounting\Lib\Service\ITypeOperation');
        $mCallDownlineSnap = $this->_mockFor('Praxigento\Downline\Lib\Service\ISnap');

        // $this->_callRepo->addEntity($req);
        $mCallRepo
            ->expects($this->exactly(1))
            ->method('addEntity');

        /**
         * Prepare request and perform call.
         */
        /** @var  $sub Db */
        $sub = new Db(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallOper,
            $mCallTypeAsset,
            $mCallTypeCalc,
            $mCallTypeOper,
            $mCallDownlineSnap
        );
        $sub->saveCompressedPtc($DATA, $CALC_ID);
    }

    /**
     * @expectedException \Exception
     */
    public function test_saveLogCustomers_failed() {
        /** === Test Data === */
        $UPDATES = [
            [ Calc::A_OTHER_ID => 121 ]
        ];
        $TRANS_IDS = [ 1 ];
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolDate = $this->_mockFor('Praxigento\Core\Lib\Tool\Date');
        $mToolbox = $this->_mockToolbox(null, $mToolDate);
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Lib\Service\IAccount');
        $mCallOper = $this->_mockFor('Praxigento\Accounting\Lib\Service\IOperation');
        $mCallTypeAsset = $this->_mockFor('Praxigento\Accounting\Lib\Service\ITypeAsset');
        $mCallTypeCalc = $this->_mockFor('Praxigento\Bonus\Base\Lib\Service\ITypeCalc');
        $mCallTypeOper = $this->_mockFor('Praxigento\Accounting\Lib\Service\ITypeOperation');
        $mCallDownlineSnap = $this->_mockFor('Praxigento\Downline\Lib\Service\ISnap');

        // $this->_callRepo->addEntity($req);
        $mResp = new RepoAddEntityResponse();
        $mCallRepo
            ->expects($this->exactly(1))
            ->method('addEntity')
            ->willReturn($mResp);

        /**
         * Prepare request and perform call.
         */
        /** @var  $sub Db */
        $sub = new Db(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallOper,
            $mCallTypeAsset,
            $mCallTypeCalc,
            $mCallTypeOper,
            $mCallDownlineSnap
        );
        $sub->saveLogCustomers($UPDATES, $TRANS_IDS);
    }

    public function test_saveLogCustomers_success() {
        /** === Test Data === */
        $UPDATES = [
            [ Calc::A_OTHER_ID => 121 ]
        ];
        $TRANS_IDS = [ 1 ];
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolDate = $this->_mockFor('Praxigento\Core\Lib\Tool\Date');
        $mToolbox = $this->_mockToolbox(null, $mToolDate);
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Lib\Service\IAccount');
        $mCallOper = $this->_mockFor('Praxigento\Accounting\Lib\Service\IOperation');
        $mCallTypeAsset = $this->_mockFor('Praxigento\Accounting\Lib\Service\ITypeAsset');
        $mCallTypeCalc = $this->_mockFor('Praxigento\Bonus\Base\Lib\Service\ITypeCalc');
        $mCallTypeOper = $this->_mockFor('Praxigento\Accounting\Lib\Service\ITypeOperation');
        $mCallDownlineSnap = $this->_mockFor('Praxigento\Downline\Lib\Service\ISnap');

        // $this->_callRepo->addEntity($req);
        $mResp = new RepoAddEntityResponse();
        $mCallRepo
            ->expects($this->exactly(1))
            ->method('addEntity')
            ->willReturn($mResp);
        // if(!$resp->isSucceed()) {...}
        $mResp->setAsSucceed();

        /**
         * Prepare request and perform call.
         */
        /** @var  $sub Db */
        $sub = new Db(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallOper,
            $mCallTypeAsset,
            $mCallTypeCalc,
            $mCallTypeOper,
            $mCallDownlineSnap
        );
        $sub->saveLogCustomers($UPDATES, $TRANS_IDS);
    }

    /**
     * @expectedException \Exception
     */
    public function test_saveLogCustomers_wrongSizes() {
        /** === Test Data === */
        $UPDATES = [
            [ Calc::A_OTHER_ID => 121 ]
        ];
        $TRANS_IDS = [ 1, 2 ];
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolDate = $this->_mockFor('Praxigento\Core\Lib\Tool\Date');
        $mToolbox = $this->_mockToolbox(null, $mToolDate);
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Lib\Service\IAccount');
        $mCallOper = $this->_mockFor('Praxigento\Accounting\Lib\Service\IOperation');
        $mCallTypeAsset = $this->_mockFor('Praxigento\Accounting\Lib\Service\ITypeAsset');
        $mCallTypeCalc = $this->_mockFor('Praxigento\Bonus\Base\Lib\Service\ITypeCalc');
        $mCallTypeOper = $this->_mockFor('Praxigento\Accounting\Lib\Service\ITypeOperation');
        $mCallDownlineSnap = $this->_mockFor('Praxigento\Downline\Lib\Service\ISnap');

        /**
         * Prepare request and perform call.
         */
        /** @var  $sub Db */
        $sub = new Db(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallOper,
            $mCallTypeAsset,
            $mCallTypeCalc,
            $mCallTypeOper,
            $mCallDownlineSnap
        );
        $sub->saveLogCustomers($UPDATES, $TRANS_IDS);
    }

    public function test_saveLogOperations() {
        /** === Test Data === */
        $OPER_ID = 256;
        $CALC_ID = 512;
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolDate = $this->_mockFor('Praxigento\Core\Lib\Tool\Date');
        $mToolbox = $this->_mockToolbox(null, $mToolDate);
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Lib\Service\IAccount');
        $mCallOper = $this->_mockFor('Praxigento\Accounting\Lib\Service\IOperation');
        $mCallTypeAsset = $this->_mockFor('Praxigento\Accounting\Lib\Service\ITypeAsset');
        $mCallTypeCalc = $this->_mockFor('Praxigento\Bonus\Base\Lib\Service\ITypeCalc');
        $mCallTypeOper = $this->_mockFor('Praxigento\Accounting\Lib\Service\ITypeOperation');
        $mCallDownlineSnap = $this->_mockFor('Praxigento\Downline\Lib\Service\ISnap');

        // $this->_callRepo->addEntity($req);
        $mCallRepo
            ->expects($this->once())
            ->method('addEntity');

        /**
         * Prepare request and perform call.
         */
        /** @var  $sub Db */
        $sub = new Db(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallOper,
            $mCallTypeAsset,
            $mCallTypeCalc,
            $mCallTypeOper,
            $mCallDownlineSnap
        );
        $sub->saveLogOperations($OPER_ID, $CALC_ID);
    }

    public function test_saveLogPvWriteOff() {
        /** === Test Data === */
        $DATA = [
            Operation::ATTR_ID => 8
        ];
        $OPER_ID = 2;
        $CALC_ID = 4;
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolDate = $this->_mockFor('Praxigento\Core\Lib\Tool\Date');
        $mToolbox = $this->_mockToolbox(null, $mToolDate);
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Lib\Service\IAccount');
        $mCallOper = $this->_mockFor('Praxigento\Accounting\Lib\Service\IOperation');
        $mCallTypeAsset = $this->_mockFor('Praxigento\Accounting\Lib\Service\ITypeAsset');
        $mCallTypeCalc = $this->_mockFor('Praxigento\Bonus\Base\Lib\Service\ITypeCalc');
        $mCallTypeOper = $this->_mockFor('Praxigento\Accounting\Lib\Service\ITypeOperation');
        $mCallDownlineSnap = $this->_mockFor('Praxigento\Downline\Lib\Service\ISnap');

        // $this->_callRepo->addEntity($req);
        $mCallRepo
            ->expects($this->exactly(2))
            ->method('addEntity');
        /**
         * Prepare request and perform call.
         */
        /** @var  $sub Db */
        $sub = new Db(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallOper,
            $mCallTypeAsset,
            $mCallTypeCalc,
            $mCallTypeOper,
            $mCallDownlineSnap
        );
        $sub->saveLogPvWriteOff($DATA, $OPER_ID, $CALC_ID);
    }

    /**
     * @expectedException \Exception
     */
    public function test_saveLogSaleOrders_failed() {
        /** === Test Data === */
        $UPDATES = [
            [ Calc::A_ORDR_ID => 212 ]
        ];
        $TRANS_IDS = [ 1 ];
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolDate = $this->_mockFor('Praxigento\Core\Lib\Tool\Date');
        $mToolbox = $this->_mockToolbox(null, $mToolDate);
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Lib\Service\IAccount');
        $mCallOper = $this->_mockFor('Praxigento\Accounting\Lib\Service\IOperation');
        $mCallTypeAsset = $this->_mockFor('Praxigento\Accounting\Lib\Service\ITypeAsset');
        $mCallTypeCalc = $this->_mockFor('Praxigento\Bonus\Base\Lib\Service\ITypeCalc');
        $mCallTypeOper = $this->_mockFor('Praxigento\Accounting\Lib\Service\ITypeOperation');
        $mCallDownlineSnap = $this->_mockFor('Praxigento\Downline\Lib\Service\ISnap');

        // $this->_callRepo->addEntity($req);
        $mResp = new RepoAddEntityResponse();
        $mCallRepo
            ->expects($this->exactly(1))
            ->method('addEntity')
            ->willReturn($mResp);

        /**
         * Prepare request and perform call.
         */
        /** @var  $sub Db */
        $sub = new Db(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallOper,
            $mCallTypeAsset,
            $mCallTypeCalc,
            $mCallTypeOper,
            $mCallDownlineSnap
        );
        $sub->saveLogSaleOrders($UPDATES, $TRANS_IDS);
    }

    public function test_saveLogSaleOrders_success() {
        /** === Test Data === */
        $UPDATES = [
            [ Calc::A_ORDR_ID => 212 ]
        ];
        $TRANS_IDS = [ 1 ];
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolDate = $this->_mockFor('Praxigento\Core\Lib\Tool\Date');
        $mToolbox = $this->_mockToolbox(null, $mToolDate);
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Lib\Service\IAccount');
        $mCallOper = $this->_mockFor('Praxigento\Accounting\Lib\Service\IOperation');
        $mCallTypeAsset = $this->_mockFor('Praxigento\Accounting\Lib\Service\ITypeAsset');
        $mCallTypeCalc = $this->_mockFor('Praxigento\Bonus\Base\Lib\Service\ITypeCalc');
        $mCallTypeOper = $this->_mockFor('Praxigento\Accounting\Lib\Service\ITypeOperation');
        $mCallDownlineSnap = $this->_mockFor('Praxigento\Downline\Lib\Service\ISnap');

        // $this->_callRepo->addEntity($req);
        $mResp = new RepoAddEntityResponse();
        $mCallRepo
            ->expects($this->exactly(1))
            ->method('addEntity')
            ->willReturn($mResp);
        // if(!$resp->isSucceed()) {...}
        $mResp->setAsSucceed();

        /**
         * Prepare request and perform call.
         */
        /** @var  $sub Db */
        $sub = new Db(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallOper,
            $mCallTypeAsset,
            $mCallTypeCalc,
            $mCallTypeOper,
            $mCallDownlineSnap
        );
        $sub->saveLogSaleOrders($UPDATES, $TRANS_IDS);
    }

    /**
     * @expectedException \Exception
     */
    public function test_saveLogSaleOrders_wrongSizes() {
        /** === Test Data === */
        $UPDATES = [
            [ Calc::A_ORDR_ID => 212 ]
        ];
        $TRANS_IDS = [ 1, 2 ];
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolDate = $this->_mockFor('Praxigento\Core\Lib\Tool\Date');
        $mToolbox = $this->_mockToolbox(null, $mToolDate);
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Lib\Service\IAccount');
        $mCallOper = $this->_mockFor('Praxigento\Accounting\Lib\Service\IOperation');
        $mCallTypeAsset = $this->_mockFor('Praxigento\Accounting\Lib\Service\ITypeAsset');
        $mCallTypeCalc = $this->_mockFor('Praxigento\Bonus\Base\Lib\Service\ITypeCalc');
        $mCallTypeOper = $this->_mockFor('Praxigento\Accounting\Lib\Service\ITypeOperation');
        $mCallDownlineSnap = $this->_mockFor('Praxigento\Downline\Lib\Service\ISnap');

        /**
         * Prepare request and perform call.
         */
        /** @var  $sub Db */
        $sub = new Db(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallOper,
            $mCallTypeAsset,
            $mCallTypeCalc,
            $mCallTypeOper,
            $mCallDownlineSnap
        );
        $sub->saveLogSaleOrders($UPDATES, $TRANS_IDS);
    }

    public function test_saveOperationPvWriteOff() {
        /** === Test Data === */
        $DT_NOW = '2015-01-29 11:42:32';
        $ACC_REPRES = 1;
        $ACC_CUST_01 = 2;
        $ACC_CUST_02 = 3;
        $UPDATES = [
            $ACC_REPRES  => '10.00',
            $ACC_CUST_01 => '20.00',
            $ACC_CUST_02 => '0.00'
        ];
        $NEW_OPER_ID = 8;
        $ASSET_TYPE_ID = 16;
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolDate = $this->_mockFor('Praxigento\Core\Lib\Tool\Date');
        $mToolbox = $this->_mockToolbox(null, $mToolDate);
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Lib\Service\IAccount');
        $mCallOper = $this->_mockFor('Praxigento\Accounting\Lib\Service\IOperation');
        $mCallTypeAsset = $this->_mockFor('Praxigento\Accounting\Lib\Service\ITypeAsset');
        $mCallTypeCalc = $this->_mockFor('Praxigento\Bonus\Base\Lib\Service\ITypeCalc');
        $mCallTypeOper = $this->_mockFor('Praxigento\Accounting\Lib\Service\ITypeOperation');
        $mCallDownlineSnap = $this->_mockFor('Praxigento\Downline\Lib\Service\ISnap');

        // $datePerformed = $this->_toolbox->getDate()->getUtcNowForDb();
        $mToolDate
            ->expects($this->once())
            ->method('getUtcNowForDb')
            ->willReturn($DT_NOW);
        // $respTypeAsset = $this->_callTypeAsset->getByCode(new TypeAssetGetByCodeRequest(Cfg::CODE_TYPE_ASSET_WALLET_ACTIVE));
        $mRespTypeAsset = new TypeAssetGetByCodeResponse();
        $mCallTypeAsset
            ->expects($this->once())
            ->method('getByCode')
            ->willReturn($mRespTypeAsset);
        // $assetTypeId = $respTypeAsset->getId();
        $mRespTypeAsset->setId($ASSET_TYPE_ID);
        // $respAccRepres = $this->_callAccount->getRepresentative($reqAccRepres);
        $mRespAccRepres = new AccountGetRepresentativeResponse();
        $mCallAccount
            ->expects($this->once())
            ->method('getRepresentative')
            ->willReturn($mRespAccRepres);
        // $represAccId = $respAccRepres->getAccountId();
        $mRespAccRepres->setData(Account::ATTR_ID, $ACC_REPRES);
        // $resp = $this->_callOper->add($req);
        $mRespAdd = new OperationAddResponse();
        $mRespAdd->setOperationId($NEW_OPER_ID);
        $mCallOper
            ->expects($this->once())
            ->method('add')
            ->willReturn($mRespAdd);
        /**
         * Prepare request and perform call.
         */
        /** @var  $sub Db */
        $sub = new Db(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallOper,
            $mCallTypeAsset,
            $mCallTypeCalc,
            $mCallTypeOper,
            $mCallDownlineSnap
        );
        $res = $sub->saveOperationPvWriteOff($UPDATES);
        $this->assertEquals($NEW_OPER_ID, $res);
    }

    public function test_saveOperationWalletActive() {
        /** === Test Data === */
        $DT_NOW = '2015-01-29 11:42:32';
        $ACC_REPRES = 1;
        $ACC_CUST_01 = 2;
        $ACC_CUST_02 = 3;
        $UPDATES = [
            $ACC_REPRES  => [
                Calc::A_CUST_ID => $ACC_REPRES,
                Calc::A_VALUE   => '10.00'
            ],
            $ACC_CUST_01 => [
                Calc::A_CUST_ID => $ACC_CUST_01,
                Calc::A_VALUE   => '20.00'
            ],
            $ACC_CUST_02 => [
                Calc::A_CUST_ID => $ACC_CUST_02,
                Calc::A_VALUE   => '0.00'
            ]
        ];
        $NEW_OPER_ID = 8;
        $WALLET_ACTIVE_ASSET_TYPE_ID = 16;
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolDate = $this->_mockFor('Praxigento\Core\Lib\Tool\Date');
        $mToolbox = $this->_mockToolbox(null, $mToolDate);
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Lib\Service\IAccount');
        $mCallOper = $this->_mockFor('Praxigento\Accounting\Lib\Service\IOperation');
        $mCallTypeAsset = $this->_mockFor('Praxigento\Accounting\Lib\Service\ITypeAsset');
        $mCallTypeCalc = $this->_mockFor('Praxigento\Bonus\Base\Lib\Service\ITypeCalc');
        $mCallTypeOper = $this->_mockFor('Praxigento\Accounting\Lib\Service\ITypeOperation');
        $mCallDownlineSnap = $this->_mockFor('Praxigento\Downline\Lib\Service\ISnap');

        // $datePerformed = $this->_toolbox->getDate()->getUtcNowForDb();
        $mToolDate
            ->expects($this->once())
            ->method('getUtcNowForDb')
            ->willReturn($DT_NOW);
        // $respTypeAsset = $this->_callTypeAsset->getByCode(new TypeAssetGetByCodeRequest(Cfg::CODE_TYPE_ASSET_WALLET_ACTIVE));
        $mRespTypeAsset = new TypeAssetGetByCodeResponse();
        $mCallTypeAsset
            ->expects($this->once())
            ->method('getByCode')
            ->willReturn($mRespTypeAsset);
        // $walletActiveAssetTypeId = $respTypeAsset->getId();
        $mRespTypeAsset->setId($WALLET_ACTIVE_ASSET_TYPE_ID);
        // $respAccRepres = $this->_callAccount->getRepresentative($reqAccRepres);
        $mRespAccRepres = new AccountGetRepresentativeResponse();
        $mCallAccount
            ->expects($this->once())
            ->method('getRepresentative')
            ->willReturn($mRespAccRepres);
        // $walletActiveRepresAccId = $respAccRepres->getAccountId();
        $mRespAccRepres->setData(Account::ATTR_ID, $ACC_REPRES);
        // foreach($updates as $customerId => $value) {
        // $respGetAccount = $this->_callAccount->get($reqGetAccount);
        $mRespGetAccRepres = new AccountGetResponse();
        $mRespGetAccRepres->setData(Account::ATTR_ID, $ACC_REPRES);
        $mCallAccount
            ->expects($this->at(1))// 0 - is for Representative account request
            ->method('get')
            ->willReturn($mRespGetAccRepres);
        $mRespGetAcc = new AccountGetResponse();
        $mRespGetAcc->setData(Account::ATTR_ID, $ACC_CUST_01);
        $mCallAccount
            ->expects($this->at(2))
            ->method('get')
            ->willReturn($mRespGetAcc);
        // $resp = $this->_callOper->add($req);
        $mRespAdd = new OperationAddResponse();
        $mRespAdd->setOperationId($NEW_OPER_ID);
        $mCallOper
            ->expects($this->once())
            ->method('add')
            ->willReturn($mRespAdd);
        /**
         * Prepare request and perform call.
         */
        /** @var  $sub Db */
        $sub = new Db(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallOper,
            $mCallTypeAsset,
            $mCallTypeCalc,
            $mCallTypeOper,
            $mCallDownlineSnap
        );
        $res = $sub->saveOperationWalletActive($UPDATES, 'OPER_CODE');
        $this->assertEquals($NEW_OPER_ID, $res->getOperationId());
    }

    public function test_saveUpdatesOiCompress() {
        /** === Test Data === */
        $UPDATES = [
            32 => 1024
        ];
        $CALC_ID = 16;
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolbox = $this->_mockToolbox();
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Lib\Service\IAccount');
        $mCallOper = $this->_mockFor('Praxigento\Accounting\Lib\Service\IOperation');
        $mCallTypeAsset = $this->_mockFor('Praxigento\Accounting\Lib\Service\ITypeAsset');
        $mCallTypeCalc = $this->_mockFor('Praxigento\Bonus\Base\Lib\Service\ITypeCalc');
        $mCallTypeOper = $this->_mockFor('Praxigento\Accounting\Lib\Service\ITypeOperation');
        $mCallDownlineSnap = $this->_mockFor('Praxigento\Downline\Lib\Service\ISnap');

        // $this->_callRepo->updateEntity($req);
        $mCallRepo
            ->expects($this->once())
            ->method('updateEntity');

        /**
         * Prepare request and perform call.
         */
        /** @var  $sub Db */
        $sub = new Db(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallOper,
            $mCallTypeAsset,
            $mCallTypeCalc,
            $mCallTypeOper,
            $mCallDownlineSnap
        );
        $sub->saveUpdatesOiCompress($UPDATES, $CALC_ID);
    }

    public function test_saveValueOv() {
        /** === Test Data === */
        $DATA = [
            32 => 1024
        ];
        $CALC_ID = 16;
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolbox = $this->_mockToolbox();
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Lib\Service\IAccount');
        $mCallOper = $this->_mockFor('Praxigento\Accounting\Lib\Service\IOperation');
        $mCallTypeAsset = $this->_mockFor('Praxigento\Accounting\Lib\Service\ITypeAsset');
        $mCallTypeCalc = $this->_mockFor('Praxigento\Bonus\Base\Lib\Service\ITypeCalc');
        $mCallTypeOper = $this->_mockFor('Praxigento\Accounting\Lib\Service\ITypeOperation');
        $mCallDownlineSnap = $this->_mockFor('Praxigento\Downline\Lib\Service\ISnap');

        // $this->_callRepo->updateEntity($req);
        $mCallRepo
            ->expects($this->once())
            ->method('updateEntity');

        /**
         * Prepare request and perform call.
         */
        /** @var  $sub Db */
        $sub = new Db(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallOper,
            $mCallTypeAsset,
            $mCallTypeCalc,
            $mCallTypeOper,
            $mCallDownlineSnap
        );
        $sub->saveValueOv($DATA, $CALC_ID);
    }

    public function test_saveValueTv() {
        /** === Test Data === */
        $DATA = [
            32 => 1024
        ];
        $CALC_ID = 16;
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mToolbox = $this->_mockToolbox();
        $mCallRepo = $this->_mockCallRepo();
        $mCallAccount = $this->_mockFor('Praxigento\Accounting\Lib\Service\IAccount');
        $mCallOper = $this->_mockFor('Praxigento\Accounting\Lib\Service\IOperation');
        $mCallTypeAsset = $this->_mockFor('Praxigento\Accounting\Lib\Service\ITypeAsset');
        $mCallTypeCalc = $this->_mockFor('Praxigento\Bonus\Base\Lib\Service\ITypeCalc');
        $mCallTypeOper = $this->_mockFor('Praxigento\Accounting\Lib\Service\ITypeOperation');
        $mCallDownlineSnap = $this->_mockFor('Praxigento\Downline\Lib\Service\ISnap');

        // $this->_callRepo->updateEntity($req);
        $mCallRepo
            ->expects($this->once())
            ->method('updateEntity');

        /**
         * Prepare request and perform call.
         */
        /** @var  $sub Db */
        $sub = new Db(
            $mLogger,
            $mDba,
            $mToolbox,
            $mCallRepo,
            $mCallAccount,
            $mCallOper,
            $mCallTypeAsset,
            $mCallTypeCalc,
            $mCallTypeOper,
            $mCallDownlineSnap
        );
        $sub->saveValueTv($DATA, $CALC_ID);
    }

}