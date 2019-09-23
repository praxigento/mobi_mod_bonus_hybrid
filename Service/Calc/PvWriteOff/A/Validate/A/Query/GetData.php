<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 * Since: 2019
 */

namespace Praxigento\BonusHybrid\Service\Calc\PvWriteOff\A\Validate\A\Query;

use Praxigento\Accounting\Repo\Data\Account as EAcc;
use Praxigento\BonusHybrid\Config as Cfg;

/**
 * Query to get data to validate customer groups (only distributors can have PV).
 */
class GetData
    extends \Praxigento\Core\App\Repo\Query\Builder
{
    /** Tables aliases for external usage ('camelCase' naming) */
    const AS_ACC = 'a';
    const AS_CUST = 'c';

    /** Columns/expressions aliases for external usage ('underscore' naming for database fields; 'camelCase' naming for aliases) */
    const A_ACC_ID = 'accountId';
    const A_GROUP_ID = 'groupId';

    public function build(\Magento\Framework\DB\Select $source = null)
    {
        $result = $this->conn->select();

        /* define tables aliases for internal usage (in this method) */
        $asAcc = self::AS_ACC;
        $asCust = self::AS_CUST;

        /* FROM prxgt_acc_account  */
        $tbl = $this->resource->getTableName(EAcc::ENTITY_NAME);
        $as = $asAcc;
        $cols = [
            self::A_ACC_ID => EAcc::A_ID
        ];
        $result->from([$as => $tbl], $cols);

        /* LEFT JOIN customer_entity */
        $tbl = $this->resource->getTableName(Cfg::ENTITY_MAGE_CUSTOMER);
        $as = $asCust;
        $cols = [
            self::A_GROUP_ID => Cfg::E_CUSTOMER_A_GROUP_ID
        ];
        $cond = $as . '.' . Cfg::E_CUSTOMER_A_ENTITY_ID . '=' . $asAcc . '.' . EAcc::A_CUST_ID;
        $result->joinLeft([$as => $tbl], $cond, $cols);

        return $result;
    }

}