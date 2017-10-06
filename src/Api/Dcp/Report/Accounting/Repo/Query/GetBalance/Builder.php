<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Dcp\Report\Accounting\Repo\Query\GetBalance;

use Praxigento\Accounting\Repo\Entity\Data\Account as EAccount;
use Praxigento\Accounting\Repo\Entity\Data\Type\Asset as ETypeAsset;

class Builder
    extends \Praxigento\Accounting\Repo\Query\Balance\OnDate\Closing\Builder
{
    /** Tables aliases for external usage ('camelCase' naming) */
    const AS_ASSET_TYPE = 'assType';

    /** Columns/expressions aliases for external usage ('camelCase' naming) */
    const A_ASSET = 'asset';

    /** Bound variables names ('camelCase' naming) */
    const BND_CUST_ID = 'custId'; // to get asset balances

    public function build(\Magento\Framework\DB\Select $source = null)
    {
        $result = parent::build();
        /* define tables aliases for internal usage (in this method) */
        $asType = self::AS_ASSET_TYPE;
        $asAcc = self::AS_ACC;

        /* JOIN prxgt_acc_type_asset */
        $tbl = $this->resource->getTableName(ETypeAsset::ENTITY_NAME);
        $as = $asType;
        $cols = [
            self::A_ASSET => ETypeAsset::ATTR_CODE
        ];
        $cond = $as . '.' . ETypeAsset::ATTR_ID . '=' . $asAcc . '.' . EAccount::ATTR_ASSET_TYPE_ID;
        $result->joinLeft([$as => $tbl], $cond, $cols);

        /* add filter by customer */
        $where = $asAcc . '.' . EAccount::ATTR_CUST_ID . '=:' . self::BND_CUST_ID;
        $result->where($where);

        return $result;
    }
}