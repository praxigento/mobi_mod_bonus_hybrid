<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\Bonus\Hybrid\Lib\Tool\Def;

use Praxigento\Bonus\Base\Lib\Entity\Rank;
use Praxigento\Bonus\Hybrid\Lib\Defaults as Def;
use Praxigento\Bonus\Hybrid\Lib\Entity\Cfg\Param as CfgParam;
use Praxigento\Downline\Data\Entity\Customer;

/**
 * TODO: move this tool to Repo section or extract DB related methods to standalone class.
 */
class Scheme
    extends \Praxigento\Core\Repo\Def\Base
    implements \Praxigento\Bonus\Hybrid\Lib\Tool\IScheme
{
    const A_RANK_ID = 'RankId';
    const A_SCHEME = 'Scheme';
    /**
     * There are 3 customer with forced qualifications and ranks in Santegra project.
     *
     * @var array [$mlmId=>[$schema, $rankCode], ...]
     */
    private $QUALIFIED_CUSTOMERS = [
        '770000001' => [Def::SCHEMA_DEFAULT, Def::RANK_PRESIDENT],
        '777163048' => [Def::SCHEMA_DEFAULT, Def::RANK_EXEC_DIRECTOR],
        '777017725' => [Def::SCHEMA_DEFAULT, Def::RANK_PRESIDENT],
        '790003045' => [Def::SCHEMA_EU, Def::RANK_MANAGER],
        '790003049' => [Def::SCHEMA_EU, Def::RANK_MANAGER]
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
    /** @var \Praxigento\Core\Repo\IGeneric */
    protected $_repoBasic;

    /**
     * Scheme constructor.
     */
    public function __construct(
        \Magento\Framework\App\ResourceConnection $resource,
        \Praxigento\Core\Repo\IGeneric $repoGeneric
    ) {
        parent::__construct($resource);
        $this->_repoBasic = $repoGeneric;
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
        $tblParams = $this->_conn->getTableName(CfgParam::ENTITY_NAME);
        $tblRank = $this->_conn->getTableName(Rank::ENTITY_NAME);
        // FROM prxgt_bon_hyb_cfg_param pbhcp
        $query = $this->_conn->select();
        $query->from([$asParams => $tblParams]);
        // LEFT JOIN prxgt_bon_hyb_rank pbhr ON pbhcp.rank_id = pbhr.id
        $on = "$asParams." . CfgParam::ATTR_RANK_ID . "=$asRank." . Rank::ATTR_ID;
        $cols = [Rank::ATTR_CODE];
        $query->joinLeft([$asRank => $tblRank], $on, $cols);
        // $sql = (string)$query;
        $entries = $this->_conn->fetchAll($query);
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
     * @return array [[Customer::ATTR_CUSTOMER_ID=>..., Customer::ATTR_HUMAN_REF=>...], ...]
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
            $quoted = $this->_conn->quote($one);
            $where .= Customer::ATTR_HUMAN_REF . "=\"$quoted\"";
        }
        $cols = [Customer::ATTR_CUSTOMER_ID, Customer::ATTR_HUMAN_REF];
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
                $ref = $item[Customer::ATTR_HUMAN_REF];
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
            Def::SCHEMA_DEFAULT => Def::PV_QUALIFICATION_LEVEL_DEF,
            Def::SCHEMA_EU => Def::PV_QUALIFICATION_LEVEL_EU
        ];
        return $result;
    }

    public function getSchemeByCustomer($data)
    {
        $result = Def::SCHEMA_DEFAULT;
        $countryCode = $data[Customer::ATTR_COUNTRY_CODE];
        $code = strtoupper($countryCode);
        if (
            ($code == 'AT') ||
            ($code == 'DE') ||
            ($code == 'ES')
        ) {
            $result = Def::SCHEMA_EU;
        }
        return $result;
    }
}