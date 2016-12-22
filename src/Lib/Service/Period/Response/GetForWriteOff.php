<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\Bonus\Hybrid\Lib\Service\Period\Response;

/**
 * @method array getPeriodData()
 * @method void setPeriodData(array $data)
 * @method array getCalcData()
 * @method void setCalcData(array $data)
 */
class GetForWriteOff extends \Praxigento\Core\Service\Base\Response {
    const HAS_NO_PV_TRANSACTIONS_YET = 'has_no_pv_transactions_yet';

    /**
     * @return bool
     */
    public function hasNoPvTransactionsYet() {
        $result = (bool)$this->get(self::HAS_NO_PV_TRANSACTIONS_YET);
        return $result;
    }

    public function setHasNoPvTransactionsYet() {
        $this->set(self::HAS_NO_PV_TRANSACTIONS_YET, true);
        $this->markSucceed();
    }
}