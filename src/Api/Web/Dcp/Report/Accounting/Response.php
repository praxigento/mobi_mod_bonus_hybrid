<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Web\Dcp\Report\Accounting;

/**
 * Response to get data for DCP Accounting report.
 *
 * (Define getters explicitly to use with Swagger tool)
 *
 */
class Response
    extends \Praxigento\Core\App\Api\Web\Response
{
    /**
     * @return \Praxigento\BonusHybrid\Api\Web\Dcp\Report\Accounting\Response\Data
     */
    public function getData()
    {
        $result = parent::get(self::ATTR_DATA);
        return $result;
    }

    /**
     * @param \Praxigento\BonusHybrid\Api\Web\Dcp\Report\Accounting\Response\Data $data
     */
    public function setData($data)
    {
        parent::set(self::ATTR_DATA, $data);
    }

}