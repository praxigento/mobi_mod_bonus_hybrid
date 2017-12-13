<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Web\Dcp\Report\Check;

/**
 * Response to get data for DCP Check report.
 *
 * (Define getters explicitly to use with Swagger tool)
 *
 */
class Response
    extends \Praxigento\Core\App\Api\Web\Response
{
    /**
     * @return \Praxigento\BonusHybrid\Api\Web\Dcp\Report\Check\Response\Body
     */
    public function getData()
    {
        $result = parent::get(self::ATTR_DATA);
        return $result;
    }

    /**
     * @param \Praxigento\BonusHybrid\Api\Web\Dcp\Report\Check\Response\Body $data
     */
    public function setData($data)
    {
        parent::set(self::ATTR_DATA, $data);
    }

}