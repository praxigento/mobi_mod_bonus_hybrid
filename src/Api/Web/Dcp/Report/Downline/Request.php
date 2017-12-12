<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Web\Dcp\Report\Downline;

/**
 * Request to get data for DCP Downline report.
 *
 * (Define getters explicitly to use with Swagger tool)
 * (Define setters explicitly to use with Magento JSON2PHP conversion tool)
 *
 */
class Request
    extends \Praxigento\Core\App\Api\Web\RequestCond
{
    /**
     * @return \Praxigento\BonusHybrid\Api\Web\Dcp\Report\Downline\Request\Data
     */
    public function getData() {
        return parent::getData();
    }

    /**
     * @param \Praxigento\BonusHybrid\Api\Web\Dcp\Report\Downline\Request\Data $data
     */
    public function setData($data) {
        parent::setData($data);
    }


}