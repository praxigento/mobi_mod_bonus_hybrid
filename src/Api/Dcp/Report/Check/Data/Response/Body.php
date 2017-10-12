<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data\Response;

class Body
    extends \Praxigento\Core\Data
{
    /**
     * @return string
     */
    public function getPeriod(): string
    {
        $result = parent::getPeriod();
        return $result;
    }

    public function setPeriod(string $data)
    {
        parent::setPeriod($data);
    }
}