<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Api\Stats\Phase2;

/**
 * Response to get bonus related statistics (PV/TV/OV) for phase 1 compressed tree.
 *
 * (Define getters explicitly to use with Swagger tool)
 *
 */
class Response
    extends \Praxigento\Core\Api\Response
{
    /**
     * @return \Praxigento\BonusHybrid\Api\Stats\Phase2\Response\Entry[]
     */
    public function getData()
    {
        $result = parent::get(self::ATTR_DATA);
        return $result;
    }

    /**
     * @param \Praxigento\BonusHybrid\Api\Stats\Phase2\Response\Entry[] $data
     */
    public function setData($data)
    {
        parent::set(self::ATTR_DATA, $data);
    }

}