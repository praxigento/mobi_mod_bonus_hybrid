<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Dcp\Report\Accounting\Response\Data;

/**
 * Accessors use 'CamelCase' naming strategy (data object default), but data inside use 'snake_case' naming strategy
 * (API default). Repo queries should use 'snake_case' namings to prepare array data, DataObject will return
 * 'snake_case' property if 'CamelCase' will not be found.
 *
 * @method string getAsset()
 * @method void setAsset(string $data)
 * @method int getCustomerId()
 * @method void setCustomerId(int $data)
 * @method string getDate()
 * @method void setDate(string $data)
 * @method string getDetails()
 * @method void setDetails(string $data)
 * @method int getItemId()
 * @method void setItemId(int $data)
 * @method string getType()
 * @method void setType(string $data)
 * @method float getValue()
 * @method void setValue(float $data)
 */
class Trans
    extends \Praxigento\Core\Data
{
    const A_ASSET = 'asset';
    const A_CUSTOMER_ID = 'customer_id';
    const A_DATE = 'date';
    const A_DETAILS = 'details';
    const A_ITEM_ID = 'item_id';
    const A_TYPE = 'type';
    const A_VALUE = 'value';

}