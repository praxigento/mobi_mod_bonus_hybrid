<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Service\Calc\Sub;

use Flancer32\Lib\DataObject;
use Praxigento\Accounting\Data\Entity\Account;
use Praxigento\Accounting\Data\Entity\Transaction;
use Praxigento\BonusHybrid\Defaults as Def;
use Praxigento\BonusHybrid\Entity\Cfg\Override as CfgOverride;
use Praxigento\BonusHybrid\Entity\Cfg\Param as CfgParam;
use Praxigento\BonusHybrid\Entity\Compression\Oi as OiCompress;
use Praxigento\BonusHybrid\Entity\Compression\Ptc as PtcCompress;
use Praxigento\Core\Tool\IFormat as ToolFormat;
use Praxigento\Downline\Data\Entity\Customer;
use Praxigento\Downline\Data\Entity\Snap;
use Praxigento\Downline\Service\Snap\Response\ExpandMinimal as DownlineSnapExtendMinimalResponse;
use Praxigento\Downline\Tool\Def\Tree as ToolDownlineTree;

include_once(__DIR__ . '/../../../../phpunit_bootstrap.php');

class Calc_UnitTest extends \Praxigento\BonusHybrid\Test\BaseTestCase
{
    private $CFG_OVERRIDE = [
        Def::SCHEMA_DEFAULT => [
            1 => [CfgParam::ATTR_RANK_ID => 1]
        ]
    ];
    private $CFG_PARAMS = [
        Def::SCHEMA_DEFAULT => [
            1 => [
                CfgParam::ATTR_RANK_ID => 1,
                CfgParam::ATTR_INFINITY => 0.03,
                CfgParam::ATTR_QUALIFY_PV => 10,
                CfgParam::ATTR_QUALIFY_TV => 100,
                CfgParam::ATTR_LEG_MAX => 300,
                CfgParam::ATTR_LEG_MEDIUM => 200,
                CfgParam::ATTR_LEG_MIN => 100
            ],
            2 => [
                CfgParam::ATTR_RANK_ID => 2,
                CfgParam::ATTR_INFINITY => 0.02,
                CfgParam::ATTR_QUALIFY_PV => 10,
                CfgParam::ATTR_QUALIFY_TV => 100,
                CfgParam::ATTR_LEG_MAX => 300,
                CfgParam::ATTR_LEG_MEDIUM => 200,
                CfgParam::ATTR_LEG_MIN => 100
            ]
        ]
    ];
    private $COURTESY_PERCENT = 0.05;
    /** @var array Levels  for Personal Bonus */
    private $LEVELS_PERS = [0 => 0, 50 => 0.05, 100 => 0.10, 500 => 0.15, 750 => 0.2];
    /** @var array Levels  for Team Bonus */
    private $LEVELS_TEAM = [0 => 0, 50 => 0.10, 500 => 0.15, 750 => 0.2];

    protected function setUp()
    {
        parent::setUp();
        $this->markTestSkipped('Test is deprecated after M1 & M2 merge is done.');
    }

    public function test__calcOverrideBonusByRank()
    {
        /** === Test Data === */
        $CUST_ID = 1;
        $CFG_OVR = [2 => [CfgOverride::ATTR_PERCENT => 0.10]];
        $MAP_GEN = [$CUST_ID => [2 => [2, 3]]];
        $MAP_ID = [
            2 => [OiCompress::ATTR_PV => 100],
            3 => [OiCompress::ATTR_PV => 10]
        ];
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mToolFormat = new ToolFormat();
        $mToolbox = $this->_mockToolbox(null, null, $mToolFormat);
        $mCallDownlineSnap = $this->_mockFor('\Praxigento\Downline\Service\ISnap');

        /**
         * Prepare request and perform call.
         */
        /** @var  $sub Calc */
        $sub = new Calc($mLogger, $mToolbox, $mCallDownlineSnap);
        $data = $sub->_calcOverrideBonusByRank($CUST_ID, $CFG_OVR, $MAP_GEN, $MAP_ID);
        $this->assertTrue(is_array($data));
    }

    public function test__getMaxQualifiedRankId()
    {
        /** === Test Data === */
        $LEGS_3 = [
            OiCompress::ATTR_CUSTOMER_ID => 1,
            OiCompress::ATTR_PV => 100,
            OiCompress::ATTR_TV => 1000,
            OiCompress::ATTR_OV_LEG_MAX => 310,
            OiCompress::ATTR_OV_LEG_SECOND => 210,
            OiCompress::ATTR_OV_LEG_SUMMARY => 110
        ];
        $LEGS_2 = [
            OiCompress::ATTR_CUSTOMER_ID => 2,
            OiCompress::ATTR_PV => 100,
            OiCompress::ATTR_TV => 1000,
            OiCompress::ATTR_OV_LEG_MAX => 310,
            OiCompress::ATTR_OV_LEG_SECOND => 210,
            OiCompress::ATTR_OV_LEG_SUMMARY => 0
        ];
        $LEGS_1 = [
            OiCompress::ATTR_CUSTOMER_ID => 3,
            OiCompress::ATTR_PV => 100,
            OiCompress::ATTR_TV => 1000,
            OiCompress::ATTR_OV_LEG_MAX => 310,
            OiCompress::ATTR_OV_LEG_SECOND => 0,
            OiCompress::ATTR_OV_LEG_SUMMARY => 0
        ];
        $LEGS_0 = [
            OiCompress::ATTR_CUSTOMER_ID => 3,
            OiCompress::ATTR_PV => 100,
            OiCompress::ATTR_TV => 1000,
            OiCompress::ATTR_OV_LEG_MAX => 0,
            OiCompress::ATTR_OV_LEG_SECOND => 0,
            OiCompress::ATTR_OV_LEG_SUMMARY => 0
        ];
        $CFG_PARAM = [
            Def::SCHEMA_DEFAULT => [
                [
                    CfgParam::ATTR_RANK_ID => 64,
                    CfgParam::ATTR_QUALIFY_PV => 10,
                    CfgParam::ATTR_QUALIFY_TV => 10,
                    CfgParam::ATTR_LEG_MAX => 300,
                    CfgParam::ATTR_LEG_MEDIUM => 200,
                    CfgParam::ATTR_LEG_MIN => 100
                ],
                [
                    CfgParam::ATTR_RANK_ID => 32,
                    CfgParam::ATTR_QUALIFY_PV => 10,
                    CfgParam::ATTR_QUALIFY_TV => 10,
                    CfgParam::ATTR_LEG_MAX => 300,
                    CfgParam::ATTR_LEG_MEDIUM => 200,
                    CfgParam::ATTR_LEG_MIN => 0
                ],
                [
                    CfgParam::ATTR_RANK_ID => 16,
                    CfgParam::ATTR_QUALIFY_PV => 10,
                    CfgParam::ATTR_QUALIFY_TV => 10,
                    CfgParam::ATTR_LEG_MAX => 300,
                    CfgParam::ATTR_LEG_MEDIUM => 0,
                    CfgParam::ATTR_LEG_MIN => 0
                ],
                [
                    CfgParam::ATTR_RANK_ID => 8,
                    CfgParam::ATTR_QUALIFY_PV => 10,
                    CfgParam::ATTR_QUALIFY_TV => 10,
                    CfgParam::ATTR_LEG_MAX => 0,
                    CfgParam::ATTR_LEG_MEDIUM => 0,
                    CfgParam::ATTR_LEG_MIN => 0
                ]
            ]
        ];
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mToolScheme = $this->_mockFor('\Praxigento\BonusHybrid\Tool\IScheme');
        $mToolbox = $this->_mockToolbox(null, null, null, null, $mToolScheme);
        $mCallDownlineSnap = $this->_mockFor('\Praxigento\Downline\Service\ISnap');

        // $forcedRankId = $this->_toolScheme->getForcedQualificationRank($custId, $scheme);
        $mToolScheme
            ->expects($this->any())
            ->method('getForcedQualificationRank')
            ->willReturn(null);

        /**
         * Prepare request and perform call.
         */
        /** @var  $sub Calc */
        $sub = new Calc($mLogger, $mToolbox, $mCallDownlineSnap);
        $data = $sub->_getMaxQualifiedRankId($LEGS_3, Def::SCHEMA_DEFAULT, $CFG_PARAM);
        $this->assertNotNull($data);
        $data = $sub->_getMaxQualifiedRankId($LEGS_2, Def::SCHEMA_DEFAULT, $CFG_PARAM);
        $this->assertNotNull($data);
        $data = $sub->_getMaxQualifiedRankId($LEGS_1, Def::SCHEMA_DEFAULT, $CFG_PARAM);
        $this->assertNotNull($data);
        $data = $sub->_getMaxQualifiedRankId($LEGS_0, Def::SCHEMA_DEFAULT, $CFG_PARAM);
        $this->assertNotNull($data);
    }

    public function test__mapByGeneration()
    {
        /** === Test Data === */
        $DATA = [
            0 => [1],
            1 => [2],
            2 => [3]
        ];
        $TREE = [
            1 => [Snap::ATTR_PATH => '/'],
            2 => [Snap::ATTR_PATH => '/1/'],
            3 => [Snap::ATTR_PATH => '/1/2/']
        ];
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mToolDownlineTree = new \Praxigento\Downline\Tool\Def\Tree();
        $mToolbox = $this->_mockToolbox(null, null, null, null, null, $mToolDownlineTree);
        $mCallDownlineSnap = $this->_mockFor('\Praxigento\Downline\Service\ISnap');

        /**
         * Prepare request and perform call.
         */
        /** @var  $sub Calc */
        $sub = new Calc($mLogger, $mToolbox, $mCallDownlineSnap);
        $data = $sub->_mapByGeneration($DATA, $TREE);
        $this->assertTrue(is_array($data));
    }

    public function test__mapByPv()
    {
        /** === Test Data === */
        $DATA = [
            ['CustId' => 1, 'PV' => 10],
            ['CustId' => 1, 'PV' => 20]
        ];
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mToolbox = $this->_mockToolbox();
        $mCallDownlineSnap = $this->_mockFor('\Praxigento\Downline\Service\ISnap');

        /**
         * Prepare request and perform call.
         */
        /** @var  $sub Calc */
        $sub = new Calc($mLogger, $mToolbox, $mCallDownlineSnap);
        $data = $sub->_mapByPv($DATA, 'CustId', 'PV');
        $this->assertTrue(is_array($data));
    }

    public function test_bonusCourtesy()
    {
        /** === Test Data === */
        $COMPRESSED = [
            [
                PtcCompress::ATTR_CUSTOMER_ID => 1,
                Customer::ATTR_HUMAN_REF => 'ref01',
                Customer::ATTR_COUNTRY_CODE => 'LV',
                PtcCompress::ATTR_PARENT_ID => 1,
                PtcCompress::ATTR_PV => 25,
                PtcCompress::ATTR_TV => 205
            ],
            [
                PtcCompress::ATTR_CUSTOMER_ID => 2,
                Customer::ATTR_HUMAN_REF => 'ref02',
                Customer::ATTR_COUNTRY_CODE => 'LV',
                PtcCompress::ATTR_PARENT_ID => 1,
                PtcCompress::ATTR_PV => 60,
                PtcCompress::ATTR_TV => 0
            ],
            [
                PtcCompress::ATTR_CUSTOMER_ID => 3,
                Customer::ATTR_HUMAN_REF => 'ref03',
                Customer::ATTR_COUNTRY_CODE => 'LV',
                PtcCompress::ATTR_PARENT_ID => 1,
                PtcCompress::ATTR_PV => 120,
                PtcCompress::ATTR_TV => 0
            ]
        ];
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mToolFormat = new ToolFormat();
        $mToolScheme = $this->_mockFor('\Praxigento\BonusHybrid\Tool\IScheme');
        $mToolbox = $this->_mockToolbox(null, null, $mToolFormat, null, $mToolScheme);
        $mCallDownlineSnap = $this->_mockFor('\Praxigento\Downline\Service\ISnap');

        // $custScheme = $this->_toolScheme->getSchemeByCustomer($item);
        $mToolScheme
            ->expects($this->any())
            ->method('getSchemeByCustomer')
            ->willReturn(Def::SCHEMA_DEFAULT);
        // $tv = $this->_toolScheme->getForcedTv($custId, $custScheme, $tv);
        $mToolScheme
            ->expects($this->any())
            ->method('getForcedTv')
            ->willReturnArgument(2);
        /**
         * Prepare request and perform call.
         */
        /** @var  $sub Calc */
        $sub = new Calc($mLogger, $mToolbox, $mCallDownlineSnap);
        $res = $sub->bonusCourtesy(
            $COMPRESSED,
            $this->COURTESY_PERCENT,
            $this->LEVELS_PERS,
            $this->LEVELS_TEAM
        );
        $this->assertTrue(is_array($res));
    }

    public function test_bonusInfinity()
    {
        /** === Test Data === */
        $COMPRESSED_OI = [
            [
                OiCompress::ATTR_CUSTOMER_ID => 1,
                OiCompress::ATTR_PARENT_ID => 1,
                OiCompress::ATTR_RANK_ID => 1,
                OiCompress::ATTR_PV => 100,
                Customer::ATTR_COUNTRY_CODE => 'LV'
            ],
            [
                OiCompress::ATTR_CUSTOMER_ID => 2,
                OiCompress::ATTR_PARENT_ID => 1,
                OiCompress::ATTR_RANK_ID => 2,
                OiCompress::ATTR_PV => 100,
                Customer::ATTR_COUNTRY_CODE => 'LV'
            ],
            [
                OiCompress::ATTR_CUSTOMER_ID => 3,
                OiCompress::ATTR_PARENT_ID => 2,
                OiCompress::ATTR_RANK_ID => 1,
                OiCompress::ATTR_PV => 100,
                Customer::ATTR_COUNTRY_CODE => 'LV'
            ],
            [
                OiCompress::ATTR_CUSTOMER_ID => 4,
                OiCompress::ATTR_PARENT_ID => 3,
                OiCompress::ATTR_RANK_ID => 2,
                OiCompress::ATTR_PV => 100,
                Customer::ATTR_COUNTRY_CODE => 'LV'
            ],
            [
                OiCompress::ATTR_CUSTOMER_ID => 5,
                OiCompress::ATTR_PARENT_ID => 4,
                OiCompress::ATTR_RANK_ID => 2,
                OiCompress::ATTR_PV => 100,
                Customer::ATTR_COUNTRY_CODE => 'LV'
            ]
        ];
        $MAP_TREE_EXP = [
            1 => [
                OiCompress::ATTR_CUSTOMER_ID => 1,
                OiCompress::ATTR_RANK_ID => 1,
                Snap::ATTR_PATH => '/'
            ],
            2 => [
                OiCompress::ATTR_CUSTOMER_ID => 2,
                OiCompress::ATTR_RANK_ID => 2,
                Snap::ATTR_PATH => '/1/'
            ],
            3 => [
                OiCompress::ATTR_CUSTOMER_ID => 3,
                OiCompress::ATTR_RANK_ID => 1,
                Snap::ATTR_PATH => '/1/2/'
            ],
            4 => [
                OiCompress::ATTR_CUSTOMER_ID => 4,
                OiCompress::ATTR_RANK_ID => 2,
                Snap::ATTR_PATH => '/1/2/3/'
            ],
            5 => [
                OiCompress::ATTR_CUSTOMER_ID => 5,
                OiCompress::ATTR_RANK_ID => 2,
                Snap::ATTR_PATH => '/1/2/3/4/'
            ]
        ];
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mToolFormat = new \Praxigento\Core\Tool\IFormat();
        $mToolScheme = $this->_mockFor('\Praxigento\BonusHybrid\Tool\IScheme');
        $mToolDownlineTree = new \Praxigento\Downline\Tool\Def\Tree();
        $mToolbox = $this->_mockToolbox(null, null, $mToolFormat, null, $mToolScheme, $mToolDownlineTree);
        $mCallDownlineSnap = $this->_mockFor('\Praxigento\Downline\Service\ISnap');

        // $mapTreeExp = $this->_getExpandedTreeSnap(
        // $respExt = $this->_callDownlineSnap->expandMinimal($reqExt);
        $mRespExt = new DataObject();
        $mCallDownlineSnap
            ->expects($this->once())
            ->method('expandMinimal')
            ->willReturn($mRespExt);
        // $result = $respExt->getSnapData();
        $mRespExt->setSnapData($MAP_TREE_EXP);

        // $scheme = $this->_toolScheme->getSchemeByCustomer($one);
        $mToolScheme
            ->expects($this->any())
            ->method('getSchemeByCustomer')
            ->willReturn(Def::SCHEMA_DEFAULT);
        /**
         * Prepare request and perform call.
         */
        /** @var  $sub Calc */
        $sub = new Calc($mLogger, $mToolbox, $mCallDownlineSnap);
        $res = $sub->bonusInfinity($COMPRESSED_OI, Def::SCHEMA_DEFAULT, $this->CFG_PARAMS);
        $this->assertTrue(is_array($res));
    }

    public function test_bonusOverride()
    {
        /** === Test Data === */
        $COMPRESSED_OI = [
            [
                OiCompress::ATTR_CUSTOMER_ID => 1,
                Customer::ATTR_HUMAN_REF => 'ref01',
                OiCompress::ATTR_PARENT_ID => 1,
                OiCompress::ATTR_RANK_ID => 1,
                OiCompress::ATTR_PV => 100,
                Customer::ATTR_COUNTRY_CODE => 'LV'
            ],
            [ // incomplete CFG_OVERRIDE
                OiCompress::ATTR_CUSTOMER_ID => 2,
                Customer::ATTR_HUMAN_REF => 'ref02',
                OiCompress::ATTR_PARENT_ID => 1,
                OiCompress::ATTR_RANK_ID => 2,
                OiCompress::ATTR_PV => 100,
                Customer::ATTR_COUNTRY_CODE => 'LV'
            ]
        ];
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mToolFormat = new \Praxigento\Core\Tool\IFormat();
        $mToolScheme = $this->_mockFor('\Praxigento\BonusHybrid\Tool\IScheme');
        $mToolDownlineTree = new \Praxigento\Downline\Tool\Def\Tree();
        $mToolbox = $this->_mockToolbox(null, null, $mToolFormat, null, $mToolScheme, $mToolDownlineTree);
        $mCallDownlineSnap = $this->_mockFor('\Praxigento\Downline\Service\ISnap');

        // $mapTreeExp = $this->_getExpandedTreeSnap(
        // $respExt = $this->_callDownlineSnap->expandMinimal($reqExt);
        $mRespExt = new DataObject();
        $mCallDownlineSnap
            ->expects($this->once())
            ->method('expandMinimal')
            ->willReturn($mRespExt);
        // $result = $respExt->getSnapData();
        $mRespExt->setSnapData([]);

        // $scheme = $this->_toolScheme->getSchemeByCustomer($one);
        $mToolScheme
            ->expects($this->any())
            ->method('getSchemeByCustomer')
            ->willReturn(Def::SCHEMA_DEFAULT);
        /**
         * Prepare request and perform call.
         */
        /** @var  $sub Calc */
        $sub = new Calc($mLogger, $mToolbox, $mCallDownlineSnap);
        $res = $sub->bonusOverride($COMPRESSED_OI, Def::SCHEMA_DEFAULT, $this->CFG_OVERRIDE);
        $this->assertTrue(is_array($res));
    }

    public function test_bonusPersonalDef()
    {
        /** === Test Data === */
        $COMPRESSED_PTC = [
            [
                PtcCompress::ATTR_CUSTOMER_ID => 2,
                PtcCompress::ATTR_PV => 100,
                Customer::ATTR_COUNTRY_CODE => 'LV'
            ],
            [
                PtcCompress::ATTR_CUSTOMER_ID => 3,
                PtcCompress::ATTR_PV => 20,
                Customer::ATTR_COUNTRY_CODE => 'LV'
            ]
        ];
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mToolFormat = new \Praxigento\Core\Tool\IFormat();
        $mToolScheme = $this->_mockFor('\Praxigento\BonusHybrid\Tool\IScheme');
        $mToolbox = $this->_mockToolbox(null, null, $mToolFormat, null, $mToolScheme);
        $mCallDownlineSnap = $this->_mockFor('\Praxigento\Downline\Service\ISnap');

        // $scheme = $this->_toolScheme->getSchemeByCustomer($one);
        $mToolScheme
            ->expects($this->any())
            ->method('getSchemeByCustomer')
            ->willReturn(Def::SCHEMA_DEFAULT);
        /**
         * Prepare request and perform call.
         */
        /** @var  $sub Calc */
        $sub = new Calc($mLogger, $mToolbox, $mCallDownlineSnap);
        $res = $sub->bonusPersonalDef($COMPRESSED_PTC, $this->LEVELS_PERS);
        $this->assertTrue(is_array($res));
    }

    public function test_bonusPersonalEu()
    {
        /** === Test Data === */
        $TREE = [
            [Snap::ATTR_CUSTOMER_ID => 3, Snap::ATTR_PARENT_ID => 2, Snap::ATTR_PATH => '/2/']
        ];
        $COMPRESSED_PTC = [
            [
                PtcCompress::ATTR_CUSTOMER_ID => 2,
                PtcCompress::ATTR_PV => 100,
                Customer::ATTR_COUNTRY_CODE => 'LV'
            ]
        ];
        $ORDERS = [
            2 => [31 => 100],
            3 => [32 => 200]
        ];
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mToolFormat = new \Praxigento\Core\Tool\IFormat();
        $mToolScheme = $this->_mockFor('\Praxigento\BonusHybrid\Tool\IScheme');
        $mToolDownlineTree = new \Praxigento\Downline\Tool\Def\Tree();
        $mToolbox = $this->_mockToolbox(null, null, $mToolFormat, null, $mToolScheme, $mToolDownlineTree);
        $mCallDownlineSnap = $this->_mockFor('\Praxigento\Downline\Service\ISnap');

        // $scheme = $this->_toolScheme->getSchemeByCustomer($one);
        $mToolScheme
            ->expects($this->any())
            ->method('getSchemeByCustomer')
            ->willReturn(Def::SCHEMA_EU);

        /**
         * Prepare request and perform call.
         */
        /** @var  $sub Calc */
        $sub = new Calc($mLogger, $mToolbox, $mCallDownlineSnap);
        $res = $sub->bonusPersonalEu($TREE, $COMPRESSED_PTC, $ORDERS);
        $this->assertTrue(is_array($res));
    }

    public function test_bonusTeamDef()
    {
        /** === Test Data === */
        $COMPRESSED = [
            [ // forced PV
                PtcCompress::ATTR_CUSTOMER_ID => 1,
                Customer::ATTR_HUMAN_REF => 'ref_1',
                Customer::ATTR_COUNTRY_CODE => 'LV',
                PtcCompress::ATTR_PARENT_ID => 1,
                PtcCompress::ATTR_PATH => '/',
                PtcCompress::ATTR_PV => 0,
                PtcCompress::ATTR_TV => 0
            ],
            [ // parent with %TV not more then his child
                PtcCompress::ATTR_CUSTOMER_ID => 22,
                Customer::ATTR_HUMAN_REF => 'ref_22',
                Customer::ATTR_COUNTRY_CODE => 'LV',
                PtcCompress::ATTR_PARENT_ID => 1,
                PtcCompress::ATTR_PATH => '/1/',
                PtcCompress::ATTR_PV => 75,
                PtcCompress::ATTR_TV => 800
            ],
            [ // child for parent with %TV not more then his child
                PtcCompress::ATTR_CUSTOMER_ID => 222,
                Customer::ATTR_HUMAN_REF => 'ref_222',
                Customer::ATTR_COUNTRY_CODE => 'LV',
                PtcCompress::ATTR_PARENT_ID => 22,
                PtcCompress::ATTR_PATH => '/1/22/',
                PtcCompress::ATTR_PV => 75,
                PtcCompress::ATTR_TV => 0
            ],
            [ // EU customer
                PtcCompress::ATTR_CUSTOMER_ID => 21,
                Customer::ATTR_HUMAN_REF => 'ref_21',
                Customer::ATTR_COUNTRY_CODE => 'DE',
                PtcCompress::ATTR_PARENT_ID => 1,
                PtcCompress::ATTR_PATH => '/1/',
                PtcCompress::ATTR_PV => 0,
                PtcCompress::ATTR_TV => 800
            ],
            [ // EU customer with %PB less then courtesy
                PtcCompress::ATTR_CUSTOMER_ID => 2,
                Customer::ATTR_HUMAN_REF => 'ref_2',
                Customer::ATTR_COUNTRY_CODE => 'DE',
                PtcCompress::ATTR_PARENT_ID => 21,
                PtcCompress::ATTR_PATH => '/1/21/',
                PtcCompress::ATTR_PV => 60,
                PtcCompress::ATTR_TV => 800
            ],
            [ // forced TV, EU customer with %PB more then courtesy
                PtcCompress::ATTR_CUSTOMER_ID => 3,
                Customer::ATTR_HUMAN_REF => 'ref_3',
                Customer::ATTR_COUNTRY_CODE => 'DE',
                PtcCompress::ATTR_PARENT_ID => 1,
                PtcCompress::ATTR_PATH => '/1/21/2/',
                PtcCompress::ATTR_PV => 120,
                PtcCompress::ATTR_TV => 0
            ],
            [ // leaf node
                PtcCompress::ATTR_CUSTOMER_ID => 4,
                Customer::ATTR_HUMAN_REF => 'ref_4',
                Customer::ATTR_COUNTRY_CODE => 'LV',
                PtcCompress::ATTR_PARENT_ID => 1,
                PtcCompress::ATTR_PATH => '/1/21/2/3/',
                PtcCompress::ATTR_PV => 50,
                PtcCompress::ATTR_TV => 0
            ],
            [ // has max %PB, no TB
                PtcCompress::ATTR_CUSTOMER_ID => 5,
                Customer::ATTR_HUMAN_REF => 'ref_5',
                Customer::ATTR_COUNTRY_CODE => 'LV',
                PtcCompress::ATTR_PARENT_ID => 1,
                PtcCompress::ATTR_PATH => '/1/21/2/3/',
                PtcCompress::ATTR_PV => 1000,
                PtcCompress::ATTR_TV => 0
            ],
            [
                PtcCompress::ATTR_CUSTOMER_ID => 6,
                Customer::ATTR_HUMAN_REF => 'ref_6',
                Customer::ATTR_COUNTRY_CODE => 'LV',
                PtcCompress::ATTR_PARENT_ID => 1,
                PtcCompress::ATTR_PATH => '/1/',
                PtcCompress::ATTR_PV => 0,
                PtcCompress::ATTR_TV => 0
            ]
        ];
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mToolFormat = new ToolFormat();
        $mToolScheme = $this->_mockFor('\Praxigento\BonusHybrid\Tool\IScheme');
        $mToolDownlineTree = new ToolDownlineTree();
        $mToolbox = $this->_mockToolbox(null, null, $mToolFormat, null, $mToolScheme, $mToolDownlineTree);
        $mCallDownlineSnap = $this->_mockFor('\Praxigento\Downline\Service\ISnap');

        // $custScheme = $this->_toolScheme->getSchemeByCustomer($item);
        $mToolScheme
            ->expects($this->any())
            ->method('getSchemeByCustomer')
            ->willReturnCallback(function () {
                $args = func_get_args();
                $item = $args[0];
                $result = Def::SCHEMA_DEFAULT;
                if (
                    isset($item[Customer::ATTR_COUNTRY_CODE]) &&
                    ($item[Customer::ATTR_COUNTRY_CODE] != 'LV')
                ) {
                    $result = Def::SCHEMA_EU;
                }
                return $result;
            });
        // $pvForced = $this->_toolScheme->getForcedPv($custId, $pv);
        $mToolScheme
            ->expects($this->any())
            ->method('getForcedPv')
            ->willReturnCallback(function () {
                $args = func_get_args();
                $result = $args[0] == 1 ? 200 : $args[1];
                return $result;
            });
        // $tvForced = $this->_toolScheme->getForcedTv($parentId, $scheme, $tv);
        $mToolScheme
            ->expects($this->any())
            ->method('getForcedTv')
            ->willReturnCallback(function () {
                $args = func_get_args();
                $result = $args[0] == 3 ? 1000 : $args[2];
                return $result;
            });

        /**
         * Prepare request and perform call.
         */
        /** @var  $sub Calc */
        $sub = new Calc($mLogger, $mToolbox, $mCallDownlineSnap);
        $res = $sub->bonusTeamDef(
            $COMPRESSED,
            $this->LEVELS_PERS,
            $this->LEVELS_TEAM,
            $this->COURTESY_PERCENT
        );
        $this->assertTrue(is_array($res));
    }

    public function test_bonusTeamEu()
    {
        /** === Test Data === */
        $COMPRESSED_PTC = [
            [
                PtcCompress::ATTR_CUSTOMER_ID => 2,
                PtcCompress::ATTR_PARENT_ID => 2,
                PtcCompress::ATTR_PV => 100,
                Customer::ATTR_HUMAN_REF => 'ref_2',
                Customer::ATTR_COUNTRY_CODE => 'LV'
            ],
            [ //DEFAULT scheme
                PtcCompress::ATTR_CUSTOMER_ID => 3,
                PtcCompress::ATTR_PARENT_ID => 3,
                PtcCompress::ATTR_PV => 100,
                Customer::ATTR_HUMAN_REF => 'ref_3',
                Customer::ATTR_COUNTRY_CODE => 'DE'
            ],
            [ // with 0 PV
                PtcCompress::ATTR_CUSTOMER_ID => 4,
                PtcCompress::ATTR_PARENT_ID => 4,
                PtcCompress::ATTR_PV => 0,
                Customer::ATTR_HUMAN_REF => 'ref_4',
                Customer::ATTR_COUNTRY_CODE => 'LV'
            ]
        ];
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mToolFormat = new \Praxigento\Core\Tool\IFormat();
        $mToolScheme = $this->_mockFor('\Praxigento\BonusHybrid\Tool\IScheme');
        $mToolbox = $this->_mockToolbox(null, null, $mToolFormat, null, $mToolScheme);
        $mCallDownlineSnap = $this->_mockFor('\Praxigento\Downline\Service\ISnap');

        // $scheme = $this->_toolScheme->getSchemeByCustomer($one);
        $mToolScheme
            ->expects($this->at(0))
            ->method('getSchemeByCustomer')
            ->willReturn(Def::SCHEMA_EU);
        $mToolScheme
            ->expects($this->at(1))
            ->method('getSchemeByCustomer')
            ->willReturn(Def::SCHEMA_DEFAULT);
        $mToolScheme
            ->expects($this->at(2))
            ->method('getSchemeByCustomer')
            ->willReturn(Def::SCHEMA_EU);

        /**
         * Prepare request and perform call.
         */
        /** @var  $sub Calc */
        $sub = new Calc($mLogger, $mToolbox, $mCallDownlineSnap);
        $res = $sub->bonusTeamEu($COMPRESSED_PTC, $this->COURTESY_PERCENT);
        $this->assertTrue(is_array($res));
    }

    public function test_compressOi()
    {
        /** === Test Data === */
        $COMPRESSED_PTC = [
            [ // forced qualification
                PtcCompress::ATTR_CUSTOMER_ID => 1,
                PtcCompress::ATTR_PARENT_ID => 1,
                PtcCompress::ATTR_PATH => '/',
                PtcCompress::ATTR_DEPTH => 0,
                PtcCompress::ATTR_PV => 100,
                PtcCompress::ATTR_TV => 0,
                PtcCompress::ATTR_OV => 100000,
                Customer::ATTR_COUNTRY_CODE => 'LV'
            ],
            [ // qualified parent
                PtcCompress::ATTR_CUSTOMER_ID => 2,
                PtcCompress::ATTR_PARENT_ID => 1,
                PtcCompress::ATTR_PATH => '/1/',
                PtcCompress::ATTR_DEPTH => 1,
                PtcCompress::ATTR_PV => 100,
                PtcCompress::ATTR_TV => 100,
                PtcCompress::ATTR_OV => 100000,
                Customer::ATTR_COUNTRY_CODE => 'LV'
            ],
            [ // unqualified parent
                PtcCompress::ATTR_CUSTOMER_ID => 3,
                PtcCompress::ATTR_PARENT_ID => 1,
                PtcCompress::ATTR_PATH => '/1/',
                PtcCompress::ATTR_DEPTH => 1,
                PtcCompress::ATTR_PV => 0,
                PtcCompress::ATTR_TV => 0,
                PtcCompress::ATTR_OV => 100000,
                Customer::ATTR_COUNTRY_CODE => 'LV'
            ],
            [ // qualified child 1 for qualified parent
                PtcCompress::ATTR_CUSTOMER_ID => 21,
                PtcCompress::ATTR_PARENT_ID => 2,
                PtcCompress::ATTR_PATH => '/1/2/',
                PtcCompress::ATTR_DEPTH => 2,
                PtcCompress::ATTR_PV => 100,
                PtcCompress::ATTR_TV => 200,
                PtcCompress::ATTR_OV => 100000,
                Customer::ATTR_COUNTRY_CODE => 'LV'
            ],
            [ // qualified child 2 for qualified parent
                PtcCompress::ATTR_CUSTOMER_ID => 22,
                PtcCompress::ATTR_PARENT_ID => 2,
                PtcCompress::ATTR_PATH => '/1/2/',
                PtcCompress::ATTR_DEPTH => 2,
                PtcCompress::ATTR_PV => 100,
                PtcCompress::ATTR_TV => 300,
                PtcCompress::ATTR_OV => 100000,
                Customer::ATTR_COUNTRY_CODE => 'LV'
            ],
            [ // qualified child 3 for qualified parent
                PtcCompress::ATTR_CUSTOMER_ID => 23,
                PtcCompress::ATTR_PARENT_ID => 2,
                PtcCompress::ATTR_PATH => '/1/2/',
                PtcCompress::ATTR_DEPTH => 2,
                PtcCompress::ATTR_PV => 100,
                PtcCompress::ATTR_TV => 300,
                PtcCompress::ATTR_OV => 100000,
                Customer::ATTR_COUNTRY_CODE => 'LV'
            ],
            [ // qualified child 4 for qualified parent
                PtcCompress::ATTR_CUSTOMER_ID => 21,
                PtcCompress::ATTR_PARENT_ID => 2,
                PtcCompress::ATTR_PATH => '/1/2/',
                PtcCompress::ATTR_DEPTH => 2,
                PtcCompress::ATTR_PV => 100,
                PtcCompress::ATTR_TV => 300,
                PtcCompress::ATTR_OV => 100000,
                Customer::ATTR_COUNTRY_CODE => 'LV'
            ],
            [ // qualified child for unqualified parent
                PtcCompress::ATTR_CUSTOMER_ID => 31,
                PtcCompress::ATTR_PARENT_ID => 3,
                PtcCompress::ATTR_PATH => '/1/3/',
                PtcCompress::ATTR_DEPTH => 2,
                PtcCompress::ATTR_PV => 100,
                PtcCompress::ATTR_TV => 100,
                PtcCompress::ATTR_OV => 100000,
                Customer::ATTR_COUNTRY_CODE => 'LV'
            ],
            [ // unqualified root
                PtcCompress::ATTR_CUSTOMER_ID => 4,
                PtcCompress::ATTR_PARENT_ID => 4,
                PtcCompress::ATTR_PATH => '/',
                PtcCompress::ATTR_DEPTH => 0,
                PtcCompress::ATTR_PV => 0,
                PtcCompress::ATTR_TV => 0,
                PtcCompress::ATTR_OV => 100000,
                Customer::ATTR_COUNTRY_CODE => 'LV'
            ],
            [ // qualified child for unqualified root
                PtcCompress::ATTR_CUSTOMER_ID => 41,
                PtcCompress::ATTR_PARENT_ID => 4,
                PtcCompress::ATTR_PATH => '/4/',
                PtcCompress::ATTR_DEPTH => 1,
                PtcCompress::ATTR_PV => 1000,
                PtcCompress::ATTR_TV => 1000,
                PtcCompress::ATTR_OV => 100000,
                Customer::ATTR_COUNTRY_CODE => 'LV'
            ]
        ];
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mToolFormat = new \Praxigento\Core\Tool\IFormat();
        $mToolScheme = $this->_mockFor('\Praxigento\BonusHybrid\Tool\IScheme');
        $mToolDownlineTree = new ToolDownlineTree();
        $mToolbox = $this->_mockToolbox(null, null, $mToolFormat, null, $mToolScheme, $mToolDownlineTree);
        $mCallDownlineSnap = $this->_mockFor('\Praxigento\Downline\Service\ISnap');

        // $rankId = $this->_toolScheme->getForcedQualificationRank($custId, $scheme);
        $mToolScheme
            ->expects($this->any())
            ->method('getForcedQualificationRank')
            ->willReturnCallback(function () {
                $args = func_get_args();
                $result = null;
                if ($args[0] == 1) {
                    $result = 1;
                }
                return $result;
            });

        // $scheme = $this->_toolScheme->getSchemeByCustomer($one);
        //        $mToolScheme
        //            ->expects($this->any())
        //            ->method('getSchemeByCustomer')
        //            ->willReturn(Def::SCHEMA_EU);


        /**
         * Prepare request and perform call.
         */
        /** @var  $sub Calc */
        $sub = new Calc($mLogger, $mToolbox, $mCallDownlineSnap);
        $res = $sub->compressOi($COMPRESSED_PTC, $this->CFG_PARAMS, Def::SCHEMA_DEFAULT);
        $this->assertTrue(is_array($res));
    }

    public function test_compressPtc()
    {
        /** === Test Data === */
        $CUSTS = [
            [Customer::ATTR_CUSTOMER_ID => 1],
            [Customer::ATTR_CUSTOMER_ID => 2],
            [Customer::ATTR_CUSTOMER_ID => 3],
            [Customer::ATTR_CUSTOMER_ID => 4],
            [Customer::ATTR_CUSTOMER_ID => 5],
            [Customer::ATTR_CUSTOMER_ID => 6],
            [Customer::ATTR_CUSTOMER_ID => 7]
        ];
        $TREE = [
            1 => [
                Snap::ATTR_CUSTOMER_ID => 1,
                Snap::ATTR_PARENT_ID => 1,
                Snap::ATTR_DEPTH => 0,
                Snap::ATTR_PATH => '/'
            ],
            2 => [
                Snap::ATTR_CUSTOMER_ID => 2,
                Snap::ATTR_PARENT_ID => 1,
                Snap::ATTR_DEPTH => 1,
                Snap::ATTR_PATH => '/1/'
            ],
            3 => [
                Snap::ATTR_CUSTOMER_ID => 3,
                Snap::ATTR_PARENT_ID => 2,
                Snap::ATTR_DEPTH => 2,
                Snap::ATTR_PATH => '/1/2/'
            ],
            4 => [
                Snap::ATTR_CUSTOMER_ID => 4,
                Snap::ATTR_PARENT_ID => 3,
                Snap::ATTR_DEPTH => 3,
                Snap::ATTR_PATH => '/1/2/3/'
            ],
            5 => [
                Snap::ATTR_CUSTOMER_ID => 5,
                Snap::ATTR_PARENT_ID => 4,
                Snap::ATTR_DEPTH => 4,
                Snap::ATTR_PATH => '/1/2/3/4/'
            ],
            6 => [
                Snap::ATTR_CUSTOMER_ID => 6,
                Snap::ATTR_PARENT_ID => 5,
                Snap::ATTR_DEPTH => 5,
                Snap::ATTR_PATH => '/1/2/3/4/5/'
            ],
            7 => [
                Snap::ATTR_CUSTOMER_ID => 7,
                Snap::ATTR_PARENT_ID => 3,
                Snap::ATTR_DEPTH => 3,
                Snap::ATTR_PATH => '/1/2/3/'
            ],
        ];
        $TRANS = [
            [Account::ATTR_CUST_ID => 2, Transaction::ATTR_VALUE => 20],
            [Account::ATTR_CUST_ID => 3, Transaction::ATTR_VALUE => 20],
            [Account::ATTR_CUST_ID => 4, Transaction::ATTR_VALUE => 20],
            [Account::ATTR_CUST_ID => 5, Transaction::ATTR_VALUE => 25],
            [Account::ATTR_CUST_ID => 6, Transaction::ATTR_VALUE => 250],
            [Account::ATTR_CUST_ID => 7, Transaction::ATTR_VALUE => 20]
        ];
        $Q_LEVELS = [Def::SCHEMA_DEFAULT => 50, Def::SCHEMA_EU => 100];
        $FORCED_IDS = [1, 2];
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mToolScheme = $this->_mockFor('\Praxigento\BonusHybrid\Tool\IScheme');
        $mToolDownlineTree = new \Praxigento\Downline\Tool\Def\Tree();
        $mToolbox = $this->_mockToolbox(null, null, null, null, $mToolScheme, $mToolDownlineTree);
        $mCallDownlineSnap = $this->_mockFor('\Praxigento\Downline\Service\ISnap');

        // $qLevels = $this->_toolScheme->getQualificationLevels();
        $mToolScheme
            ->expects($this->once())
            ->method('getQualificationLevels')
            ->willReturn($Q_LEVELS);
        // $forcedIds = $this->_toolScheme->getForcedQualificationCustomersIds();
        $mToolScheme
            ->expects($this->once())
            ->method('getForcedQualificationCustomersIds')
            ->willReturn($FORCED_IDS);
        // $scheme = $this->_toolScheme->getSchemeByCustomer($custData);
        $mToolScheme
            ->expects($this->any())
            ->method('getSchemeByCustomer')
            ->willReturn(Def::SCHEMA_DEFAULT);
        // private function _composeSnapUpdates($calculatedData)
        // $resp = $this->_callDownlineSnap->expandMinimal($req);
        $mResp = new DownlineSnapExtendMinimalResponse();
        $mResp->setSnapData([]);
        $mCallDownlineSnap
            ->expects($this->once())
            ->method('expandMinimal')
            ->willReturn($mResp);
        /**
         * Prepare request and perform call.
         */
        /** @var  $sub Calc */
        $sub = new Calc($mLogger, $mToolbox, $mCallDownlineSnap);
        $data = $sub->compressPtc($TREE, $CUSTS, $TRANS);
        $this->assertTrue(is_array($data));
    }

    public function test_pvWriteOff()
    {
        /** === Test Data === */
        $TRANSACTIONS = [
            [
                Transaction::ATTR_DEBIT_ACC_ID => 2,
                Transaction::ATTR_CREDIT_ACC_ID => 4,
                Transaction::ATTR_VALUE => 10
            ],
            [
                Transaction::ATTR_DEBIT_ACC_ID => 2,
                Transaction::ATTR_CREDIT_ACC_ID => 8,
                Transaction::ATTR_VALUE => 20
            ],
            [
                Transaction::ATTR_DEBIT_ACC_ID => 4,
                Transaction::ATTR_CREDIT_ACC_ID => 8,
                Transaction::ATTR_VALUE => 30
            ]
        ];
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mToolbox = $this->_mockToolbox();
        $mCallDownlineSnap = $this->_mockFor('\Praxigento\Downline\Service\ISnap');

        /**
         * Prepare request and perform call.
         */
        /** @var  $sub Calc */
        $sub = new Calc($mLogger, $mToolbox, $mCallDownlineSnap);
        $res = $sub->pvWriteOff($TRANSACTIONS);
        $this->assertTrue(is_array($res));
    }

    public function test_valueOv()
    {
        /** === Test Data === */
        $COMPRESSION = [
            1 => [
                PtcCompress::ATTR_CUSTOMER_ID => 1,
                PtcCompress::ATTR_PARENT_ID => 1,
                PtcCompress::ATTR_PV => 325,
                PtcCompress::ATTR_DEPTH => 0
            ],
            2 => [
                PtcCompress::ATTR_CUSTOMER_ID => 2,
                PtcCompress::ATTR_PARENT_ID => 1,
                PtcCompress::ATTR_PV => 300,
                PtcCompress::ATTR_DEPTH => 1
            ],
            3 => [
                PtcCompress::ATTR_CUSTOMER_ID => 3,
                PtcCompress::ATTR_PARENT_ID => 1,
                PtcCompress::ATTR_PV => 700,
                PtcCompress::ATTR_DEPTH => 1
            ]
        ];
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mToolbox = $this->_mockToolbox();
        $mCallDownlineSnap = $this->_mockFor('\Praxigento\Downline\Service\ISnap');

        /**
         * Prepare request and perform call.
         */
        /** @var  $sub Calc */
        $sub = new Calc($mLogger, $mToolbox, $mCallDownlineSnap);
        $data = $sub->valueOv($COMPRESSION);
        $this->assertTrue(is_array($data));
    }

    public function test_valueTv()
    {
        /** === Test Data === */
        $COMPRESSION = [
            1 => [PtcCompress::ATTR_CUSTOMER_ID => 1, PtcCompress::ATTR_PARENT_ID => 1, PtcCompress::ATTR_PV => 325],
            2 => [PtcCompress::ATTR_CUSTOMER_ID => 2, PtcCompress::ATTR_PARENT_ID => 1, PtcCompress::ATTR_PV => 300],
            3 => [PtcCompress::ATTR_CUSTOMER_ID => 3, PtcCompress::ATTR_PARENT_ID => 1, PtcCompress::ATTR_PV => 700]
        ];
        /** === Mocks === */
        $mLogger = $this->_mockLogger();
        $mToolbox = $this->_mockToolbox();
        $mCallDownlineSnap = $this->_mockFor('\Praxigento\Downline\Service\ISnap');

        /**
         * Prepare request and perform call.
         */
        /** @var  $sub Calc */
        $sub = new Calc($mLogger, $mToolbox, $mCallDownlineSnap);
        $data = $sub->valueTv($COMPRESSION);
        $this->assertTrue(is_array($data));
    }
}