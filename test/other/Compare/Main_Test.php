<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Test\Compare;


use Praxigento\Accounting\Repo\Entity\Data\Account;
use Praxigento\Accounting\Repo\Entity\Data\Transaction;
use Praxigento\BonusBase\Repo\Entity\Data\Level;
use Praxigento\BonusBase\Repo\Entity\Data\Log\Opers as LogOpers;
use Praxigento\BonusBase\Repo\Entity\Data\Rank;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Defaults as Def;
use Praxigento\BonusHybrid\Repo\Data\Entity\Cfg\Override as CfgOverride;
use Praxigento\BonusHybrid\Repo\Data\Entity\Cfg\Param as CfgParam;
use Praxigento\BonusHybrid\Repo\Data\Entity\Retro\Downline\Compressed\Phase1 as PtcCompression;
use Praxigento\BonusHybrid\Service\Calc\Request\BonusCourtesy as BonusCalcBonusCourtesyRequest;
use Praxigento\BonusHybrid\Service\Calc\Request\BonusInfinity as BonusCalcBonusInfinityRequest;
use Praxigento\BonusHybrid\Service\Calc\Request\BonusOverride as BonusCalcBonusOverrideRequest;
use Praxigento\BonusHybrid\Service\Calc\Request\BonusPersonal as BonusCalcBonusPersonalRequest;
use Praxigento\BonusHybrid\Service\Calc\Request\BonusTeam as BonusCalcBonusTeamRequest;
use Praxigento\BonusHybrid\Service\Calc\Request\CompressOi as BonusCalcCompressOiRequest;
use Praxigento\BonusHybrid\Service\Calc\Request\CompressPtc as BonusCalcCompressPtcRequest;
use Praxigento\BonusHybrid\Service\Calc\Request\PvWriteOff as BonusCalcPvWriteOffRequest;
use Praxigento\BonusHybrid\Service\Calc\Request\ValueOv as BonusCalcValueOvRequest;
use Praxigento\BonusHybrid\Service\Calc\Request\ValueTv as BonusCalcValueTvRequest;
use Praxigento\Core\Test\BaseIntegrationTest;
use Praxigento\Downline\Repo\Entity\Data\Snap;
use Praxigento\Downline\Service\Customer\Request\Add as CustomerAddRequest;
use Praxigento\Downline\Service\Snap\Request\Calc as SnapCalcRequest;
use Praxigento\Downline\Service\Snap\Request\ExpandMinimal as SnapExtendMinimalRequest;
use Praxigento\Pv\Repo\Entity\Data\Sale as PvSale;
use Praxigento\Pv\Service\Transfer\Request\CreditToCustomer as PvTransferCreditToCustomerRequest;

include_once(__DIR__ . '/../phpunit_bootstrap.php');

class Main_OtherTest extends BaseIntegrationTest
{
    const A_COURTESY = 'Courtesy';
    const A_CUST_ID = 'CustId';
    const A_INFINITY = 'Infinity';
    const A_OV = 'ov';
    const A_OVERRIDE = 'Override';
    const A_PARENT_ID = 'ParentId';
    const A_PERSONAL = 'Personal';
    const A_PV = 'pv';
    const A_SCHEME = 'Schema';
    const A_TEAM = 'team';
    const A_TV = 'tv';
    const DEF_COURTESY_BONUS_PERCENT = 0.05;
    const DEF_TEAM_BONUS_EU_PERCENT = 0.05;
    const DS_CUSTOMER_ADDED = '20160122';
    const EU_DOWNLINE_COUNTRY_CODE = 'ES';
    const SCHEMA_DEFAULT = 'DEFAULT';
    private $_calcIdBonusCourtesy = null;
    private $_calcIdBonusInfinityDef = null;
    private $_calcIdBonusInfinityEu = null;
    private $_calcIdBonusOverrideDef = null;
    private $_calcIdBonusOverrideEu = null;
    private $_calcIdBonusPersonalDef = null;
    private $_calcIdBonusPersonalEu = null;
    private $_calcIdBonusTeamDef = null;
    private $_calcIdBonusTeamEu = null;
    private $_calcIdCompressOiDef = null;
    private $_calcIdCompressOiEu = null;
    private $_calcIdCompressPtc = null;
    /** @var \Praxigento\Accounting\Service\IAccount */
    private $_callAccount;
    /** @var \Praxigento\BonusHybrid\Service\ICalc */
    private $_callCalc;
    /** @var \Praxigento\Accounting\Service\IOperation */
    private $_callOperation;
    /** @var \Praxigento\BonusHybrid\Service\IPeriod */
    private $_callPeriod;
    /** @var  \Praxigento\Pv\Service\ITransfer */
    private $_callPvTransfer;
    /**
     * @var array [$mlmId=>['personal'=>99.99, ...], ...]
     */
    private $_mapCsvBalances = [];
    private $_mapCsvDownline = [];
    /**
     * @var array [$orderId => [$mlmId, $amount], ...]
     */
    private $_mapCsvOrders = [];
    /**
     * Map of the target structure of the PTC Compressed downline tree (parent, PV, TV).
     * @var array [$mlmId => [$parentId, $pv, $tv], ... ]
     */
    private $_mapCsvTreePtc = [];
    /**
     * Map index by MLM ID (index started from 1).
     *
     * @var array [ $mlmId  => $index, ... ]
     */
    private $_mapCustomerIndexByMlmId = [];
    /**
     * Map MLM ID by index (index started from 1).
     *
     * @var array [ $index => $mlmId, ... ]
     */
    private $_mapCustomerMlmIdByIndex = [];
    /** @var \Praxigento\Core\Repo\IGeneric */
    private $_repoBasic;
    /** @var \Praxigento\BonusBase\Repo\Entity\Rank */
    private $_repoRank;
    /** @var  \Praxigento\Accounting\Repo\Entity\Type\Asset */
    private $_repoTypeAsset;
    /** @var  \Praxigento\BonusBase\Repo\Entity\Type\Calc */
    private $_repoTypeCalc;
    /** @var  \Praxigento\Core\Tool\IDate */
    private $_toolDate;
    /** @var  \Praxigento\BonusHybrid\Tool\IScheme */
    private $_toolScheme;

    public function __construct()
    {
        parent::__construct();
        $this->_callAccount = $this->_manObj->get(\Praxigento\Accounting\Service\IAccount::class);
        $this->_callOperation = $this->_manObj->get(\Praxigento\Accounting\Service\IOperation::class);
        $this->_callCalc = $this->_manObj->get(\Praxigento\BonusHybrid\Service\ICalc::class);
        $this->_callDownlineSnap = $this->_manObj->get(\Praxigento\Downline\Service\ISnap::class);
        $this->_callPeriod = $this->_manObj->get(\Praxigento\BonusHybrid\Service\IPeriod::class);
        $this->_callPvTransfer = $this->_manObj->get(\Praxigento\Pv\Service\ITransfer::class);
        $this->_repoBasic = $this->_manObj->get(\Praxigento\Core\Repo\IGeneric::class);
        $this->_repoTypeAsset = $this->_manObj->get(\Praxigento\Accounting\Repo\Entity\Type\Asset::class);
        $this->_repoTypeCalc = $this->_manObj->get(\Praxigento\BonusBase\Repo\Entity\Type\Calc::class);
        $this->_repoRank = $this->_manObj->get(\Praxigento\BonusBase\Repo\Entity\Rank::class);
        $this->_toolScheme = $this->_manObj->get(\Praxigento\BonusHybrid\Tool\IScheme::class);
        $this->_toolDate = $this->_manObj->get(\Praxigento\Core\Tool\IDate::class);
    }

    /**
     * Add one row to configuration for Override Bonus.
     *
     * @param $rankCode
     * @param $schemeCode
     * @param $gen
     * @param $percent
     */
    private function _addConfigOverride($rankCode, $schemeCode, $gen, $percent)
    {
        $rankId = $this->_repoRank->getIdByCode($rankCode);
        $bind = [
            CfgOverride::ATTR_RANK_ID => $rankId,
            CfgOverride::ATTR_SCHEME => $schemeCode,
            CfgOverride::ATTR_GENERATION => $gen,
            CfgOverride::ATTR_PERCENT => $percent
        ];
        $this->_repoBasic->addEntity(CfgOverride::ENTITY_NAME, $bind);
    }

    /**
     * Add one row to configuration parameters.
     *
     * @param $rankCode
     * @param $schemeCode
     * @param $qPv
     * @param $qTv
     * @param $legMin
     * @param $legMedium
     * @param $legMax
     * @param $infinity
     */
    private function _addConfigParameter($rankCode, $schemeCode, $qPv, $qTv, $legMin, $legMedium, $legMax, $infinity)
    {
        $rankId = $this->_repoRank->getIdByCode($rankCode);
        $bind = [
            CfgParam::ATTR_RANK_ID => $rankId,
            CfgParam::ATTR_SCHEME => $schemeCode,
            CfgParam::ATTR_QUALIFY_PV => $qPv,
            CfgParam::ATTR_QUALIFY_TV => $qTv,
            CfgParam::ATTR_LEG_MIN => $legMin,
            CfgParam::ATTR_LEG_MEDIUM => $legMedium,
            CfgParam::ATTR_LEG_MAX => $legMax,
            CfgParam::ATTR_INFINITY => $infinity,
        ];
        $this->_repoBasic->addEntity(CfgParam::ENTITY_NAME, $bind);
    }

    private function _calcBonusCourtesy()
    {
        $request = new BonusCalcBonusCourtesyRequest();
        $request->setCourtesyBonusPercent(self::DEF_COURTESY_BONUS_PERCENT);
        $response = $this->_callCalc->bonusCourtesy($request);
        $this->_calcIdBonusCourtesy = $response->getCalcId();
        $this->assertTrue($response->isSucceed());
    }

    private function _calcBonusInfinity()
    {
        $request = new BonusCalcBonusInfinityRequest();
        /* DEFAULT scheme */
        $request->setScheme(Def::SCHEMA_DEFAULT);
        $response = $this->_callCalc->bonusInfinity($request);
        $this->_calcIdBonusInfinityDef = $response->getCalcId();
        $this->assertTrue($response->isSucceed());
        /* EU scheme */
        $request->setScheme(Def::SCHEMA_EU);
        $response = $this->_callCalc->bonusInfinity($request);
        $this->_calcIdBonusInfinityEu = $response->getCalcId();
        $this->assertTrue($response->isSucceed());
    }

    private function _calcBonusOverride()
    {
        $request = new BonusCalcBonusOverrideRequest();
        /* DEFAULT scheme */
        $request->setScheme(Def::SCHEMA_DEFAULT);
        $response = $this->_callCalc->bonusOverride($request);
        $this->_calcIdBonusOverrideDef = $response->getCalcId();
        $this->assertTrue($response->isSucceed());
        /* EU scheme */
        $request->setScheme(Def::SCHEMA_EU);
        $response = $this->_callCalc->bonusOverride($request);
        $this->_calcIdBonusOverrideEu = $response->getCalcId();
        $this->assertTrue($response->isSucceed());
    }

    private function _calcBonusPersonal()
    {
        $request = new BonusCalcBonusPersonalRequest();
        /* DEFAULT scheme */
        $request->setScheme(Def::SCHEMA_DEFAULT);
        $response = $this->_callCalc->bonusPersonal($request);
        $this->_calcIdBonusPersonalDef = $response->getCalcId();
        $this->assertTrue($response->isSucceed());
        /* EU scheme */
        $request->setScheme(Def::SCHEMA_EU);
        $response = $this->_callCalc->bonusPersonal($request);
        $this->_calcIdBonusPersonalEu = $response->getCalcId();
        $this->assertTrue($response->isSucceed());
    }

    private function _calcBonusTeam()
    {
        /* DEFAULT scheme */
        $request = new BonusCalcBonusTeamRequest();
        $request->setScheme(Def::SCHEMA_DEFAULT);
        $request->setCourtesyBonusPercent(self::DEF_COURTESY_BONUS_PERCENT);
        $response = $this->_callCalc->bonusTeam($request);
        $this->_calcIdBonusTeamDef = $response->getCalcId();
        $this->assertTrue($response->isSucceed());
        /* EU scheme */
        $request = new BonusCalcBonusTeamRequest();
        $request->setScheme(Def::SCHEMA_EU);
        $request->setTeamBonusPercent(self::DEF_TEAM_BONUS_EU_PERCENT);
        $response = $this->_callCalc->bonusTeam($request);
        $this->_calcIdBonusTeamEu = $response->getCalcId();
        $this->assertTrue($response->isSucceed());
    }

    private function _calcCompressOi()
    {
        $request = new BonusCalcCompressOiRequest();
        /* DEFAULT */
        $request->setScheme(Def::SCHEMA_DEFAULT);
        $response = $this->_callCalc->compressOi($request);
        $this->_calcIdCompressOiDef = $response->getCalcId();
        $this->assertTrue($response->isSucceed());
        /* EU */
        $request->setScheme(Def::SCHEMA_EU);
        $response = $this->_callCalc->compressOi($request);
        $this->_calcIdCompressOiEu = $response->getCalcId();
        $this->assertTrue($response->isSucceed());
    }

    private function _calcCompressPtc()
    {
        $request = new BonusCalcCompressPtcRequest();
        $response = $this->_callCalc->compressPtc($request);
        $this->_calcIdCompressPtc = $response->getCalcId();
        $this->assertTrue($response->isSucceed());
    }

    private function _calcPvWriteOff()
    {
        $request = new BonusCalcPvWriteOffRequest();
        $response = $this->_callCalc->pvWriteOff($request);
        $this->assertTrue($response->isSucceed());
    }

    private function _calcValueOv()
    {
        $request = new BonusCalcValueOvRequest();
        $response = $this->_callCalc->valueOv($request);
        $this->assertTrue($response->isSucceed());
    }

    private function _calcValueTv()
    {
        $request = new BonusCalcValueTvRequest();
        $response = $this->_callCalc->valueTv($request);
        $this->assertTrue($response->isSucceed());
    }

    private function _createCustomerDownlineTreeSnap()
    {
        /* calculate snapshots */
        $this->_logger->debug("Calculate snapshots.");
        $req = new SnapCalcRequest();
        $req->setDatestampTo(self::DS_CUSTOMER_ADDED);
        $respCalc = $this->_callDownlineSnap->calc($req);
        $this->assertTrue($respCalc->isSucceed());
    }

    private function _createCustomers()
    {
        /* create Magento Customers */
        $total = count($this->_mapCsvDownline);
        $this->_createMageCustomers($total);
        /* map MLM IDs to indexes and mage IDs and create tree based on Mage IDs */
        $mapTree = $this->_mapCustomersMlmId();
        $mapByCountry = $this->_mapCustomersCountries();
        /* expand minimal tree: populate tree with depth & path */
        $reqExpand = new SnapExtendMinimalRequest();
        $reqExpand->setTree($mapTree);
        $respExpand = $this->_callDownlineSnap->expandMinimal($reqExpand);
        $mapTree = $respExpand->getSnapData();
        /* create tree map sorted by level from top to bottom */
        $mapByDepthAsc = $this->_mapTreeByDepth($mapTree, Snap::ATTR_CUSTOMER_ID, Snap::ATTR_DEPTH);
        /* create customers in downline one by one */
        $period = $this->_toolPeriod;
        $dateAdded = $period->getTimestampFrom(self::DS_CUSTOMER_ADDED);
        foreach ($mapByDepthAsc as $depth => $ids) {
            foreach ($ids as $id) {
                $data = $mapTree[$id];
                $custId = $data[Snap::ATTR_CUSTOMER_ID];
                $parentId = $data[Snap::ATTR_PARENT_ID];
                $custNdx = $this->_mapCustomerIndexByMageId[$custId];
                $mlmId = $this->_mapCustomerMlmIdByIndex[$custNdx];
                $parentNdx = $this->_mapCustomerIndexByMageId[$parentId];
                $parentMlmId = $this->_mapCustomerMlmIdByIndex[$parentNdx];
                $countryCode = $mapByCountry[$mlmId];
                $reqAdd = new CustomerAddRequest();
                $reqAdd->setCustomerId($custId);
                $reqAdd->setParentId($parentId);
                $reqAdd->setReference($mlmId);
                $reqAdd->setCountryCode($countryCode);
                $reqAdd->setDate($dateAdded);
                $respAdd = $this->_callDownlineCustomer->add($reqAdd);
                if ($respAdd->isSucceed()) {
                    $this->_logger->debug("Downline change for customer #$custId ($mlmId) with parent #$parentId ($parentMlmId) is added to downline log.");
                } else {
                    $this->_logger->error("Cannot add new customer #$custId to downline tree.");
                }
            }
        }
        unset($csv);
        unset($mapByDepthAsc);
        unset($mapByCountry);
        unset($mapTree);
        $this->_createCustomerDownlineTreeSnap();
    }

    private function _createOrders()
    {
        foreach ($this->_mapCsvOrders as $orderId => $one) {
            $mlmId = $one[0];
            $amount = $one[1];
            $custId = $this->_getMageIdByMlmId($mlmId);
            $ts = $this->_toolPeriod->getTimestampTo(self::DS_CUSTOMER_ADDED);
            $bind = [
                Cfg::E_SALE_ORDER_A_CUSTOMER_ID => $custId,
                Cfg::E_SALE_ORDER_A_BASE_GRAND_TOTAL => $amount,
                Cfg::E_SALE_ORDER_A_CREATED_AT => $ts,
                Cfg::E_SALE_ORDER_A_UPDATED_AT => $ts
            ];
            $orderId = $this->_repoBasic->addEntity(Cfg::ENTITY_MAGE_SALES_ORDER, $bind);
            $bind = [
                PvSale::ATTR_SALE_ID => $orderId,
                PvSale::ATTR_SUBTOTAL => $amount,
                PvSale::ATTR_TOTAL => $amount,
                PvSale::ATTR_DATE_PAID => $ts
            ];
            $this->_repoBasic->addEntity(PvSale::ENTITY_NAME, $bind);
        }
    }

    private function _createPvBalances()
    {
        $csv = $this->_readCsvFile($path = __DIR__ . '/data/pv_balances.csv');
        $reqAddPv = new PvTransferCreditToCustomerRequest();
        $ts = $this->_toolPeriod->getTimestampTo(self::DS_CUSTOMER_ADDED);
        $reqAddPv->set(PvTransferCreditToCustomerRequest::DATE_APPLIED, $ts);
        foreach ($csv as $item) {
            $mlmId = $item[0];
            $pv = $this->_formatCsvNum($item[1]);
            $custNdx = $this->_mapCustomerIndexByMlmId[$mlmId];
            if (is_null($custNdx)) {
                $this->_logger->error("Cannot find customer index for MLM ID $mlmId.");
            }
            $custId = $this->_mapCustomerMageIdByIndex[$custNdx];
            $reqAddPv->set(PvTransferCreditToCustomerRequest::TO_CUSTOMER_ID, $custId);
            $reqAddPv->set(PvTransferCreditToCustomerRequest::VALUE, $pv);
            $respAddPv = $this->_callPvTransfer->creditToCustomer($reqAddPv);
            if ($respAddPv->isSucceed()) {
                $this->_logger->debug("'$pv' PV have been added to customer #$mlmId (mageID: #$custId).");
            } else {
                $this->_logger->debug("Cannot add '$pv' PV to customer #$mlmId (mageID: #$custId).");
            }
        }
    }

    private function _formatCsvNum($val)
    {
        $result = str_replace(',', '.', $val);
        $result = str_replace('"', '', $result);
        return $result;
    }

    /**
     * Get customer Mage ID by MLM ID.
     *
     * @param $mlmId
     *
     * @return mixed
     */
    private function _getMageIdByMlmId($mlmId)
    {
        $ndx = $this->_mapCustomerIndexByMlmId[$mlmId];
        $result = $this->_mapCustomerMageIdByIndex[$ndx];
        return $result;
    }

    /**
     * Get customer MLM ID by Magento ID.
     *
     * @param $mageId
     *
     * @return string
     */
    private function _getMlmIdByMageId($mageId)
    {
        $ndx = $this->_mapCustomerIndexByMageId[$mageId];
        $result = $this->_mapCustomerMlmIdByIndex[$ndx];
        return $result;
    }

    /**
     * Map countries by MLM ID.
     *
     * @return array [$mlmId=>$countryCode, ...]
     */
    private function _mapCustomersCountries()
    {
        $result = [];
        foreach ($this->_mapCsvDownline as $one) {
            $mlmId = $one[self::A_CUST_ID];
            $schema = $one[self::A_SCHEME];
            $countryCode = ($schema == self::SCHEMA_DEFAULT) ? self::DEFAULT_DOWNLINE_COUNTRY_CODE : self::EU_DOWNLINE_COUNTRY_CODE;
            $result[$mlmId] = $countryCode;
        }
        return $result;
    }

    private function _mapCustomersMlmId()
    {
        $result = [];
        /* create minimal tree based on MLM IDs */
        $treeOnMlmIds = [];
        foreach ($this->_mapCsvDownline as $one) {
            $mlmId = $one[self::A_CUST_ID];
            $parentMlmId = $one[self::A_PARENT_ID];
            $treeOnMlmIds[$mlmId] = $parentMlmId;
        }
        /* expand minimal tree: populate tree with depth & path */
        $reqExpand = new SnapExtendMinimalRequest();
        $reqExpand->setTree($treeOnMlmIds);
        $respExpand = $this->_callDownlineSnap->expandMinimal($reqExpand);
        $mapTree = $respExpand->getSnapData();
        /* create tree map sorted by level from top to bottom */
        $mapByDepthAsc = $this->_mapTreeByDepth($mapTree, Snap::ATTR_CUSTOMER_ID, Snap::ATTR_DEPTH);
        /* map MLM IDs and indexes */
        $ndx = 1;
        foreach ($mapByDepthAsc as $level => $ids) {
            foreach ($ids as $mlmId) {
                $this->_mapCustomerIndexByMlmId[$mlmId] = $ndx;
                $this->_mapCustomerMlmIdByIndex[$ndx] = $mlmId;
                $ndx++;
            }

        }
        /* create tree based on Mage IDs using original MLM IDs data and indexes */
        foreach ($this->_mapCsvDownline as $one) {
            $mlmId = $one[self::A_CUST_ID];
            $parentMlmId = $one[self::A_PARENT_ID];
            $custNdx = $this->_mapCustomerIndexByMlmId[$mlmId];
            $parentNdx = isset($this->_mapCustomerIndexByMlmId[$parentMlmId])
                ? $this->_mapCustomerIndexByMlmId[$parentMlmId] : null;
            if (is_null($parentNdx)) {
                /* this is orphan */
                $parentNdx = $custNdx;
            }
            $custId = $this->_mapCustomerMageIdByIndex[$custNdx];
            $parentId = $this->_mapCustomerMageIdByIndex[$parentNdx];
            $result[$custId] = $parentId;
        }
        return $result;
    }

    private function _mapTreeByDepth($tree, $labelCustId, $labelDepth)
    {
        $result = [];
        foreach ($tree as $one) {
            $customerId = $one[$labelCustId];
            $depth = $one[$labelDepth];
            if (!isset($result[$depth])) {
                $result[$depth] = [];
            }
            $result[$depth][] = $customerId;
        }
        /* sort by depth asc */
        ksort($result);
        return $result;
    }

    private function _readCsvBalances()
    {
        $csv = $this->_readCsvFile($path = __DIR__ . '/data/bonus.csv');
        foreach ($csv as $item) {
            $custMlmId = $item[0];
            $personal = $this->_formatCsvNum($item[1]);
            $team = $this->_formatCsvNum($item[2]);
            $courtesy = $this->_formatCsvNum($item[3]);
            $override = $this->_formatCsvNum($item[4]);
            $infinity = $this->_formatCsvNum($item[5]);
            $scheme = $item[6];
            /* reset courtesy bonus for EU customers */
            if ($scheme != self::SCHEMA_DEFAULT) {
                $team = $courtesy;
                $courtesy = 0;
            }
            $schemeCust = $this->_mapCsvDownline[$custMlmId][self::A_SCHEME];
            if ($scheme == $schemeCust) {
                $this->_mapCsvBalances[$custMlmId] = [
                    self::A_CUST_ID => $custMlmId,
                    self::A_PERSONAL => $personal,
                    self::A_TEAM => $team,
                    self::A_COURTESY => $courtesy,
                    self::A_OVERRIDE => $override,
                    self::A_INFINITY => $infinity,
                    self::A_SCHEME => $scheme
                ];
            }
        }
    }

    private function _readCsvDownline()
    {
        $csv = $this->_readCsvFile($path = __DIR__ . '/data/downline.csv');
        $map = [];
        foreach ($csv as $item) {
            $custMlmId = $item[0];
            $parentMlmId = $item[1];
            if (strlen($parentMlmId) == 0) {
                /* this is root node */
                $parentMlmId = $custMlmId;
            }
            $scheme = $item[2];
            $map[$custMlmId] = [
                self::A_CUST_ID => $custMlmId,
                self::A_PARENT_ID => $parentMlmId,
                self::A_SCHEME => $scheme
            ];
        }
        $this->_mapCsvDownline = $map;
    }

    private function _readCsvFile($path)
    {
        $result = [];
        $file = fopen($path, 'r');
        /* skip first row with header */
        fgetcsv($file);
        while ($row = fgetcsv($file)) {
            if ((count($row) > 0) && !is_null($row[0])) {
                $result[] = $row;
            }
        }
        return $result;
    }

    /**
     * Read target PTC compressed tree from CSV.
     */
    private function _readCsvPtcCompressedTree()
    {
        $csv = $this->_readCsvFile($path = __DIR__ . '/data/cpr_downline.csv');
        /* process all customers with initial PV */
        foreach ($csv as $item) {
            $mlmId = $item[0];
            $parentMlmId = $item[1];
            if ($parentMlmId == 'NULL') {
                $parentMlmId = $mlmId; // this is root
            }
            $pv = $this->_formatCsvNum($item[2]);
            $tv = $this->_formatCsvNum($item[3]);
            $this->_mapCsvTreePtc[$mlmId] = [$parentMlmId, $pv, $tv];
        }
        /* add transferred PV */
        foreach ($csv as $item) {
            $transferred = $this->_formatCsvNum($item[4]);
            if ($transferred > 0) {
                $targetMlmId = $item[6];
                $this->_mapCsvTreePtc[$targetMlmId][1] += $transferred; // pv
            }
        }
    }

    private function _readCsvRebates()
    {
        $csv = $this->_readCsvFile($path = __DIR__ . '/data/rebates.csv');
        $rebates = [];
        /* process all customers with initial PV */
        foreach ($csv as $item) {
            $custMlmId = $item[0];
            $paidMlmId = $item[1];
            $orderId = $item[2];
            $amount = $item[3];
            $bonus = $item[5];
            if ($custMlmId == $paidMlmId) {
                /* process only customer orders entries, skip bonuses to parents */
                $this->_mapCsvOrders[$orderId] = [$custMlmId, $amount];
            }
            if (isset($rebates[$paidMlmId])) {
                $rebates[$paidMlmId] += $bonus;
            } else {
                $rebates[$paidMlmId] = $bonus;
            }
        }
        /* process EU rebates and replace expected personal bonus */
        foreach ($rebates as $custMlmId => $bonus) {
            $this->_mapCsvBalances[$custMlmId][self::A_PERSONAL] = $bonus;
        }
    }

    private function _selectCompressionPtc($calcId)
    {
        $where = PtcCompression::ATTR_CALC_ID . '=' . (int)$calcId;
        $result = $this->_repoBasic->getEntities(PtcCompression::ENTITY_NAME, null, $where);
        return $result;
    }

    /**
     * Return array of the Wallet Active transactions related to [$calcId, ...].
     *
     * @param $calcIds array
     *
     * @return array [ [Account::ATTR_CUST_ID=>$id, Transaction::ATTR_VALUE=>$value], ... ]
     */
    private function _selectWalletTransactionsByCalcIds($calcIds)
    {
        /**
         * SELECT
         * paa.customer_id, SUM(pat.value) as value
         * FROM prxgt_acc_transaction pat
         * LEFT JOIN prxgt_acc_account paa
         * ON pat.credit_acc_id = paa.id
         * WHERE (pat.operation_id = operId1) OR (pat.operation_id = operId2) OR ...
         * GROUP BY paa.customer_id
         */
        /* aliases and tables */
        $asAcc = 'paa';
        $asTrn = 'pat';
        $tblAcc = $this->_resource->getTableName(Account::ENTITY_NAME);
        $tblTrn = $this->_resource->getTableName(Transaction::ENTITY_NAME);
        /* FROM prxgt_acc_transaction */
        $query = $this->_conn->select();
        $cols = [Transaction::ATTR_VALUE => 'SUM(' . Transaction::ATTR_VALUE . ')'];
        $query->from([$asTrn => $tblTrn], $cols);
        // JOIN prxgt_acc_account paa  ON pat.credit_acc_id = paa.id
        $on = "$asTrn." . Transaction::ATTR_CREDIT_ACC_ID . "=$asAcc." . Account::ATTR_ID;
        $cols = [Account::ATTR_CUST_ID];
        $query->joinLeft([$asAcc => $tblAcc], $on, $cols);
        // WHERE (pat.operation_id = operId1) OR (pat.operation_id = operId2) OR ...
        $where = '';
        foreach ($calcIds as $calcId) {
            /* get operation ID by calculation ID */
            $whereLog = LogOpers::ATTR_CALC_ID . '=' . (int)$calcId;
            $data = $this->_repoBasic->getEntities(LogOpers::ENTITY_NAME, null, $whereLog);
            $operId = $data[0][LogOpers::ATTR_OPER_ID]; // get first entry's 'oper_id' attribute
            /* compose WHERE clause */
            if (strlen($where) > 0) {
                $where .= ' OR ';
            }
            $where .= '(' . $asTrn . '.' . Transaction::ATTR_OPERATION_ID . '=' . (int)$operId . ')';
        }
        $query->where($where);
        $query->group(Account::ATTR_CUST_ID);
        // $sql = (string)$query;
        $result = $this->_conn->fetchAll($query);
        return $result;
    }

    private function _setBonusLevelsPersonal()
    {
        /* DEFAULT */
        $calTypeId = $this->_repoTypeCalc->getIdByCode(Cfg::CODE_TYPE_CALC_BONUS_PERSONAL_DEF);
        $data = [
            [Level::ATTR_CALC_TYPE_ID => $calTypeId, Level::ATTR_LEVEL => 0, Level::ATTR_PERCENT => 0],
            [Level::ATTR_CALC_TYPE_ID => $calTypeId, Level::ATTR_LEVEL => 50, Level::ATTR_PERCENT => 0.05],
            [Level::ATTR_CALC_TYPE_ID => $calTypeId, Level::ATTR_LEVEL => 100, Level::ATTR_PERCENT => 0.10],
            [Level::ATTR_CALC_TYPE_ID => $calTypeId, Level::ATTR_LEVEL => 500, Level::ATTR_PERCENT => 0.15],
            [Level::ATTR_CALC_TYPE_ID => $calTypeId, Level::ATTR_LEVEL => 750, Level::ATTR_PERCENT => 0.20]
        ];
        foreach ($data as $item) {
            $this->_repoBasic->addEntity(Level::ENTITY_NAME, $item);
        }
        /* EU */
        $calTypeId = $this->_repoTypeCalc->getIdByCode(Cfg::CODE_TYPE_CALC_BONUS_PERSONAL_EU);
        $data = [
            [Level::ATTR_CALC_TYPE_ID => $calTypeId, Level::ATTR_LEVEL => 0, Level::ATTR_PERCENT => 0.20],
            [Level::ATTR_CALC_TYPE_ID => $calTypeId, Level::ATTR_LEVEL => 100, Level::ATTR_PERCENT => 0.40]
        ];
        foreach ($data as $item) {
            $this->_repoBasic->addEntity(Level::ENTITY_NAME, $item);
        }
        $this->_logger->debug("Personal Bonus levels are set.");
    }

    private function _setBonusLevelsTeam()
    {
        /* DEFAULT */
        $calTypeId = $this->_repoTypeCalc->getIdByCode(Cfg::CODE_TYPE_CALC_BONUS_TEAM_DEF);
        $data = [
            [Level::ATTR_CALC_TYPE_ID => $calTypeId, Level::ATTR_LEVEL => 0, Level::ATTR_PERCENT => 0],
            [Level::ATTR_CALC_TYPE_ID => $calTypeId, Level::ATTR_LEVEL => 50, Level::ATTR_PERCENT => 0.10],
            [Level::ATTR_CALC_TYPE_ID => $calTypeId, Level::ATTR_LEVEL => 500, Level::ATTR_PERCENT => 0.15],
            [Level::ATTR_CALC_TYPE_ID => $calTypeId, Level::ATTR_LEVEL => 750, Level::ATTR_PERCENT => 0.20]
        ];
        foreach ($data as $item) {
            $this->_repoBasic->addEntity(Level::ENTITY_NAME, $item);
        }
        /* EU */
        $calTypeId = $this->_repoTypeCalc->getIdByCode(Cfg::CODE_TYPE_CALC_BONUS_TEAM_EU);
        $data = [
            [Level::ATTR_CALC_TYPE_ID => $calTypeId, Level::ATTR_LEVEL => 0, Level::ATTR_PERCENT => 0],
            [Level::ATTR_CALC_TYPE_ID => $calTypeId, Level::ATTR_LEVEL => 100, Level::ATTR_PERCENT => 0.05]
        ];
        foreach ($data as $item) {
            $this->_repoBasic->addEntity(Level::ENTITY_NAME, $item);
        }
        $this->_logger->debug("Team Bonus levels are set.");
    }

    private function _setConfigOverride()
    {
        /* DEFAULT */
        $full = [2 => 0.10, 3 => 0.07, 4 => 0.05, 5 => 0.03, 6 => 0.02, 7 => 0.01];
        $dataDef = [
            [Def::RANK_SEN_MANAGER, Def::SCHEMA_DEFAULT, [2 => 0.10, 3 => 0.07]],
            [Def::RANK_SUPERVISOR, Def::SCHEMA_DEFAULT, [2 => 0.10, 3 => 0.07, 4 => 0.05]],
            [Def::RANK_DIRECTOR, Def::SCHEMA_DEFAULT, [2 => 0.10, 3 => 0.07, 4 => 0.05, 5 => 0.03]],
            [Def::RANK_SEN_DIRECTOR, Def::SCHEMA_DEFAULT, [2 => 0.10, 3 => 0.07, 4 => 0.05, 5 => 0.03, 6 => 0.02]],
            [Def::RANK_EXEC_DIRECTOR, Def::SCHEMA_DEFAULT, $full],
            [Def::RANK_SEN_VICE, Def::SCHEMA_DEFAULT, $full],
            [Def::RANK_EXEC_VICE, Def::SCHEMA_DEFAULT, $full],
            [Def::RANK_PRESIDENT, Def::SCHEMA_DEFAULT, $full]
        ];
        foreach ($dataDef as $one) {
            $rankCode = $one[0];
            $scheme = $one[1];
            $gen = $one[2];
            foreach ($gen as $level => $percent) {
                $this->_addConfigOverride($rankCode, $scheme, $level, $percent);
            }
        }
        $this->_logger->debug("Override bonus configuration for DEFAULT scheme is set.");
        /* EU */
        $full = [2 => 0.15, 3 => 0.10, 4 => 0.10, 5 => 0.05, 6 => 0.03];
        $dataDef = [
            [Def::RANK_MANAGER, Def::SCHEMA_EU, [2 => 0.15]],
            [Def::RANK_SEN_MANAGER, Def::SCHEMA_EU, [2 => 0.15, 3 => 0.10]],
            [Def::RANK_SUPERVISOR, Def::SCHEMA_EU, [2 => 0.15, 3 => 0.10, 4 => 0.10]],
            [Def::RANK_DIRECTOR, Def::SCHEMA_EU, [2 => 0.15, 3 => 0.10, 4 => 0.10, 5 => 0.05]],
            [Def::RANK_SEN_DIRECTOR, Def::SCHEMA_EU, $full],
            [Def::RANK_SEN_VICE, Def::SCHEMA_EU, $full],
            [Def::RANK_EXEC_VICE, Def::SCHEMA_EU, $full],
            [Def::RANK_PRESIDENT, Def::SCHEMA_EU, $full]
        ];
        foreach ($dataDef as $one) {
            $rankCode = $one[0];
            $scheme = $one[1];
            $gen = $one[2];
            foreach ($gen as $level => $percent) {
                $this->_addConfigOverride($rankCode, $scheme, $level, $percent);
            }
        }
        $this->_logger->debug("Override bonus configuration for EU scheme is set.");
    }

    /**
     * Add data to 'cfg_param' table.
     */

    private function _setConfigParameters()
    {
        /* DEFAULT */
        $dataDef = [
            [Def::RANK_MANAGER, Def::SCHEMA_DEFAULT, 100, 750, 0, 0, 0, 0],
            [Def::RANK_SEN_MANAGER, Def::SCHEMA_DEFAULT, 100, 750, 0, 0, 750, 0],
            [Def::RANK_SUPERVISOR, Def::SCHEMA_DEFAULT, 100, 750, 0, 750, 1500, 0],
            [Def::RANK_DIRECTOR, Def::SCHEMA_DEFAULT, 100, 750, 1000, 1500, 2250, 0],
            [Def::RANK_SEN_DIRECTOR, Def::SCHEMA_DEFAULT, 100, 750, 2250, 3750, 4500, 0],
            [Def::RANK_EXEC_DIRECTOR, Def::SCHEMA_DEFAULT, 100, 750, 2250, 4500, 9000, 0],
            [Def::RANK_SEN_VICE, Def::SCHEMA_DEFAULT, 100, 750, 3000, 9000, 15000, 0.01],
            [Def::RANK_EXEC_VICE, Def::SCHEMA_DEFAULT, 100, 750, 6000, 15000, 18000, 0.02],
            [Def::RANK_PRESIDENT, Def::SCHEMA_DEFAULT, 100, 750, 9000, 18000, 24000, 0.03]
        ];
        foreach ($dataDef as $one) {
            $this->_addConfigParameter($one[0], $one[1], $one[2], $one[3], $one[4], $one[5], $one[6], $one[7]);
        }
        $this->_logger->debug("Common configuration for DEFAULT scheme is set.");
        /* EU */
        $dataDef = [
            [Def::RANK_MANAGER, Def::SCHEMA_EU, 100, 500, 0, 0, 0, 0],
            [Def::RANK_SEN_MANAGER, Def::SCHEMA_EU, 100, 500, 0, 0, 750, 0],
            [Def::RANK_SUPERVISOR, Def::SCHEMA_EU, 100, 800, 0, 800, 1600, 0],
            [Def::RANK_DIRECTOR, Def::SCHEMA_EU, 100, 1000, 900, 1600, 2500, 0],
            [Def::RANK_SEN_DIRECTOR, Def::SCHEMA_EU, 100, 1000, 2300, 3800, 4500, 0],
            [Def::RANK_SEN_VICE, Def::SCHEMA_EU, 100, 1000, 2300, 9000, 10500, 0.01],
            [Def::RANK_EXEC_VICE, Def::SCHEMA_EU, 100, 1000, 3000, 12000, 15000, 0.02],
            [Def::RANK_PRESIDENT, Def::SCHEMA_EU, 100, 1000, 6000, 12000, 24000, 0.03]
        ];
        foreach ($dataDef as $one) {
            $this->_addConfigParameter($one[0], $one[1], $one[2], $one[3], $one[4], $one[5], $one[6], $one[7]);
        }
        $this->_logger->debug("Common configuration for EU scheme is set.");
    }

    private function _setRanks()
    {
        $data = [
            [Rank::ATTR_CODE => Def::RANK_DISTRIBUTOR, Rank::ATTR_NOTE => 'Manager (#00, lowest).'],
            [Rank::ATTR_CODE => Def::RANK_MANAGER, Rank::ATTR_NOTE => 'Manager (#01, lowest).'],
            [Rank::ATTR_CODE => Def::RANK_SEN_MANAGER, Rank::ATTR_NOTE => 'Senior Manager (#02).'],
            [Rank::ATTR_CODE => Def::RANK_SUPERVISOR, Rank::ATTR_NOTE => 'Supervisor (#3).'],
            [Rank::ATTR_CODE => Def::RANK_DIRECTOR, Rank::ATTR_NOTE => 'Director (#4).'],
            [Rank::ATTR_CODE => Def::RANK_SEN_DIRECTOR, Rank::ATTR_NOTE => 'Senior Director (#5).'],
            [Rank::ATTR_CODE => Def::RANK_EXEC_DIRECTOR, Rank::ATTR_NOTE => 'Executive Director (#6).'],
            [Rank::ATTR_CODE => Def::RANK_SEN_VICE, Rank::ATTR_NOTE => 'Senior Vice President (#7).'],
            [Rank::ATTR_CODE => Def::RANK_EXEC_VICE, Rank::ATTR_NOTE => 'Executive Vice President (#8).'],
            [Rank::ATTR_CODE => Def::RANK_PRESIDENT, Rank::ATTR_NOTE => 'President Director (#9, highest).'],
        ];
        foreach ($data as $item) {
            $this->_repoBasic->addEntity(Rank::ENTITY_NAME, $item);
        }
        $this->_logger->debug("Ranks are set.");
    }


    /**
     * @param $operTypeCode string see Cfg::CODE_TYPE_OPER_BONUS_...
     * @param $calcIds array see $this->_calcIdBonus...
     * @param $csvBonusType string see self::A_...
     */
    private function _validateBonus($operTypeCode, $calcIds, $csvBonusType)
    {
        $actualBalances = $this->_selectWalletTransactionsByCalcIds($calcIds);
        /* select all actual none zero balances and compare to expected */
        $mapActual = [];
        $wrongBalances = [];
        foreach ($actualBalances as $item) {
            $custId = $item[Account::ATTR_CUST_ID];
            $custRef = $this->_getMlmIdByMageId($custId);
            $actual = $item[Transaction::ATTR_VALUE];
            $mapActual[$custRef] = $actual;
            $balances = $this->_mapCsvBalances[$custRef];
            $expected = $balances[$csvBonusType];
            $delta = abs($actual - $expected);
            /* this is summary bonus, so cumulative rounding error can be valuable */
            if ($delta > Cfg::DEF_ZERO) {
                $this->_logger->debug("VALID: Wrong $operTypeCode bonus value for customer $custRef (#$custId ), expected '$expected', actual '$actual' (delta: $delta).");
                $wrongBalances[$custRef] = ['expected' => $expected, 'actual' => $actual];
            }
        }
        // $this->assertTrue($wrongCount == 0, "There are '$wrongCount' total expected non-zero override bonus balances that are not equal to the actual results.");
        /* select all expected none zero balances and compare to actual zero balances */
        $missedBalances = [];
        foreach ($this->_mapCsvBalances as $custRef => $balances) {
            $expected = isset($balances[$csvBonusType]) ? $balances[$csvBonusType] : 0;
            if (($expected > Cfg::DEF_ZERO)) {
                if (!isset($mapActual[$custRef])) {
                    $custId = $this->_getMageIdByMlmId($custRef);
                    $this->_logger->debug("VALID: Wrong $operTypeCode bonus value for customer $custRef (#$custId ), expected '$expected', actual '0'.");
                    $missedBalances[$custRef] = $expected;
                }
            }
        }
        $actualCount = count($actualBalances);
        $wrongCount = count($wrongBalances);
        $missedCount = count($missedBalances);
        $this->_logger->debug("VALID: $operTypeCode: actual: $actualCount, wrong: $wrongCount, missed: $missedCount.");
        /* use The Pareto principle in validation */
        $pareto = ($actualCount - $wrongCount - $missedCount) / $actualCount;
        $pareto = number_format($pareto, 2, '.', '');
        $this->assertTrue($pareto >= 0.8, "There are too much errors in validation result (>80%).");
    }

    private function _validateCompressionPtc()
    {
        $wrongItems = [];
        $actualItems = [];
        $data = $this->_selectCompressionPtc($this->_calcIdCompressPtc);
        foreach ($data as $one) {
            $custId = $one[PtcCompression::ATTR_CUSTOMER_REF];
            $parentId = $one[PtcCompression::ATTR_PARENT_REF];
            $pv = $one[PtcCompression::ATTR_PV];
            $custMlmId = $this->_getMlmIdByMageId($custId);
            $parentMlmId = $this->_getMlmIdByMageId($parentId);
            $targetItem = $this->_mapCsvTreePtc[$custMlmId];
            if (!is_null($targetItem)) {
                $targetParentMlmId = $targetItem[0];
                $targetPv = $targetItem[1];
                if (
                    ($parentMlmId != $targetParentMlmId) ||
                    (abs($pv - $targetPv) > Cfg::DEF_ZERO)
                ) {
                    $wrongItems[$custMlmId] = [
                        'expect' => [$targetParentMlmId, $targetPv],
                        'act' => [$parentMlmId, $pv]
                    ];
                    $this->_logger->debug("Wrong PTC compress validation: customer #$custMlmId (#$custId) " . var_export($wrongItems[$custMlmId],
                            true));
                } else {
                    /* correct actual item */
                    $actualItems[] = $custMlmId;
                }
            } else {
                $this->_logger->debug("Wrong PTC compress validation: customer #$custMlmId (#$custId) is not found in the target compressed tree.");
            }
        }
        $this->assertTrue(count($wrongItems) == 0, "There are some wrong items in the compressed downline tree (PTC).");
        /* select all expected none zero PVs and compare to actual data */
        $missed = [];
        foreach ($this->_mapCsvTreePtc as $mlmId => $one) {
            $pvExp = $one[1];
            if ($pvExp > 0) {
                if (in_array($mlmId, $actualItems)) {
                    continue;
                } else {
                    $missed[] = $mlmId;
                    $this->_logger->debug("Wrong PTC compress validation: non-zero PV ($pvExp) for customer #$mlmId is not found in the actual results.");
                }
            }
        }
        $this->assertTrue(count($missed) == 0,
            "There are some missed items in the compressed downline tree (PTC)." . var_export($missed, true));
    }

    private function _validateTv()
    {
        $wrongItems = [];
        $missedItems = [];
        $data = $this->_selectCompressionPtc($this->_calcIdCompressPtc);
        foreach ($data as $one) {
            $custId = $one[PtcCompression::ATTR_CUSTOMER_REF];
            $tv = $one[PtcCompression::ATTR_TV];
            $custMlmId = $this->_getMlmIdByMageId($custId);
            $targetItem = $this->_mapCsvTreePtc[$custMlmId];
            if (!is_null($targetItem)) {
                $expectTv = $targetItem[2];
                if ((abs($tv - $expectTv) > Cfg::DEF_ZERO)) {
                    $wrongItems[$custMlmId] = ['expect' => $expectTv, 'act' => $tv];
                    $this->_logger->debug("Wrong TV value: customer #$custMlmId (#$custId ) " . var_export($wrongItems[$custMlmId],
                            true));
                }
            } else {
                $missedItems[$custMlmId] = $tv;
                $this->_logger->debug("Wrong TV value: customer #$custMlmId (#$custId ) is not found in the target compressed tree.");
            }
        }
        $wrongCount = count($wrongItems);
        $this->assertTrue($wrongCount == 0, "There are $wrongCount items with wrong TV values.");
        $missedCount = count($missedItems);
        $this->assertTrue($missedCount == 0, "There are $missedCount items with missed TV values.");
    }

    public function test_main()
    {
        $this->_logger->debug('Compare scenario in Hybrid Bonus tests is started.');
        $this->_conn->beginTransaction();
        try {
            /* prepare project specific data (Santegra by default) */
            $this->_setBonusLevelsPersonal();
            $this->_setBonusLevelsTeam();
            $this->_setRanks();
            $this->_setConfigParameters();
            $this->_setConfigOverride();
            /* read data from SCV (input and expected values) */
            $this->_readCsvDownline();
            $this->_readCsvBalances();
            $this->_readCsvRebates();
            $this->_readCsvPtcCompressedTree();
            /* create initial data in DB */
            $this->_createCustomers();
            $this->_createPvBalances();
            $this->_createOrders();
            /* perform calculations and validate results  */
            $this->_calcPvWriteOff();
            $this->_calcCompressPtc();
            $this->_validateCompressionPtc();
            $this->_calcBonusPersonal();
            $this->_validateBonus(
                Cfg::CODE_TYPE_OPER_BONUS_PERSONAL,
                [$this->_calcIdBonusPersonalDef, $this->_calcIdBonusPersonalEu],
                self::A_PERSONAL
            );
            $this->_calcValueTv();
            $this->_validateTv();
            $this->_calcBonusTeam();
            $this->_validateBonus(
                Cfg::CODE_TYPE_OPER_BONUS_TEAM,
                [$this->_calcIdBonusTeamDef, $this->_calcIdBonusTeamEu],
                self::A_TEAM
            );
            $this->_calcBonusCourtesy();
            $this->_validateBonus(
                Cfg::CODE_TYPE_OPER_BONUS_COURTESY,
                [$this->_calcIdBonusCourtesy],
                self::A_COURTESY
            );
            $this->_calcValueOv();
            $this->_calcCompressOi();
            $this->_calcBonusOverride();
            $this->_validateBonus(
                Cfg::CODE_TYPE_OPER_BONUS_OVERRIDE,
                [$this->_calcIdBonusOverrideDef, $this->_calcIdBonusOverrideEu],
                self::A_OVERRIDE
            );
            $this->_calcBonusInfinity();
            $this->_validateBonus(
                Cfg::CODE_TYPE_OPER_BONUS_INFINITY,
                [$this->_calcIdBonusInfinityDef, $this->_calcIdBonusInfinityEu],
                self::A_INFINITY
            );
        } catch (\Exception $e) {
            $this->_logger->error("Exception in integration test. Message: " . $e->getMessage());
            throw $e;
        } finally {
            //            $this->_conn->commit();
            $this->_conn->rollBack();
        }
        $this->_logMemoryUsage();
        $this->_logger->debug('Compare scenario in Hybrid Bonus tests is completed, all transactions are rolled back.');
    }
}