<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Api\Stats\Phase1;

/**
 * Request to get bonus related statistics (PV/TV/OV) for phase 1 compressed tree.
 *
 * (Define getters explicitly to use with Swagger tool)
 * (Define setters explicitly to use with Magento JSON2PHP conversion tool)
 *
 */
class Request
    extends \Flancer32\Lib\Data
{
    /**
     * Limit tree depth starting from root customer level.
     *
     * @return int|null
     */
    public function getMaxDepth()
    {
        $result = parent::getMaxDepth();
        return $result;
    }

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
     * Limit tree depth starting from root customer level.
     *
     * @param int $data
     */
    public function setMaxDepth($data)
    {
        parent::setMaxDepth($data);
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

}