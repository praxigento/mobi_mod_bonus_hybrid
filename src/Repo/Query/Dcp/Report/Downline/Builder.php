<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Repo\Query\Dcp\Report\Downline;

use Praxigento\BonusBase\Repo\Entity\Data\Rank as ERank;
use Praxigento\BonusHybrid\Api\Web\Dcp\Report\Downline\Response\Entry as DReportEntry;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Entity\Data\Downline as EBonDwnl;
use Praxigento\Downline\Repo\Entity\Data\Customer as EDwnlCust;

/**
 * Build query to get DCP Downline Report data.
 */
class Builder
    extends \Praxigento\Core\App\Repo\Query\Def\Builder
{
    /** Tables aliases */
    const AS_BONUS_DWNL = 'bdwnl';
    const AS_DWNL_CUSTOMER = 'dcust';
    const AS_MAGE_CUSTOMER = 'mcust';
    const AS_RANK = 'rank';

    /** Columns aliases */
    const A_COUNTRY = DReportEntry::A_COUNTRY;
    const A_CUSTOMER_REF = DReportEntry::A_CUSTOMER_REF;
    const A_DEPTH = DReportEntry::A_DEPTH;
    const A_EMAIL = DReportEntry::A_EMAIL;
    const A_MLM_ID = DReportEntry::A_MLM_ID;
    const A_NAME_FIRST = DReportEntry::A_NAME_FIRST;
    const A_NAME_LAST = DReportEntry::A_NAME_LAST;
    const A_OV = DReportEntry::A_OV;
    const A_PARENT_REF = DReportEntry::A_PARENT_REF;
    const A_PATH = DReportEntry::A_PATH;
    const A_PV = DReportEntry::A_PV;
    const A_RANK_CODE = DReportEntry::A_RANK_CODE;
    const A_TV = DReportEntry::A_TV;
    const A_UNQ_MONTHS = DReportEntry::A_UNQ_MONTHS;

    /** Bound variables names ('camelCase' naming) */
    const BND_CALC_ID = 'calcId';
    const BND_CUST_ID = 'custId';
    const BND_PATH = 'path';

    public function build(\Magento\Framework\DB\Select $source = null)
    {
        $result = $this->conn->select();
        /* define tables aliases for internal usage (in this method) */
        $asBonDwnl = self::AS_BONUS_DWNL;
        $asDwnlCust = self::AS_DWNL_CUSTOMER;
        $asMageCust = self::AS_MAGE_CUSTOMER;
        $asRank = self::AS_RANK;

        /* FROM prxgt_bon_hyb_dwnl */
        $tbl = $this->resource->getTableName(EBonDwnl::ENTITY_NAME);
        $as = $asBonDwnl;
        $cols = [
            self::A_CUSTOMER_REF => EBonDwnl::ATTR_CUST_REF,
            self::A_DEPTH => EBonDwnl::ATTR_DEPTH,
            self::A_OV => EBonDwnl::ATTR_OV,
            self::A_PARENT_REF => EBonDwnl::ATTR_PARENT_REF,
            self::A_PATH => EBonDwnl::ATTR_PATH,
            self::A_PV => EBonDwnl::ATTR_PV,
            self::A_TV => EBonDwnl::ATTR_TV,
            self::A_UNQ_MONTHS => EBonDwnl::ATTR_UNQ_MONTHS
        ];
        $result->from([$as => $tbl], $cols);

        /* LEFT JOIN prxgt_dwnl_customer */
        $tbl = $this->resource->getTableName(EDwnlCust::ENTITY_NAME);
        $as = $asDwnlCust;
        $cols = [
            self::A_COUNTRY => EDwnlCust::ATTR_COUNTRY_CODE,
            self::A_MLM_ID => EDwnlCust::ATTR_MLM_ID
        ];
        $cond = $as . '.' . EDwnlCust::ATTR_CUSTOMER_ID . '=' . $asBonDwnl . '.' . EBonDwnl::ATTR_CUST_REF;
        $result->joinLeft([$as => $tbl], $cond, $cols);

        /* LEFT JOIN customer_entity */
        $tbl = $this->resource->getTableName(Cfg::ENTITY_MAGE_CUSTOMER);
        $as = $asMageCust;
        $cols = [
            self::A_EMAIL => Cfg::E_CUSTOMER_A_EMAIL,
            self::A_NAME_FIRST => Cfg::E_CUSTOMER_A_FIRSTNAME,
            self::A_NAME_LAST => Cfg::E_CUSTOMER_A_LASTNAME
        ];
        $cond = $as . '.' . Cfg::E_CUSTOMER_A_ENTITY_ID . '=' . $asBonDwnl . '.' . EBonDwnl::ATTR_CUST_REF;
        $result->joinLeft([$as => $tbl], $cond, $cols);

        /* LEFT JOIN prxgt_bon_base_rank */
        $tbl = $this->resource->getTableName(ERank::ENTITY_NAME);
        $as = $asRank;
        $cols = [
            self::A_RANK_CODE => ERank::ATTR_CODE
        ];
        $cond = $as . '.' . ERank::ATTR_ID . '=' . $asBonDwnl . '.' . EBonDwnl::ATTR_RANK_REF;
        $result->joinLeft([$as => $tbl], $cond, $cols);

        /* query tuning */
        $byCalcId = $asBonDwnl . '.' . EBonDwnl::ATTR_CALC_REF . '=:' . self::BND_CALC_ID;
        $byPath = $asBonDwnl . '.' . EBonDwnl::ATTR_PATH . ' LIKE :' . self::BND_PATH;
        $byCustId = $asBonDwnl . '.' . EBonDwnl::ATTR_CUST_REF . '=:' . self::BND_CUST_ID;
        $result->where("$byCalcId AND ($byPath OR $byCustId)");

        return $result;
    }


}