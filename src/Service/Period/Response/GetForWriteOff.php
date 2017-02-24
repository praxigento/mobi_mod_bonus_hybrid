<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Service\Period\Response;

/**
 * @method \Praxigento\BonusBase\Data\Entity\Period getPeriodData()
 * @method void setPeriodData(\Praxigento\BonusBase\Data\Entity\Period | array $data)
 * @method \Praxigento\BonusBase\Data\Entity\Calculation getCalcData()
 * @method void setCalcData(\Praxigento\BonusBase\Data\Entity\Calculation | array $data)
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