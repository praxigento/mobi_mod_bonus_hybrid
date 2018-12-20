<?php
/**
 * Authors: Alex Gusev <alex@flancer64.com>
 * Since: 2018
 */

namespace Praxigento\BonusHybrid\Ui\DataProvider\Options;

use Praxigento\BonusBase\Repo\Data\Period as EPeriod;

class Period
    implements \Magento\Framework\Data\OptionSourceInterface
{
    /** @var \Praxigento\BonusBase\Repo\Dao\Period */
    private $daoPeriod;
    /** @var array */
    private $options;

    public function __construct(
        \Praxigento\BonusBase\Repo\Dao\Period $daoPeriod
    ) {
        $this->daoPeriod = $daoPeriod;
    }

    /**
     * @return EPeriod[]
     */
    private function loadPeriods()
    {
        $order = EPeriod::A_DSTAMP_END . ' DESC';
        $group = EPeriod::A_DSTAMP_END;
        $rs = $this->daoPeriod->get(null, $order, null, null, null, $group);
        return $rs;
    }

    public function toOptionArray()
    {
        if ($this->options === null) {
            $this->options = [];
            $items = $this->loadPeriods();
            foreach ($items as $item) {
                $dsEnd = $item->getDstampEnd();
                $period = substr($dsEnd, 0, 6);
                $option = ["label" => $period, "value" => $period];
                $this->options[] = $option;
            }
        }
        return $this->options;
    }
}
