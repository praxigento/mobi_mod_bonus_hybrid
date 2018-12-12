<?php
/**
 * Authors: Alex Gusev <alex@flancer64.com>
 * Since: 2018
 */

namespace Praxigento\BonusHybrid\Service\Calc\Forecast\Unqualified;

/**
 * @method string getPeriod() YYYYMM
 * @method void setPeriod(string $data) YYYYMM
 */
class Request
    extends \Praxigento\Core\App\Service\Request
{
    const PERIOD = 'period';
}
