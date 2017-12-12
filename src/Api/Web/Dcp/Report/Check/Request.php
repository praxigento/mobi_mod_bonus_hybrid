<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Web\Dcp\Report\Check;

/**
 * Request to get data for DCP Check report.
 *
 * (Define getters explicitly to use with Swagger tool)
 * (Define setters explicitly to use with Magento JSON2PHP conversion tool)
 *
 */
class Request
    extends \Praxigento\Core\App\Api\Web\Request
{
    /**
     * @return \Praxigento\BonusHybrid\Api\Web\Dcp\Report\Check\Request\Data
     */
    public function getData() {
        return parent::getData();
    }

    /**
     * @param \Praxigento\BonusHybrid\Api\Web\Dcp\Report\Check\Request\Data $data
     */
    public function setData($data) {
        parent::setData($data);
    }
}