<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Compress;

/**
 * Phase II compression calculation.
 */
interface IPhase2
    extends \Praxigento\Core\Service\IProcess
{
    /** Calculation scheme (DEFAULT or EU) */
    const CTX_IN_SCHEME = 'in.scheme';
}