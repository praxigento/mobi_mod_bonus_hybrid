<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Dcp\Report\Accounting;

/**
 * Response to get data for DCP Accounting report.
 *
 * (Define getters explicitly to use with Swagger tool)
 *
 */
class Response
    extends \Praxigento\Core\Api\Response
{
    /**
     * @return \Praxigento\BonusHybrid\Api\Dcp\Report\Accounting\Response\Entry[]
     */
    public function getData()
    {
        $result = parent::get(self::ATTR_DATA);
        return $result;
    }

    /**
     * @param \Praxigento\BonusHybrid\Api\Dcp\Report\Accounting\Response\Entry[] $data
     */
    public function setData($data)
    {
        parent::set(self::ATTR_DATA, $data);
    }

}