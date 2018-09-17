<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Helper;

use Praxigento\BonusBase\Repo\Data\Rank;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Data\Cfg\Param as CfgParam;
use Praxigento\Downline\Repo\Data\Customer;

/**
 * Hybrid bonus configuration parameters.
 */
class Scheme
    implements \Praxigento\BonusHybrid\Api\Helper\Scheme
{
    const A_RANK_ID = 'RankId';
    const A_SCHEME = 'Scheme';

    /**
     * There are 2 customers with forced qualifications and ranks in Santegra project.
     *
     * @var array [$mlmId=>[$schema, $rankCode], ...]
     */
    private $QUALIFIED_CUSTOMERS = [
        '770000001' => [Cfg::SCHEMA_DEFAULT, Cfg::RANK_PRESIDENT],
        '777017725' => [Cfg::SCHEMA_DEFAULT, Cfg::RANK_PRESIDENT]
    ];
    /**
     * @var array of the customers with forced qualification.
     */
    private $cacheForcedCustomerIds = null;
    /**
     * Cached values for customers with forced ranks. Each customer can be ranked in all schemes.
     *
     * @var array [$custId=>[$schema=>[A_RANK_ID=>$rankId, A_CFG_PARAMS=>[...]], ...], ...]
     */
    private $cacheForcedRanks = null;
    /**
     * @var array of customers with forced qualification from 'Sign Up Volume Debit' (MOBI-635)
     */
    private $cacheSignUpDebitCustIds = null;
    /** @var  \Magento\Framework\DB\Adapter\AdapterInterface */
    private $conn;
    /** @var \Praxigento\Downline\Repo\Dao\Customer */
    private $daoDwnlCust;
    /** @var \Praxigento\Core\Api\App\Repo\Generic */
    private $daoGeneric;
    /** @var \Praxigento\BonusHybrid\Repo\Dao\Registry\SignUpDebit */
    private $daoRegSignUpDebit;
    /** @var \Praxigento\BonusHybrid\Repo\Query\SignUpDebit\GetLastCalcIdForPeriod */
    private $queryGetLastSignUpCalcId;
    /** @var \Magento\Framework\App\ResourceConnection */
    private $resource;

    /**
     * Scheme constructor.
     */
    public function __construct(
        \Magento\Framework\App\ResourceConnection $resource,
        \Praxigento\Core\Api\App\Repo\Generic $daoGeneric,
        \Praxigento\Downline\Repo\Dao\Customer $daoDwnlCust,
        \Praxigento\BonusHybrid\Repo\Dao\Registry\SignUpDebit $daoRegSignUpDebit,
        \Praxigento\BonusHybrid\Repo\Query\SignUpDebit\GetLastCalcIdForPeriod $queryGetLastSignUpCalcId
    ) {
        $this->resource = $resource;
        $this->conn = $resource->getConnection();
        $this->daoGeneric = $daoGeneric;
        $this->daoDwnlCust = $daoDwnlCust;
        $this->daoRegSignUpDebit = $daoRegSignUpDebit;
        $this->queryGetLastSignUpCalcId = $queryGetLastSignUpCalcId;
    }


    /**
     * Get all ranks configuration parameters to create map for forced customers.
     *
     * SELECT
     * pbhcp.*,
     * pbhr.code
     * FROM prxgt_bon_hyb_cfg_param pbhcp
     * LEFT JOIN prxgt_bon_hyb_rank pbhr
     * ON pbhcp.rank_id = pbhr.id
     *
     * @return array [$rankCode => [$scheme=>[$data], ...], ...]
     */
    private function getCfgParamsByRanks()
    {
        /* aliases and tables */
        $asParams = 'pbhcp';
        $asRank = 'pbhr';
        $tblParams = $this->resource->getTableName(CfgParam::ENTITY_NAME);
        $tblRank = $this->resource->getTableName(Rank::ENTITY_NAME);
        // FROM prxgt_bon_hyb_cfg_param pbhcp
        $query = $this->conn->select();
        $query->from([$asParams => $tblParams]);
        // LEFT JOIN prxgt_bon_hyb_rank pbhr ON pbhcp.rank_id = pbhr.id
        $on = "$asParams." . CfgParam::A_RANK_ID . "=$asRank." . Rank::A_ID;
        $cols = [Rank::A_CODE];
        $query->joinLeft([$asRank => $tblRank], $on, $cols);
        // $sql = (string)$query;
        $entries = $this->conn->fetchAll($query);
        $result = [];
        foreach ($entries as $entry) {
            $rankCode = $entry[Rank::A_CODE];
            $rankScheme = $entry[CfgParam::A_SCHEME];
            $result[$rankCode][$rankScheme] = $entry;
        }
        return $result;
    }

    /**
     * IDs for customers with forced qualification.
     *
     * @return array [[Customer::A_CUSTOMER_ID=>..., Customer::A_MLM_ID=>...], ...]
     */
    private function getForcedCustomersIds()
    {
        $mlmIds = array_keys($this->QUALIFIED_CUSTOMERS);
        $where = '';
        foreach ($mlmIds as $one) {
            /* skip first iteration */
            if (strlen($where) > 0) {
                $where .= ' OR ';
            }
            $quoted = $this->conn->quote($one);
            $where .= Customer::A_MLM_ID . "=\"$quoted\"";
        }
        $cols = [Customer::A_CUSTOMER_ID, Customer::A_MLM_ID];
        $result = $this->daoGeneric->getEntities(Customer::ENTITY_NAME, $cols, $where);
        return $result;
    }

    public function getForcedPv($custId, $scheme, $pv)
    {
        $result = $pv;
        /* be sure to have _cached data */
        $this->getForcedQualificationCustomers();
        if (in_array($custId, $this->cacheForcedCustomerIds)) {
            $custData = $this->cacheForcedRanks[$custId];
            $qpv = $custData[$scheme][CfgParam::A_QUALIFY_PV];
            if ($result < $qpv) {
                $result = $qpv;
            }
        }
        return $result;
    }

    /**
     * Load configuration data for customers with forced qualification.
     *
     * @return array
     */
    public function getForcedQualificationCustomers()
    {
        if (is_null($this->cacheForcedRanks)) {
            /* get Customer IDs from DB to map ranks to Mage IDs instead of MLM IDs */
            $custIds = $this->getForcedCustomersIds();
            /* get all ranks with configuration parameters for all schemes */
            $ranks = $this->getCfgParamsByRanks();
            $this->cacheForcedRanks = [];
            foreach ($custIds as $item) {
                $custId = $item[Customer::A_CUSTOMER_ID];
                $ref = $item[Customer::A_MLM_ID];
                $rankCode = $this->QUALIFIED_CUSTOMERS[$ref][1];
                $cfgParamsWithSchemes = $ranks[$rankCode];
                $this->cacheForcedRanks[$custId] = $cfgParamsWithSchemes;
            }
            /* compose map from customer IDs for quick search */
            $this->cacheForcedCustomerIds = array_keys($this->cacheForcedRanks);
        }
        return $this->cacheForcedRanks;
    }

    public function getForcedQualificationCustomersIds()
    {
        $this->getForcedQualificationCustomers();
        return $this->cacheForcedCustomerIds;
    }

    public function getForcedQualificationRank($custId, $scheme)
    {
        $result = null;
        $forced = $this->getForcedQualificationCustomers();
        if (isset($forced[$custId][$scheme])) {
            $result = $forced[$custId][$scheme][CfgParam::A_RANK_ID];
        }
        return $result;
    }

    /**
     * MOBI-635: get customers w/o 100 PV from Sign Up Volume Debit
     */
    protected function getForcedSignUpDebitCustIds()
    {
        if (is_null($this->cacheSignUpDebitCustIds)) {
            $ids = [];
            $calcId = $this->queryGetLastSignUpCalcId->exec();
            $where = \Praxigento\BonusHybrid\Repo\Data\Registry\SignUpDebit::A_CALC_REF . '=' . (int)$calcId;
            $rs = $this->daoRegSignUpDebit->get($where);
            /** @var \Praxigento\BonusHybrid\Repo\Data\Registry\SignUpDebit $one */
            foreach ($rs as $one) {
                $custRef = $one->getCustomerRef();
                $ids[] = $custRef;
            }
            $this->cacheSignUpDebitCustIds = $ids;
        }
        return $this->cacheSignUpDebitCustIds;

    }

    public function getForcedTv($custId, $scheme, $tv)
    {
        $result = $tv;
        /* be sure to have _cached data */
        $this->getForcedQualificationCustomers();
        if (in_array($custId, $this->cacheForcedCustomerIds)) {
            $custData = $this->cacheForcedRanks[$custId];
            $qpv = $custData[$scheme][CfgParam::A_QUALIFY_TV];
            if ($result < $qpv) {
                $result = $qpv;
            }
        }
        return $result;
    }

    public function getQualificationLevels()
    {
        $result = [
            Cfg::SCHEMA_DEFAULT => Cfg::PV_QUALIFICATION_LEVEL_DEF,
            Cfg::SCHEMA_EU => Cfg::PV_QUALIFICATION_LEVEL_EU
        ];
        return $result;
    }

    public function getSchemeByCustomer($data)
    {
        $result = Cfg::SCHEMA_DEFAULT;
        if (is_array($data)) {
            $countryCode = $data[Customer::A_COUNTRY_CODE];
        } elseif ($data instanceof \Praxigento\Core\Data) {
            $countryCode = $data->get(Customer::A_COUNTRY_CODE);
        }
        $code = strtoupper($countryCode);
        if (
            ($code == 'AT') ||
            ($code == 'DE') ||
            ($code == 'ES') ||
            ($code == 'LU')
        ) {
            $result = Cfg::SCHEMA_EU;
        }
        return $result;
    }

    public function getSchemeByCustomerId($id)
    {
        $data = $this->daoDwnlCust->getById($id);
        $result = $this->getSchemeByCustomer($data);
        return $result;
    }
}