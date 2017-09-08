<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Bonus;

/**
 * Calculate Override Bonus.
 */
interface IOverride
    extends \Praxigento\Core\Service\IProcess
{
    /** Calculation scheme (DEFAULT or EU) */
    const CTX_IN_SCHEME = 'in.scheme';
}