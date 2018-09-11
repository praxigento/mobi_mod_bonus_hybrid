<?php
/**
 * Authors: Alex Gusev <alex@flancer64.com>
 * Since: 2018
 */

namespace Praxigento\BonusHybrid\Helper;

/**
 * Helper to get configuration parameters related to the module.
 */
class Config
{

    /** @var \Magento\Framework\App\Config\ScopeConfigInterface */
    private $scopeConfig;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    public function getDowngradeGroupUnqual()
    {
        $result = $this->scopeConfig->getValue('praxigento_downline/downgrade/group_unqual');
        $result = filter_var($result, FILTER_VALIDATE_INT);
        return $result;
    }
}