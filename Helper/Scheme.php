<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Helper;

use Praxigento\BonusBase\Repo\Data\Rank;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Data\Cfg\Param as CfgParam;
use Praxigento\Downline\Repo\Entity\Data\Customer;

/**
 * TODO: move this tool to Repo section or extract DB related methods to standalone class.
 */
class Scheme
    extends \Praxigento\Core\App\Repo\Def\Db
    implements \Praxigento\BonusHybrid\Helper\IScheme
{
    const A_RANK_ID = 'RankId';
    const A_SCHEME = 'Scheme';


    /**
     * There are 3 customer with forced qualifications and ranks in Santegra project.
     *
     * @var array [$mlmId=>[$schema, $rankCode], ...]
     */
    private $QUALIFIED_CUSTOMERS = [
        '770000001' => [Cfg::SCHEMA_DEFAULT, Cfg::RANK_PRESIDENT],
        '777163048' => [Cfg::SCHEMA_DEFAULT, Cfg::RANK_EXEC_DIRECTOR],
        '777017725' => [Cfg::SCHEMA_DEFAULT, Cfg::RANK_PRESIDENT]
    ];
    /**
     * @var array of the customers with forced qualification.
     */
    private $_cachedForcedCustomerIds = null;
    /**
     * Cached values for customers with forced ranks. Each customer can be ranked in all schemes.
     *
     * @var array [$custId=>[$schema=>[A_RANK_ID=>$rankId, A_CFG_PARAMS=>[...]], ...], ...]
     */
    private $_cachedForcedRanks = null;
    /** @var \Praxigento\Core\App\Repo\IGeneric */
    protected $_repoBasic;
    /**
     * @var array of customers with forced qualification from 'Sign Up Volume Debit' (MOBI-635)
     */
    private $cachedSignupDebitCustIds = null;
    /** @var \Praxigento\BonusHybrid\Repo\Query\SignupDebit\GetLastCalcIdForPeriod */
    protected $queryGetLastSignupCalcId;
    /** @var \Praxigento\BonusHybrid\Repo\Dao\Registry\SignupDebit */
    protected $repoRegSignupDebit;

    /**
     * Scheme constructor.
     */
    public function __construct(
        \Magento\Framework\App\ResourceConnection $resource,
        \Praxigento\Core\App\Repo\IGeneric $repoGeneric,
        \Praxigento\BonusHybrid\Repo\Dao\Registry\SignupDebit $repoRegSignupDebit,
        \Praxigento\BonusHybrid\Repo\Query\SignupDebit\GetLastCalcIdForPeriod $queryGetLastSignupCalcId
    ) {
        parent::__construct($resource);
        $this->_repoBasic = $repoGeneric;
        $this->repoRegSignupDebit = $repoRegSignupDebit;
        $this->queryGetLastSignupCalcId = $queryGetLastSignupCalcId;
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
    private function _getCfgParamsByRanks()
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
        $on = "$asParams." . CfgParam::ATTR_RANK_ID . "=$asRank." . Rank::ATTR_ID;
        $cols = [Rank::ATTR_CODE];
        $query->joinLeft([$asRank => $tblRank], $on, $cols);
        // $sql = (string)$query;
        $entries = $this->conn->fetchAll($query);
        $result = [];
        foreach ($entries as $entry) {
            $rankCode = $entry[Rank::ATTR_CODE];
            $rankScheme = $entry[CfgParam::ATTR_SCHEME];
            $result[$rankCode][$rankScheme] = $entry;
        }
        return $result;
    }

    /**
     * IDs for customers with forced qualification.
     *
     * @return array [[Customer::ATTR_CUSTOMER_ID=>..., Customer::ATTR_MLM_ID=>...], ...]
     */
    private function _getForcedCustomersIds()
    {
        $mlmIds = array_keys($this->QUALIFIED_CUSTOMERS);
        $where = '';
        foreach ($mlmIds as $one) {
            /* skip first iteration */
            if (strlen($where) > 0) {
                $where .= ' OR ';
            }
            $quoted = $this->conn->quote($one);
            $where .= Customer::ATTR_MLM_ID . "=\"$quoted\"";
        }
        $cols = [Customer::ATTR_CUSTOMER_ID, Customer::ATTR_MLM_ID];
        $result = $this->_repoBasic->getEntities(Customer::ENTITY_NAME, $cols, $where);
        return $result;
    }

    public function getForcedPv($custId, $scheme, $pv)
    {
        $result = $pv;
        /* be sure to have _cached data */
        $this->getForcedQualificationCustomers();
        if (in_array($custId, $this->_cachedForcedCustomerIds)) {
            $custData = $this->_cachedForcedRanks[$custId];
            $qpv = $custData[$scheme][CfgParam::ATTR_QUALIFY_PV];
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
        if (is_null($this->_cachedForcedRanks)) {
            /* get Customer IDs from DB to map ranks to Mage IDs instead of MLM IDs */
            $custIds = $this->_getForcedCustomersIds();
            /* get all ranks with configuration parameters for all schemes */
            $ranks = $this->_getCfgParamsByRanks();
            $this->_cachedForcedRanks = [];
            foreach ($custIds as $item) {
                $custId = $item[Customer::ATTR_CUSTOMER_ID];
                $ref = $item[Customer::ATTR_MLM_ID];
                $rankCode = $this->QUALIFIED_CUSTOMERS[$ref][1];
                $cfgParamsWithSchemes = $ranks[$rankCode];
                $this->_cachedForcedRanks[$custId] = $cfgParamsWithSchemes;
            }
            /* compose map from customer IDs for quick search */
            $this->_cachedForcedCustomerIds = array_keys($this->_cachedForcedRanks);
        }
        return $this->_cachedForcedRanks;
    }

    public function getForcedQualificationCustomersIds()
    {
        $this->getForcedQualificationCustomers();
        return $this->_cachedForcedCustomerIds;
    }

    public function getForcedQualificationRank($custId, $scheme)
    {
        $result = null;
        $forced = $this->getForcedQualificationCustomers();
        if (isset($forced[$custId][$scheme])) {
            $result = $forced[$custId][$scheme][CfgParam::ATTR_RANK_ID];
        }
        return $result;
    }

    /**
     * MOBI-635: get customers w/o 100 PV from Sign Up Volume Debit
     */
    protected function getForcedSignupDebitCustIds()
    {
        if (is_null($this->cachedSignupDebitCustIds)) {
            $ids = [];
            $calcId = $this->queryGetLastSignupCalcId->exec();
            $where = \Praxigento\BonusHybrid\Repo\Data\Registry\SignupDebit::ATTR_CALC_REF . '=' . (int)$calcId;
            $rs = $this->repoRegSignupDebit->get($where);
            foreach ($rs as $one) {
                /* TODO: use as object not as array */
                $one = (array)$one->get();
                $ids[] = $one[\Praxigento\BonusHybrid\Repo\Data\Registry\SignupDebit::ATTR_CUST_REF];
            }
            $this->cachedSignupDebitCustIds = $ids;
        }
        return $this->cachedSignupDebitCustIds;

    }

    public function getForcedTv($custId, $scheme, $tv)
    {
        $result = $tv;
        /* be sure to have _cached data */
        $this->getForcedQualificationCustomers();
        if (in_array($custId, $this->_cachedForcedCustomerIds)) {
            $custData = $this->_cachedForcedRanks[$custId];
            $qpv = $custData[$scheme][CfgParam::ATTR_QUALIFY_TV];
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
            $countryCode = $data[Customer::ATTR_COUNTRY_CODE];
        } elseif ($data instanceof \Praxigento\Core\Data) {
            $countryCode = $data->get(Customer::ATTR_COUNTRY_CODE);
        }
        $code = strtoupper($countryCode);
        if (
            ($code == 'AT') ||
            ($code == 'DE') ||
            ($code == 'ES')
        ) {
            $result = Cfg::SCHEMA_EU;
        }
        return $result;
    }
}