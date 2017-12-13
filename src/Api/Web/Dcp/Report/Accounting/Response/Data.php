<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Web\Dcp\Report\Accounting\Response;

/**
 * Accessors use 'CamelCase' naming strategy (data object default), but data inside use 'snake_case' naming strategy
 * (API default). Repo queries should use 'snake_case' namings to prepare array data, DataObject will return
 * 'snake_case' property if 'CamelCase' will not be found.
 *
 * @method \Praxigento\BonusHybrid\Api\Web\Dcp\Report\Accounting\Response\Data\Balance[] getBalanceClose()
 * @method void setBalanceClose(\Praxigento\BonusHybrid\Api\Web\Dcp\Report\Accounting\Response\Data\Balance[] $data)
 * @method \Praxigento\BonusHybrid\Api\Web\Dcp\Report\Accounting\Response\Data\Balance[] getBalanceOpen()
 * @method void setBalanceOpen(\Praxigento\BonusHybrid\Api\Web\Dcp\Report\Accounting\Response\Data\Balance[] $data)
 * @method \Praxigento\BonusHybrid\Api\Web\Dcp\Report\Accounting\Response\Data\Trans[] getTrans()
 * @method void setTrans(\Praxigento\BonusHybrid\Api\Web\Dcp\Report\Accounting\Response\Data\Trans[] $data)
 * * @method \Praxigento\BonusHybrid\Api\Web\Dcp\Report\Accounting\Response\Data\Customer getCustomer()
 * @method void setCustomer(\Praxigento\BonusHybrid\Api\Web\Dcp\Report\Accounting\Response\Data\Customer $data)
 */
class Data
    extends \Praxigento\Core\Data
{
    const A_BAL_CLOSE = 'balance_close';
    const A_BAL_OPEN = 'balance_open';
    const A_CUSTOMER = 'customer';
    const A_TRANS = 'trans';

}