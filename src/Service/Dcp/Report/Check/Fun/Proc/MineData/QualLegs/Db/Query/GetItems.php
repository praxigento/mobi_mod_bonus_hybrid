<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Dcp\Report\Check\Fun\Proc\MineData\QualLegs\Db\Query;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Entity\Data\Downline as EBonDwnl;
use Praxigento\Downline\Repo\Entity\Data\Customer as EDwnCust;

/**
 * Query to get legs data (rank qualification).
 */
class GetItems
    extends \Praxigento\Core\App\Repo\Query\Builder
{

    /** Tables aliases for external usage ('camelCase' naming) */
    const AS_BON_DWNL = 'bonDwnl';
    const AS_CUST = 'cust';
    const AS_DWNL_CUST = 'dwnCust';

    /** Columns/expressions aliases for external usage ('camelCase' naming) */
    const A_CUST_ID = 'custId';
    const A_DEPTH = 'depth';
    const A_MLM_ID = 'mlmId';
    const A_NAME_FIRST = 'nameFirst';
    const A_NAME_LAST = 'nameLast';
    const A_OV = 'ov';

    /** Bound variables names ('camelCase' naming) */
    const BND_CALC_ID = 'calcId';
    const BND_CUST_ID = 'custId';

    /** Entities are used in the query */
    const E_BON_DWNL = EBonDwnl::ENTITY_NAME;
    const E_CUSTOMER = Cfg::ENTITY_MAGE_CUSTOMER;
    const E_DWNL_CUST = EDwnCust::ENTITY_NAME;


    public function build(\Magento\Framework\DB\Select $source = null)
    {
        /* this is root query builder (started from SELECT) */
        $result = $this->conn->select();

        /* define tables aliases for internal usage (in this method) */
        $asBonDwnl = self::AS_BON_DWNL;
        $asCust = self::AS_CUST;
        $asDwnlCust = self::AS_DWNL_CUST;

        /* FROM prxgt_bon_hyb_dwnl */
        $tbl = $this->resource->getTableName(EBonDwnl::ENTITY_NAME);
        $as = $asBonDwnl;
        $cols = [
            self::A_CUST_ID => EBonDwnl::ATTR_CUST_REF,
            self::A_DEPTH => EBonDwnl::ATTR_DEPTH,
            self::A_OV => EBonDwnl::ATTR_OV
        ];
        $result->from([$as => $tbl], $cols);

        /* JOIN prxgt_dwnl_customer to get MLM IDs for donors */
        $tbl = $this->resource->getTableName(EDwnCust::ENTITY_NAME);
        $as = $asDwnlCust;
        $cols = [
            self::A_MLM_ID => EDwnCust::ATTR_MLM_ID
        ];
        $cond = $as . '.' . EDwnCust::ATTR_CUSTOMER_ID . '=' . $asBonDwnl . '.' . EBonDwnl::ATTR_CUST_REF;
        $result->joinLeft([$as => $tbl], $cond, $cols);

        /* JOIN customer_entity to get name */
        $tbl = $this->resource->getTableName(Cfg::ENTITY_MAGE_CUSTOMER);
        $as = $asCust;
        $cols = [
            self::A_NAME_FIRST => Cfg::E_CUSTOMER_A_FIRSTNAME,
            self::A_NAME_LAST => Cfg::E_CUSTOMER_A_LASTNAME
        ];
        $cond = $as . '.' . Cfg::E_CUSTOMER_A_ENTITY_ID . '=' . $asDwnlCust . '.' . EDwnCust::ATTR_CUSTOMER_ID;
        $result->joinLeft([$as => $tbl], $cond, $cols);

        /* query tuning */
        $byCalcId = "$asBonDwnl." . EBonDwnl::ATTR_CALC_REF . '=:' . self::BND_CALC_ID;
        $byCustId = "$asBonDwnl." . EBonDwnl::ATTR_PARENT_REF . '=:' . self::BND_CUST_ID;
        $result->where("($byCalcId) AND ($byCustId)");

        return $result;
    }
}