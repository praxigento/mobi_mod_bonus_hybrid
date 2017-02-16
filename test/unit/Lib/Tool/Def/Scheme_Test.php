<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Tool\Def;

use Flancer32\Lib\DataObject;
use Praxigento\BonusBase\Data\Entity\Rank;
use Praxigento\BonusHybrid\Defaults as Def;
use Praxigento\BonusHybrid\Entity\Cfg\Param as CfgParam;
use Praxigento\Downline\Data\Entity\Customer;

include_once(__DIR__ . '/../../../phpunit_bootstrap.php');

class Scheme_UnitTest extends \Praxigento\Core\Test\BaseCase\Mockery
{
    private $FORCED_CFG_PARAMS = [
        [
            Rank::ATTR_CODE => Def::RANK_PRESIDENT,
            CfgParam::ATTR_SCHEME => Def::SCHEMA_DEFAULT,
            CfgParam::ATTR_RANK_ID => 1,
            CfgParam::ATTR_QUALIFY_PV => 200,
            CfgParam::ATTR_QUALIFY_TV => 2000
        ],
        [
            Rank::ATTR_CODE => Def::RANK_MANAGER,
            CfgParam::ATTR_SCHEME => Def::SCHEMA_EU,
            CfgParam::ATTR_RANK_ID => 2,
            CfgParam::ATTR_QUALIFY_PV => 40,
            CfgParam::ATTR_QUALIFY_TV => 400
        ]
    ];
    private $FORCED_CUST_IDS = [
        [Customer::ATTR_CUSTOMER_ID => 1, Customer::ATTR_HUMAN_REF => '770000001'],
        [Customer::ATTR_CUSTOMER_ID => 2, Customer::ATTR_HUMAN_REF => '790003045']
    ];

    protected function setUp()
    {
        parent::setUp();
        $this->markTestSkipped('Test is deprecated after M1 & M2 merge is done.');
    }

    public function test_getForcedPv()
    {
        /** === Test Data === */
        $CUST_ID = 1;
        $PV = 100;
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mCallRepo = $this->_mockFor('\Praxigento\Core\Lib\Service\IRepo');
        $mCallRank = $this->_mockFor('\Praxigento\BonusBase\Service\IRank');

        // private function _getForcedCustomersIds() {...}
        // $resp = $this->_callRepo->getEntities($req);
        $mResp = new DataObject($this->FORCED_CUST_IDS);
        $mCallRepo
            ->expects($this->once())
            ->method('getEntities')
            ->willReturn($mResp);
        // private function _getCfgParamsByRanks() {...}
        // $query = $this->_getConn()->select();
        $mQuery = $this->_mockDbSelect();
        $mConn
            ->expects($this->once())
            ->method('select')
            ->willReturn($mQuery);
        // $entries = $this->_getConn()->fetchAll($query);
        $mConn
            ->expects($this->once())
            ->method('fetchAll')
            ->willReturn($this->FORCED_CFG_PARAMS);
        /** === Test itself === */
        $obj = new Scheme($mLogger, $mDba, $mCallRepo, $mCallRank);
        $res = $obj->getForcedPv($CUST_ID, Def::SCHEMA_DEFAULT, 100);
        $this->assertEquals(200, $res);
    }

    public function test_getForcedQualificationCustomers()
    {
        /** === Test Data === */
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mCallRepo = $this->_mockFor('\Praxigento\Core\Lib\Service\IRepo');
        $mCallRank = $this->_mockFor('\Praxigento\BonusBase\Service\IRank');

        // private function _getForcedCustomersIds() {...}
        // $resp = $this->_callRepo->getEntities($req);
        $mResp = new DataObject($this->FORCED_CUST_IDS);
        $mCallRepo
            ->expects($this->once())
            ->method('getEntities')
            ->willReturn($mResp);
        // private function _getCfgParamsByRanks() {...}
        // $query = $this->_getConn()->select();
        $mQuery = $this->_mockDbSelect();
        $mConn
            ->expects($this->once())
            ->method('select')
            ->willReturn($mQuery);
        // $entries = $this->_getConn()->fetchAll($query);
        $mConn
            ->expects($this->once())
            ->method('fetchAll')
            ->willReturn($this->FORCED_CFG_PARAMS);
        /** === Test itself === */
        $obj = new Scheme($mLogger, $mDba, $mCallRepo, $mCallRank);
        $res = $obj->getForcedQualificationCustomers();
        $this->assertTrue(is_array($res));
        $this->assertEquals(2, count($res));
    }

    public function test_getForcedQualificationCustomersIds()
    {
        /** === Test Data === */
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mCallRepo = $this->_mockFor('\Praxigento\Core\Lib\Service\IRepo');
        $mCallRank = $this->_mockFor('\Praxigento\BonusBase\Service\IRank');

        // private function _getForcedCustomersIds() {...}
        // $resp = $this->_callRepo->getEntities($req);
        $mResp = new DataObject($this->FORCED_CUST_IDS);
        $mCallRepo
            ->expects($this->once())
            ->method('getEntities')
            ->willReturn($mResp);
        // private function _getCfgParamsByRanks() {...}
        // $query = $this->_getConn()->select();
        $mQuery = $this->_mockDbSelect();
        $mConn
            ->expects($this->once())
            ->method('select')
            ->willReturn($mQuery);
        // $entries = $this->_getConn()->fetchAll($query);
        $mConn
            ->expects($this->once())
            ->method('fetchAll')
            ->willReturn($this->FORCED_CFG_PARAMS);
        /** === Test itself === */
        $obj = new Scheme($mLogger, $mDba, $mCallRepo, $mCallRank);
        $res = $obj->getForcedQualificationCustomersIds();
        $this->assertTrue(is_array($res));
        $this->assertEquals(2, count($res));
    }

    public function test_getForcedQualificationCustomers_emptyCache()
    {
        /** === Test Data === */
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mCallRepo = $this->_mockFor('\Praxigento\Core\Lib\Service\IRepo');
        $mCallRank = $this->_mockFor('\Praxigento\BonusBase\Service\IRank');

        // private function _getForcedCustomersIds() {...}
        // $resp = $this->_callRepo->getEntities($req);
        $mResp = new DataObject([]);
        $mCallRepo
            ->expects($this->once())
            ->method('getEntities')
            ->willReturn($mResp);
        // private function _getCfgParamsByRanks() {...}
        // $query = $this->_getConn()->select();
        $mQuery = $this->_mockDbSelect();
        $mConn
            ->expects($this->once())
            ->method('select')
            ->willReturn($mQuery);
        // $entries = $this->_getConn()->fetchAll($query);
        $mConn
            ->expects($this->once())
            ->method('fetchAll')
            ->willReturn($this->FORCED_CFG_PARAMS);
        /** === Test itself === */
        $obj = new Scheme($mLogger, $mDba, $mCallRepo, $mCallRank);
        $res = $obj->getForcedQualificationCustomers();
        $this->assertTrue(is_array($res));
        $this->assertEquals(0, count($res));
    }

    public function test_getForcedQualificationRank()
    {
        /** === Test Data === */
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mCallRepo = $this->_mockFor('\Praxigento\Core\Lib\Service\IRepo');
        $mCallRank = $this->_mockFor('\Praxigento\BonusBase\Service\IRank');

        // private function _getForcedCustomersIds() {...}
        // $resp = $this->_callRepo->getEntities($req);
        $mResp = new DataObject($this->FORCED_CUST_IDS);
        $mCallRepo
            ->expects($this->once())
            ->method('getEntities')
            ->willReturn($mResp);
        // private function _getCfgParamsByRanks() {...}
        // $query = $this->_getConn()->select();
        $mQuery = $this->_mockDbSelect();
        $mConn
            ->expects($this->once())
            ->method('select')
            ->willReturn($mQuery);
        // $entries = $this->_getConn()->fetchAll($query);
        $mConn
            ->expects($this->once())
            ->method('fetchAll')
            ->willReturn($this->FORCED_CFG_PARAMS);
        /** === Test itself === */
        $obj = new Scheme($mLogger, $mDba, $mCallRepo, $mCallRank);
        $res = $obj->getForcedQualificationRank(1, Def::SCHEMA_DEFAULT);
        $this->assertEquals(1, $res);
    }

    public function test_getForcedTv()
    {
        /** === Test Data === */
        $CUST_ID = 1;
        $PV = 100;
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mCallRepo = $this->_mockFor('\Praxigento\Core\Lib\Service\IRepo');
        $mCallRank = $this->_mockFor('\Praxigento\BonusBase\Service\IRank');

        // private function _getForcedCustomersIds() {...}
        // $resp = $this->_callRepo->getEntities($req);
        $mResp = new DataObject($this->FORCED_CUST_IDS);
        $mCallRepo
            ->expects($this->once())
            ->method('getEntities')
            ->willReturn($mResp);
        // private function _getCfgParamsByRanks() {...}
        // $query = $this->_getConn()->select();
        $mQuery = $this->_mockDbSelect();
        $mConn
            ->expects($this->once())
            ->method('select')
            ->willReturn($mQuery);
        // $entries = $this->_getConn()->fetchAll($query);
        $mConn
            ->expects($this->once())
            ->method('fetchAll')
            ->willReturn($this->FORCED_CFG_PARAMS);
        /** === Test itself === */
        $obj = new Scheme($mLogger, $mDba, $mCallRepo, $mCallRank);
        $res = $obj->getForcedTv($CUST_ID, Def::SCHEMA_DEFAULT, 1000);
        $this->assertEquals(2000, $res);
    }

    public function test_getQualificationLevels()
    {
        /** === Test Data === */
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mCallRepo = $this->_mockFor('\Praxigento\Core\Lib\Service\IRepo');
        $mCallRank = $this->_mockFor('\Praxigento\BonusBase\Service\IRank');

        /** === Test itself === */
        $obj = new Scheme($mLogger, $mDba, $mCallRepo, $mCallRank);
        $res = $obj->getQualificationLevels();
        $this->assertTrue(is_array($res));
        $this->assertEquals(2, count($res));
    }

    public function test_getSchemeByCustomer()
    {
        /** === Test Data === */
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mConn = $this->_mockConnection();
        $mDba = $this->_mockDbAdapter(null, $mConn);
        $mCallRepo = $this->_mockFor('\Praxigento\Core\Lib\Service\IRepo');
        $mCallRank = $this->_mockFor('\Praxigento\BonusBase\Service\IRank');
        /** === Test itself === */
        $obj = new Scheme($mLogger, $mDba, $mCallRepo, $mCallRank);
        $res = $obj->getSchemeByCustomer([Customer::ATTR_COUNTRY_CODE => 'LV']);
        $this->assertEquals(Def::SCHEMA_DEFAULT, $res);
        $res = $obj->getSchemeByCustomer([Customer::ATTR_COUNTRY_CODE => 'AT']);
        $this->assertEquals(Def::SCHEMA_EU, $res);
        $res = $obj->getSchemeByCustomer([Customer::ATTR_COUNTRY_CODE => 'DE']);
        $this->assertEquals(Def::SCHEMA_EU, $res);
        $res = $obj->getSchemeByCustomer([Customer::ATTR_COUNTRY_CODE => 'ES']);
        $this->assertEquals(Def::SCHEMA_EU, $res);
    }

}