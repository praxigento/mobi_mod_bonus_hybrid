<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data\Response\Body\Sections;


class QualificationLegs
    extends \Praxigento\Core\Data
{
    const A_ITEMS = 'items';

    /**
     * @return \Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data\Response\Body\Sections\QualificationLegs\Item[]
     */
    public function getItems()
    {
        $result = parent::get(self::A_ITEMS);
        return $result;
    }

    public function setItems($data)
    {
        parent::set(self::A_ITEMS, $data);
    }

}