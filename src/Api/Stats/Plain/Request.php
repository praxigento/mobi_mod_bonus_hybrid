<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Api\Stats\Plain;

/**
 * Request to get bonus related statistics (PV/TV/OV) for plain (not compressed) tree.
 *
 * (Define getters explicitly to use with Swagger tool)
 * (Define setters explicitly to use with Magento JSON2PHP conversion tool)
 *
 */
class Request
    extends \Flancer32\Lib\Data
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