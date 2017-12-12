<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Web\Dcp\Report\Check\Data\Response\Body\Sections\OrgProfile;


class Item
    extends \Praxigento\Core\Data
{

    const A_ACTIVE = 'active';
    const A_GENERATION = 'generation';
    const A_INACTIVE = 'inactive';
    const A_MGR = 'mgr';
    const A_MGR_AVG = 'mgr_avg';
    const A_QUAL = 'qual';
    const A_TOTAL = 'total';
    const A_VOLUME = 'volume';

    /**
     * @return int
     */
    public function getActive()
    {
        $result = parent::get(self::A_ACTIVE);
        return $result;
    }

    /**
     * @return int
     */
    public function getGeneration()
    {
        $result = parent::get(self::A_GENERATION);
        return $result;
    }

    /**
     * @return int
     */
    public function getInactive()
    {
        $result = parent::get(self::A_INACTIVE);
        return $result;
    }

    /**
     * @return int
     */
    public function getMgr()
    {
        $result = parent::get(self::A_MGR);
        return $result;
    }

    /**
     * @return float
     */
    public function getMgrAvg()
    {
        $result = parent::get(self::A_MGR_AVG);
        return $result;
    }

    /**
     * @return int
     */
    public function getQual()
    {
        $result = parent::get(self::A_QUAL);
        return $result;
    }

    /**
     * @return int
     */
    public function getTotal()
    {
        $result = parent::get(self::A_TOTAL);
        return $result;
    }

    /**
     * @return float
     */
    public function getVolume()
    {
        $result = parent::get(self::A_VOLUME);
        return $result;
    }

    public function setActive($data)
    {
        parent::set(self::A_ACTIVE, $data);
    }

    public function setGeneration($data)
    {
        parent::set(self::A_GENERATION, $data);
    }

    public function setInactive($data)
    {
        parent::set(self::A_INACTIVE, $data);
    }

    public function setMgr($data)
    {
        parent::set(self::A_MGR, $data);
    }

    public function setMgrAvg($data)
    {
        parent::set(self::A_MGR_AVG, $data);
    }

    public function setQual($data)
    {
        parent::set(self::A_QUAL, $data);
    }

    public function setTotal($data)
    {
        parent::set(self::A_TOTAL, $data);
    }

    public function setVolume($data)
    {
        parent::set(self::A_VOLUME, $data);
    }
}