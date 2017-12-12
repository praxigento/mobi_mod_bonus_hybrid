<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Web\Dcp\Report\Accounting\Response\Data;

/**
 * Accessors use 'CamelCase' naming strategy (data object default), but data inside use 'snake_case' naming strategy
 * (API default). Repo queries should use 'snake_case' namings to prepare array data, DataObject will return
 * 'snake_case' property if 'CamelCase' will not be found.
 *
 * @method string getAsset()
 * @method void setAsset(string $data)
 * @method float getValue()
 * @method void setValue(float $data)
 */
class Balance
    extends \Praxigento\Core\Data
{
    const A_ASSET = 'asset';
    const A_VALUE = 'value';

}