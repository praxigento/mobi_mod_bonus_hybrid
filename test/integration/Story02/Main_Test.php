<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\Bonus\Hybrid\Lib\Test\Story02;

use Praxigento\Bonus\Base\Lib\Entity\Calculation;
use Praxigento\Bonus\Base\Lib\Entity\Period;
use Praxigento\Bonus\Base\Lib\Entity\Rank;
use Praxigento\Bonus\Base\Lib\Service\Rank\Request\GetByCode as RankGetByCodeRequest;
use Praxigento\Bonus\Base\Lib\Service\Type\Calc\Request\GetByCode as TypeCalcGetByCodeRequest;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\Bonus\Hybrid\Lib\Entity\Cfg\Param as CfgParam;
use Praxigento\Bonus\Hybrid\Lib\Entity\Compression\Ptc as PtcCompression;
use Praxigento\Bonus\Hybrid\Lib\Service\Calc\Request\BonusOverride as CalcBonusInfinityRequest;
use Praxigento\Bonus\Hybrid\Lib\Service\Calc\Request\BonusOverride as CalcBonusOverrideRequest;
use Praxigento\Bonus\Hybrid\Lib\Service\Calc\Request\CompressOi as CalcCompressOiRequest;
use Praxigento\Core\Lib\Context;
use Praxigento\Core\Lib\Service\Repo\Request\AddEntity as RepoAddEntityRequest;
use Praxigento\Core\Lib\Test\BaseIntegrationTest;
use Praxigento\Downline\Data\Entity\Snap;
use Praxigento\Downline\Lib\Service\Snap\Request\ExpandMinimal as SnapExtendMinimalRequest;

include_once(__DIR__ . '/../phpunit_bootstrap.php');

class Main_IntegrationTest extends BaseIntegrationTest {
    const PERIOD_DS_BEGIN = '20160101';
    const PERIOD_DS_END = '20160131';
    const QUALIFICATION_PV = 100;
    const QUALIFICATION_TV = 750;
    const RANK_DIR = 'DIRECTOR';
    const RANK_DIR_S = 'SENIOR DIRECTOR';
    const RANK_MAN = 'MANAGER';
    const RANK_MAN_S = 'SENIOR MANAGER';
    const RANK_SUPER = 'SUPERVISOR';
    /** @var \Praxigento\Accounting\Lib\Service\IAccount */
    private $_callAccount;
    /** @var \Praxigento\Bonus\Hybrid\Lib\Service\ICalc */
    private $_callCalc;
    /** @var \Praxigento\Accounting\Lib\Service\IOperation */
    private $_callOperation;
    /** @var \Praxigento\Bonus\Hybrid\Lib\Service\IPeriod */
    private $_callPeriod;
    /** @var  \Praxigento\Pv\Lib\Service\ITransfer */
    private $_callPvTransfer;
    /** @var \Praxigento\Bonus\Base\Lib\Service\IRank */
    private $_callRank;
    /** @var \Praxigento\Core\Lib\Service\IRepo */
    private $_callRepo;
    /** @var \Praxigento\Accounting\Lib\Service\ITypeAsset */
    private $_callTypeAsset;
    /** @var \Praxigento\Bonus\Base\Lib\Service\ITypeCalc */
    private $_callTypeCalc;
    private $_testCalcIdPtc;

    public function __construct() {
        parent::__construct();
        $this->_callAccount = $this->_manObj->get(\Praxigento\Accounting\Lib\Service\IAccount::class);
        $this->_callOperation = $this->_manObj->get(\Praxigento\Accounting\Lib\Service\IOperation::class);
        $this->_callTypeAsset = $this->_manObj->get(\Praxigento\Accounting\Lib\Service\ITypeAsset::class);
        $this->_callTypeCalc = $this->_manObj->get(\Praxigento\Bonus\Base\Lib\Service\ITypeCalc::class);
        $this->_callCalc = $this->_manObj->get(\Praxigento\Bonus\Hybrid\Lib\Service\ICalc::class);
        $this->_callPeriod = $this->_manObj->get(\Praxigento\Bonus\Hybrid\Lib\Service\IPeriod::class);
        $this->_callRank = $this->_manObj->get(\Praxigento\Bonus\Base\Lib\Service\IRank::class);
        $this->_callPvTransfer = $this->_manObj->get(\Praxigento\Pv\Lib\Service\ITransfer::class);
        $this->_callRepo = $this->_manObj->get(\Praxigento\Core\Lib\Service\IRepo::class);
    }

    private function _calcBonusInfinity() {
        $req = new CalcBonusInfinityRequest();
        $resp = $this->_callCalc->bonusInfinity($req);
        $this->assertTrue($resp->isSucceed());
    }

    private function _calcBonusOverride() {
        $req = new CalcBonusOverrideRequest();
        $req->setQualificationLevelPv(self::QUALIFICATION_PV);
        $req->setQualificationLevelTv(self::QUALIFICATION_TV);
        $resp = $this->_callCalc->bonusOverride($req);
        $this->assertTrue($resp->isSucceed());
    }

    private function _calcCompressionOi() {
        $req = new CalcCompressOiRequest();
        $req->setQualificationLevelPv(self::QUALIFICATION_PV);
        $req->setQualificationLevelTv(self::QUALIFICATION_TV);
        $resp = $this->_callCalc->compressOi($req);
        $this->assertTrue($resp->isSucceed());
    }

    private function _setCompressedPtc() {
        $data = [
            [
                PtcCompression::ATTR_CUSTOMER_ID => 1,
                PtcCompression::ATTR_PARENT_ID   => 1,
                PtcCompression::ATTR_PV          => 100,
                PtcCompression::ATTR_TV          => 750,
                PtcCompression::ATTR_OV          => 750
            ], [
                PtcCompression::ATTR_CUSTOMER_ID => 2,
                PtcCompression::ATTR_PARENT_ID   => 1,
                PtcCompression::ATTR_PV          => 100,
                PtcCompression::ATTR_TV          => 750,
                PtcCompression::ATTR_OV          => 4550
            ], [
                PtcCompression::ATTR_CUSTOMER_ID => 3,
                PtcCompression::ATTR_PARENT_ID   => 1,
                PtcCompression::ATTR_PV          => 100,
                PtcCompression::ATTR_TV          => 750,
                PtcCompression::ATTR_OV          => 3755
            ], [
                PtcCompression::ATTR_CUSTOMER_ID => 4,
                PtcCompression::ATTR_PARENT_ID   => 1,
                PtcCompression::ATTR_PV          => 100,
                PtcCompression::ATTR_TV          => 750,
                PtcCompression::ATTR_OV          => 2255
            ], [
                PtcCompression::ATTR_CUSTOMER_ID => 5,
                PtcCompression::ATTR_PARENT_ID   => 3,
                PtcCompression::ATTR_PV          => 40,
                PtcCompression::ATTR_TV          => 750,
                PtcCompression::ATTR_OV          => 750
            ], [
                PtcCompression::ATTR_CUSTOMER_ID => 6,
                PtcCompression::ATTR_PARENT_ID   => 3,
                PtcCompression::ATTR_PV          => 100,
                PtcCompression::ATTR_TV          => 750,
                PtcCompression::ATTR_OV          => 750
            ], [
                PtcCompression::ATTR_CUSTOMER_ID => 7,
                PtcCompression::ATTR_PARENT_ID   => 3,
                PtcCompression::ATTR_PV          => 100,
                PtcCompression::ATTR_TV          => 750,
                PtcCompression::ATTR_OV          => 750
            ], [
                PtcCompression::ATTR_CUSTOMER_ID => 8,
                PtcCompression::ATTR_PARENT_ID   => 5,
                PtcCompression::ATTR_PV          => 100,
                PtcCompression::ATTR_TV          => 750,
                PtcCompression::ATTR_OV          => 750
            ], [
                PtcCompression::ATTR_CUSTOMER_ID => 9,
                PtcCompression::ATTR_PARENT_ID   => 6,
                PtcCompression::ATTR_PV          => 40,
                PtcCompression::ATTR_TV          => 750,
                PtcCompression::ATTR_OV          => 750
            ], [
                PtcCompression::ATTR_CUSTOMER_ID => 10,
                PtcCompression::ATTR_PARENT_ID   => 9,
                PtcCompression::ATTR_PV          => 100,
                PtcCompression::ATTR_TV          => 750,
                PtcCompression::ATTR_OV          => 750
            ], [
                PtcCompression::ATTR_CUSTOMER_ID => 11,
                PtcCompression::ATTR_PARENT_ID   => 10,
                PtcCompression::ATTR_PV          => 100,
                PtcCompression::ATTR_TV          => 750,
                PtcCompression::ATTR_OV          => 750
            ], [
                PtcCompression::ATTR_CUSTOMER_ID => 12,
                PtcCompression::ATTR_PARENT_ID   => 10,
                PtcCompression::ATTR_PV          => 100,
                PtcCompression::ATTR_TV          => 750,
                PtcCompression::ATTR_OV          => 750
            ], [
                PtcCompression::ATTR_CUSTOMER_ID => 13,
                PtcCompression::ATTR_PARENT_ID   => 10,
                PtcCompression::ATTR_PV          => 100,
                PtcCompression::ATTR_TV          => 750,
                PtcCompression::ATTR_OV          => 750
            ], [
                PtcCompression::ATTR_CUSTOMER_ID => 14,
                PtcCompression::ATTR_PARENT_ID   => 11,
                PtcCompression::ATTR_PV          => 100,
                PtcCompression::ATTR_TV          => 750,
                PtcCompression::ATTR_OV          => 750
            ], [
                PtcCompression::ATTR_CUSTOMER_ID => 15,
                PtcCompression::ATTR_PARENT_ID   => 11,
                PtcCompression::ATTR_PV          => 100,
                PtcCompression::ATTR_TV          => 750,
                PtcCompression::ATTR_OV          => 750
            ], [
                PtcCompression::ATTR_CUSTOMER_ID => 16,
                PtcCompression::ATTR_PARENT_ID   => 12,
                PtcCompression::ATTR_PV          => 100,
                PtcCompression::ATTR_TV          => 750,
                PtcCompression::ATTR_OV          => 750
            ], [
                PtcCompression::ATTR_CUSTOMER_ID => 17,
                PtcCompression::ATTR_PARENT_ID   => 12,
                PtcCompression::ATTR_PV          => 100,
                PtcCompression::ATTR_TV          => 750,
                PtcCompression::ATTR_OV          => 750
            ], [
                PtcCompression::ATTR_CUSTOMER_ID => 18,
                PtcCompression::ATTR_PARENT_ID   => 13,
                PtcCompression::ATTR_PV          => 100,
                PtcCompression::ATTR_TV          => 750,
                PtcCompression::ATTR_OV          => 750
            ], [
                PtcCompression::ATTR_CUSTOMER_ID => 19,
                PtcCompression::ATTR_PARENT_ID   => 13,
                PtcCompression::ATTR_PV          => 100,
                PtcCompression::ATTR_TV          => 800,
                PtcCompression::ATTR_OV          => 800
            ], [
                PtcCompression::ATTR_CUSTOMER_ID => 20,
                PtcCompression::ATTR_PARENT_ID   => 13,
                PtcCompression::ATTR_PV          => 100,
                PtcCompression::ATTR_TV          => 900,
                PtcCompression::ATTR_OV          => 900
            ], [
                PtcCompression::ATTR_CUSTOMER_ID => 21,
                PtcCompression::ATTR_PARENT_ID   => 14,
                PtcCompression::ATTR_PV          => 100,
                PtcCompression::ATTR_TV          => 750,
                PtcCompression::ATTR_OV          => 750
            ], [
                PtcCompression::ATTR_CUSTOMER_ID => 22,
                PtcCompression::ATTR_PARENT_ID   => 15,
                PtcCompression::ATTR_PV          => 100,
                PtcCompression::ATTR_TV          => 750,
                PtcCompression::ATTR_OV          => 750
            ], [
                PtcCompression::ATTR_CUSTOMER_ID => 23,
                PtcCompression::ATTR_PARENT_ID   => 16,
                PtcCompression::ATTR_PV          => 100,
                PtcCompression::ATTR_TV          => 750,
                PtcCompression::ATTR_OV          => 750
            ], [
                PtcCompression::ATTR_CUSTOMER_ID => 24,
                PtcCompression::ATTR_PARENT_ID   => 17,
                PtcCompression::ATTR_PV          => 100,
                PtcCompression::ATTR_TV          => 750,
                PtcCompression::ATTR_OV          => 750
            ], [
                PtcCompression::ATTR_CUSTOMER_ID => 25,
                PtcCompression::ATTR_PARENT_ID   => 18,
                PtcCompression::ATTR_PV          => 100,
                PtcCompression::ATTR_TV          => 750,
                PtcCompression::ATTR_OV          => 750
            ]
        ];
        /* replace customer indexes by Magento IDs */
        $tree = [ ]; // tree data as [$custId => $parentId, ...]
        foreach($data as $key => $one) {
            $custNdx = $one[PtcCompression::ATTR_CUSTOMER_ID];
            $parentNdx = $one[PtcCompression::ATTR_PARENT_ID];
            $custId = $this->_mapCustomerMageIdByIndex[$custNdx];
            $parentId = $this->_mapCustomerMageIdByIndex[$parentNdx];
            $data[$key][PtcCompression::ATTR_CUSTOMER_ID] = $custId;
            $data[$key][PtcCompression::ATTR_PARENT_ID] = $parentId;
            $tree[$custId] = $parentId;
        }
        /* populate tree with depth & path */
        $reqExtend = new SnapExtendMinimalRequest();
        $reqExtend->setTree($tree);
        $respExtend = $this->_callDownlineSnap->expandMinimal($reqExtend);
        $snap = $respExtend->getSnapData(); // [$custId=>[...], ...]
        /* populate initial data with depth & path */
        foreach($data as $key => $one) {
            $custId = $one[PtcCompression::ATTR_CUSTOMER_ID];
            $snapEntry = $snap[$custId];
            $data[$key][PtcCompression::ATTR_DEPTH] = $snapEntry[Snap::ATTR_DEPTH];
            $data[$key][PtcCompression::ATTR_PATH] = $snapEntry[Snap::ATTR_PATH];
        }
        /* save data into DB */
        $reqAdd = new RepoAddEntityRequest(PtcCompression::ENTITY_NAME);
        foreach($data as $one) {
            $one[PtcCompression::ATTR_CALC_ID] = $this->_testCalcIdPtc;
            $reqAdd->setBind($one);
            $this->_callRepo->addEntity($reqAdd);
        }
        $this->_logger->debug("PTC compression data is set.");
    }

    private function _setConfig() {
        $data = [
            [
                CfgParam::ATTR_RANK_ID    => self::RANK_MAN,
                CfgParam::ATTR_LEG_MAX    => 0,
                CfgParam::ATTR_LEG_MEDIUM => 0,
                CfgParam::ATTR_LEG_MIN    => 0,
                CfgParam::ATTR_INFINITY   => 0
            ], [
                CfgParam::ATTR_RANK_ID    => self::RANK_MAN_S,
                CfgParam::ATTR_LEG_MAX    => 750,
                CfgParam::ATTR_LEG_MEDIUM => 0,
                CfgParam::ATTR_LEG_MIN    => 0,
                CfgParam::ATTR_INFINITY   => 0
            ], [
                CfgParam::ATTR_RANK_ID    => self::RANK_SUPER,
                CfgParam::ATTR_LEG_MAX    => 1500,
                CfgParam::ATTR_LEG_MEDIUM => 750,
                CfgParam::ATTR_LEG_MIN    => 0,
                CfgParam::ATTR_INFINITY   => 0
            ], [
                CfgParam::ATTR_RANK_ID    => self::RANK_DIR,
                CfgParam::ATTR_LEG_MAX    => 2250,
                CfgParam::ATTR_LEG_MEDIUM => 1500,
                CfgParam::ATTR_LEG_MIN    => 1000,
                CfgParam::ATTR_INFINITY   => 0.01
            ], [
                CfgParam::ATTR_RANK_ID    => self::RANK_DIR_S,
                CfgParam::ATTR_LEG_MAX    => 4500,
                CfgParam::ATTR_LEG_MEDIUM => 3750,
                CfgParam::ATTR_LEG_MIN    => 2250,
                CfgParam::ATTR_INFINITY   => 0.02
            ]
        ];
        $req = new RepoAddEntityRequest(CfgParam::ENTITY_NAME);
        foreach($data as $item) {
            /* replace rank code by rank ID */
            $respRankByCode = $this->_callRank->getByCode(new RankGetByCodeRequest($item[CfgParam::ATTR_RANK_ID]));
            $rankId = $respRankByCode->getId();
            $item[CfgParam::ATTR_RANK_ID] = $rankId;
            $req->setBind($item);
            $this->_callRepo->addEntity($req);
        }
        $this->_logger->debug("Ranks configuration is set.");
    }

    /**
     * Add periods for PTC, TV & OV and related calculations.
     */
    private function _setPeriodAndCalcs() {
        /* get calculation types */
        $reqGetByCode = new TypeCalcGetByCodeRequest();
        $reqGetByCode->setCode(Cfg::CODE_TYPE_CALC_COMPRESS_FOR_PTC);
        $respTypeCalc = $this->_callTypeCalc->getByCode($reqGetByCode);
        $calcTypeIdPtc = $respTypeCalc->getId();
        $reqGetByCode->setCode(Cfg::CODE_TYPE_CALC_VALUE_TV);
        $respTypeCalc = $this->_callTypeCalc->getByCode($reqGetByCode);
        $calcTypeIdTv = $respTypeCalc->getId();
        $reqGetByCode->setCode(Cfg::CODE_TYPE_CALC_VALUE_OV);
        $respTypeCalc = $this->_callTypeCalc->getByCode($reqGetByCode);
        $calcTypeIdOv = $respTypeCalc->getId();
        /* add periods */
        $reqAdd = new RepoAddEntityRequest(Period::ENTITY_NAME);
        // PTC
        $bind = [
            Period::ATTR_CALC_TYPE_ID => $calcTypeIdPtc,
            Period::ATTR_DSTAMP_BEGIN => self::PERIOD_DS_BEGIN,
            Period::ATTR_DSTAMP_END   => self::PERIOD_DS_END
        ];
        $reqAdd->setBind($bind);
        $respAdd = $this->_callRepo->addEntity($reqAdd);
        $periodIdPtc = $respAdd->getIdInserted();
        $this->_logger->debug("Period for calculation " . Cfg::CODE_TYPE_CALC_COMPRESS_FOR_PTC . " is added with ID=$periodIdPtc.");
        // TV
        $bind[Period::ATTR_CALC_TYPE_ID] = $calcTypeIdTv;
        $reqAdd->setBind($bind);
        $respAdd = $this->_callRepo->addEntity($reqAdd);
        $periodIdTv = $respAdd->getIdInserted();
        $this->_logger->debug("Period for calculation " . Cfg::CODE_TYPE_CALC_VALUE_TV . " is added with ID=$periodIdTv.");
        // OV
        $bind[Period::ATTR_CALC_TYPE_ID] = $calcTypeIdOv;
        $reqAdd->setBind($bind);
        $respAdd = $this->_callRepo->addEntity($reqAdd);
        $periodIdOv = $respAdd->getIdInserted();
        $this->_logger->debug("Period for calculation " . Cfg::CODE_TYPE_CALC_VALUE_OV . " is added with ID=$periodIdOv.");
        /* add calculations*/
        $reqAdd->setEntity(Calculation::ENTITY_NAME);
        $bind = [ Calculation::ATTR_STATE => Cfg::CALC_STATE_COMPLETE ];
        // PTC
        $bind[Calculation::ATTR_PERIOD_ID] = $periodIdPtc;
        $reqAdd->setBind($bind);
        $respAdd = $this->_callRepo->addEntity($reqAdd);
        $calcPtcId = $respAdd->getIdInserted();
        $this->_logger->debug("Calculation " . Cfg::CODE_TYPE_CALC_COMPRESS_FOR_PTC . " is added with ID=$calcPtcId.");
        // TV
        $bind[Calculation::ATTR_PERIOD_ID] = $periodIdTv;
        $reqAdd->setBind($bind);
        $respAdd = $this->_callRepo->addEntity($reqAdd);
        $calcTvId = $respAdd->getIdInserted();
        $this->_logger->debug("Calculation " . Cfg::CODE_TYPE_CALC_VALUE_TV . " is added with ID=$calcTvId.");
        // OV
        $bind[Calculation::ATTR_PERIOD_ID] = $periodIdOv;
        $reqAdd->setBind($bind);
        $respAdd = $this->_callRepo->addEntity($reqAdd);
        $calcOvId = $respAdd->getIdInserted();
        $this->_logger->debug("Calculation " . Cfg::CODE_TYPE_CALC_VALUE_OV . " is added with ID=$calcOvId.");
        /* save intermediary test data */
        $this->_testCalcIdPtc = $calcPtcId;
    }

    private function _setRanks() {
        $data = [
            [ Rank::ATTR_CODE => 'MANAGER', Rank::ATTR_NOTE => 'Manager' ],
            [ Rank::ATTR_CODE => 'SENIOR MANAGER', Rank::ATTR_NOTE => 'Senior Manager' ],
            [ Rank::ATTR_CODE => 'SUPERVISOR', Rank::ATTR_NOTE => 'Supervisor' ],
            [ Rank::ATTR_CODE => 'DIRECTOR', Rank::ATTR_NOTE => 'Director' ],
            [ Rank::ATTR_CODE => 'SENIOR DIRECTOR', Rank::ATTR_NOTE => 'Senior Director' ]
        ];
        $req = new RepoAddEntityRequest(Rank::ENTITY_NAME);
        foreach($data as $item) {
            $req->setBind($item);
            $resp = $this->_callRepo->addEntity($req);
            $this->_logger->debug("Rank '" . $item[Rank::ATTR_CODE] . "' is added with ID=" . $resp->getIdInserted() . ".");
        }
        $this->_logger->debug("Ranks are set.");
    }

    public function test_main() {
        $this->_logger->debug('Story02 in Hybrid Bonus Integration tests is started.');
        $this->_conn->beginTransaction();
        try {
            $this->_setRanks();
            $this->_setConfig();
            $this->_createMageCustomers(25);
            $this->_setPeriodAndCalcs();
            $this->_setCompressedPtc();
            $this->_calcCompressionOi();
            $this->_calcBonusOverride();
            $this->_calcBonusInfinity();
        } catch(\Exception $e) {
            $this->_logger->error("Exception in integration test. Message: " . $e->getMessage());
        } finally {
            // $this->_conn->commit();
            $this->_conn->rollBack();
        }
        $this->_logger->debug('Story02 in Hybrid Bonus Integration tests is completed, all transactions are rolled back.');
    }
}