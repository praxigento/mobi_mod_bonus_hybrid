<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Forecast;

interface IPlain
    extends \Praxigento\Core\Service\IProcess
{
    /** string 'YYYY', 'YYYYMM' or 'YYYYMMDD' */
    const CTX_IN_PERIOD = 'in.period';
}