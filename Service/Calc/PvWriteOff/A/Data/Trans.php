<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\PvWriteOff\A\Data;

/**
 * Data object to collect & process PV transaction related data.
 */
class Trans
    extends \Praxigento\Core\Data
{
    const A_ACC_ID_CREDIT = 'accIdCredit';
    const A_ACC_ID_DEBIT = 'accIdDebit';
    const A_AMOUNT = 'amount';
    const A_OPER_ID = 'operId';
}