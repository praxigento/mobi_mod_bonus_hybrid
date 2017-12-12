<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Web\Dcp\Report\Accounting\Request;
/**
 * Request data to get DCP Downline report.
 */
class Data
    extends \Praxigento\Core\Data
{
    /** End of the calculation period. */
    const PERIOD = 'period';

    /**
     * End of the calculation period.
     *
     * @return string|null 'YYYY', 'YYYYMM', 'YYYYMMDD'
     */
    public function getPeriod() {
        $result = parent::get(self::PERIOD);
        return $result;
    }

    /**
     * End of the calculation period.
     *
     * @param string $data 'YYYY', 'YYYYMM', 'YYYYMMDD'
     */
    public function setPeriod($data) {
        parent::set(self::PERIOD, $data);
    }

}