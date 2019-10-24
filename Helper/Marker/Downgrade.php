<?php
/**
 * Authors: Alex Gusev <alex@flancer64.com>
 * Since: 2018
 */

namespace Praxigento\BonusHybrid\Helper\Marker;


/**
 * Set/get mark that downgrade process is running to prevent extra calcs on customer group changes.
 */
class Downgrade
{
    private const MARK = 'prxgtBonHybridDowngradeStarted';

    /** @var \Magento\Framework\Registry */
    private $registry;

    public function __construct(
        \Magento\Framework\Registry $registry
    ) {
        $this->registry = $registry;
    }

    public function cleanMark()
    {
        if ($this->registry->registry(self::MARK)) {
            $this->registry->unregister(self::MARK);
        }
    }

    public function getMark()
    {
        $result = (bool)$this->registry->registry(self::MARK);
        return $result;
    }

    public function setMark()
    {
        $this->cleanMark();
        $this->registry->register(self::MARK, true);
    }
}