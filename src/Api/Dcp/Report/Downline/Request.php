<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Dcp\Report\Downline;

/**
 * Request to get data for DCP Downline report.
 *
 * (Define getters explicitly to use with Swagger tool)
 * (Define setters explicitly to use with Magento JSON2PHP conversion tool)
 *
 */
class Request
    extends \Praxigento\Core\App\Web\Request\WithCond
{

    /**
     * End of the calculation period.
     *
     * @return string|null 'YYYY', 'YYYYMM', 'YYYYMMDD'
     */
    public function getPeriod()
    {
        $result = parent::getPeriod();
        return $result;
    }

    /**
     * Root Customer ID for development purposes.
     *
     * @return int|null
     */
    public function getRootCustId()
    {
        $result = parent::getRootCustId();
        return $result;
    }

    /**
     * Type of the requested report (complete|compressed).
     *
     * @return string|null
     */
    public function getType()
    {
        $result = parent::getType();
        return $result;
    }

    /**
     * End of the calculation period.
     *
     * @param string $data 'YYYY', 'YYYYMM', 'YYYYMMDD'
     */
    public function setPeriod($data)
    {
        parent::setPeriod($data);
    }

    /**
     * Root Customer ID for development purposes.
     *
     * @param int $data
     */
    public function setRootCustId($data)
    {
        parent::setRootCustId($data);
    }

    /**
     * Type of the requested report (complete|compressed).
     *
     * @param string $data
     */
    public function setType($data)
    {
        parent::setType($data);
    }


}