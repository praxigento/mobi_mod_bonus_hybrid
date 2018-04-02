<?php
/**
 * Authors: Alex Gusev <alex@flancer64.com>
 * Since: 2018
 */

namespace Praxigento\BonusHybrid\Service\Calc\Aggregate\A\Data;


/**
 * Data object to accumulate bonus total aggregation.
 */
class Total
{
    /** @var int BONUS account ID */
    public $accountId;
    /** @var int customer ID (internal) */
    public $customerId;
    /** @var @float total amount of bonus aggregated per customer */
    public $total;
}