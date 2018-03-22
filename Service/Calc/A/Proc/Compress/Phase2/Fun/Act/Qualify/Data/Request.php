<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\A\Proc\Compress\Phase2\Fun\Act\Qualify\Data;

/**
 * Input data for customer qualification.
 */
class Request
    extends \Praxigento\Core\Data
{
    const CFG_PARAMS = 'cfgParams';
    const DOWNLINE_ENTRY = 'downlineEntry';
    const LEGS_ENTRY = 'legsEntry';
    const SCHEME = 'scheme';

    /**
     * @return array see \Praxigento\BonusHybrid\Service\Calc\Compress\Phase2\Calc::getCfgParams
     */
    public function getCfgParams()
    {
        $result = parent::get(self::CFG_PARAMS);
        return $result;
    }

    /**
     * @return \Praxigento\BonusHybrid\Repo\Data\Downline
     */
    public function getDownlineEntry()
    {
        $result = parent::get(self::DOWNLINE_ENTRY);
        return $result;
    }

    /**
     * @return \Praxigento\BonusHybrid\Repo\Data\Compression\Phase2\Legs
     */
    public function getLegsEntry()
    {
        $result = parent::get(self::LEGS_ENTRY);
        return $result;
    }

    /**
     * @return string
     */
    public function getScheme()
    {
        $result = parent::get(self::SCHEME);
        return $result;
    }

    /**
     * @param array $data see \Praxigento\BonusHybrid\Service\Calc\Compress\Phase2\Calc::getCfgParams
     */
    public function setCfgParams($data)
    {
        parent::set(self::CFG_PARAMS, $data);
    }

    public function setDownlineEntry(\Praxigento\BonusHybrid\Repo\Data\Downline $data)
    {
        parent::set(self::DOWNLINE_ENTRY, $data);
    }

    public function setLegsEntry(\Praxigento\BonusHybrid\Repo\Data\Compression\Phase2\Legs $data)
    {
        parent::set(self::LEGS_ENTRY, $data);
    }

    /**
     * @param string $data
     */
    public function setScheme($data)
    {
        parent::set(self::SCHEME, $data);
    }
}