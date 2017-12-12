<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Web\Dcp\Report\Downline\Request;
/**
 * Request data to get DCP Downline report.
 */
class Data
    extends \Praxigento\Core\Data
{
    /** End of the calculation period. */
    const PERIOD = 'period';

    /** Type of the requested report (complete|compressed). */
    const TYPE = 'type';

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
     * Type of the requested report (complete|compressed).
     *
     * @return string|null
     */
    public function getType() {
        $result = parent::get(self::TYPE);
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

    /**
     * Type of the requested report (complete|compressed).
     *
     * @param string $data
     */
    public function setType($data) {
        parent::set(self::TYPE, $data);
    }

}