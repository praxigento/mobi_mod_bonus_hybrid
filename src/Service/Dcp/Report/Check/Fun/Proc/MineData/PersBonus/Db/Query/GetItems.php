<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Dcp\Report\Check\Fun\Proc\MineData\PersBonus\Db\Query;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Entity\Data\Compression\Phase1\Transfer\Pv as EPhase1Transfer;
use Praxigento\BonusHybrid\Repo\Entity\Data\Downline as EBonDwnl;
use Praxigento\Downline\Repo\Entity\Data\Customer as EDwnCust;

class GetItems
    extends \Praxigento\Core\App\Repo\Query\Builder
{

    /** Tables aliases for external usage ('camelCase' naming) */
    const AS_BON_DWNL = 'bonDwnl';
    const AS_CUST = 'cust';
    const AS_CUST_DWNL = 'custDwnl';
    const AS_TRANSFER = 'transf';

    /** Columns/expressions aliases for external usage ('camelCase' naming) */
    const A_CUST_ID = 'custId';
    const A_DEPTH = 'depth';
    const A_MLM_ID = 'mlmId';
    const A_NAME_FIRST = 'nameFirst';
    const A_NAME_LAST = 'nameLast';
    const A_PV = 'pv';

    /** Bound variables names ('camelCase' naming) */
    const BND_CALC_ID_COMPRESS_PHASE1 = 'calcIdCompressPhase1';
    const BND_CALC_ID_PV_WRITE_OFF = 'calcIdPvWriteOff';
    const BND_CUST_ID = 'custId';

    /** Entities are used in the query */
    const E_BONUS_DWNL = EBonDwnl::ENTITY_NAME;
    const E_CUSTOMER = Cfg::ENTITY_MAGE_CUSTOMER;
    const E_DWNL_CUST = EDwnCust::ENTITY_NAME;
    const E_PHASE1_TRANSFER = EPhase1Transfer::ENTITY_NAME;

    public function build(\Magento\Framework\DB\Select $source = null)
    {
        /* this is root query builder (started from SELECT) */
        $result = $this->conn->select();

        /* define tables aliases for internal usage (in this method) */
        $asBonDwnl = self::AS_BON_DWNL;
        $asCust = self::AS_CUST;
        $asDwnlCust = self::AS_CUST_DWNL;
        $asTrn = self::AS_TRANSFER;

        /* FROM prxgt_bon_hyb_cmprs_ph1_trn_pv  */
        $tbl = $this->resource->getTableName(EPhase1Transfer::ENTITY_NAME);
        $as = $asTrn;
        $cols = [
            self::A_CUST_ID => EPhase1Transfer::ATTR_CUST_FROM_REF,
            self::A_PV => EPhase1Transfer::ATTR_PV
        ];
        $result->from([$as => $tbl], $cols);

        /* JOIN prxgt_bon_hyb_dwnl to get depth in retrospective */
        $tbl = $this->resource->getTableName(EBonDwnl::ENTITY_NAME);
        $as = $asBonDwnl;
        $cols = [
            self::A_DEPTH => EBonDwnl::ATTR_DEPTH
        ];
        $cond = $as . '.' . EBonDwnl::ATTR_CUST_REF . '=' . $asTrn . '.' . EPhase1Transfer::ATTR_CUST_FROM_REF;
        $cond .= ' AND ' . $as . '.' . EBonDwnl::ATTR_CALC_REF . '=:' . self::BND_CALC_ID_PV_WRITE_OFF;
        $result->joinLeft([$as => $tbl], $cond, $cols);

        /* JOIN prxgt_dwnl_customer to get MLM ID */
        $tbl = $this->resource->getTableName(EDwnCust::ENTITY_NAME);
        $as = $asDwnlCust;
        $cols = [
            self::A_MLM_ID => EDwnCust::ATTR_MLM_ID
        ];
        $cond = $as . '.' . EDwnCust::ATTR_CUSTOMER_ID . '=' . $asTrn . '.' . EPhase1Transfer::ATTR_CUST_FROM_REF;
        $result->joinLeft([$as => $tbl], $cond, $cols);

        /* JOIN customer_entity to get name */
        $tbl = $this->resource->getTableName(Cfg::ENTITY_MAGE_CUSTOMER);
        $as = $asCust;
        $cols = [
            self::A_NAME_FIRST => Cfg::E_CUSTOMER_A_FIRSTNAME,
            self::A_NAME_LAST => Cfg::E_CUSTOMER_A_LASTNAME
        ];
        $cond = $as . '.' . Cfg::E_CUSTOMER_A_ENTITY_ID . '=' . $asTrn . '.' . EPhase1Transfer::ATTR_CUST_FROM_REF;
        $result->joinLeft([$as => $tbl], $cond, $cols);

        /* query tuning */
        $byCalcCompress = "$asTrn." . EPhase1Transfer::ATTR_CALC_REF . '=:' . self::BND_CALC_ID_COMPRESS_PHASE1;
        $byCustTo = "$asTrn." . EPhase1Transfer::ATTR_CUST_TO_REF . '=:' . self::BND_CUST_ID;
        $result->where("($byCalcCompress) AND ($byCustTo)");

        return $result;
    }
}