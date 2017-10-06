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
 * @method int getId()
 * @method void setId(int $data)
 * @method string getMlmId()
 * @method void setMlmId(string $data)
 * @method string getNameFirst()
 * @method void setNameFirst(string $data)
 * @method string getNameLast()
 * @method void setNameLast(string $data)
 */
class Customer
    extends \Praxigento\Core\Data
{
    const A_ID = 'id';
    const A_MLM_ID = 'mlm_id';
    const A_NAME_FIRST = 'name_first';
    const A_NAME_LAST = 'name_last';

}