<?php
/**
 * Authors: Alex Gusev <alex@flancer64.com>
 * Since: 2020
 */

namespace Praxigento\BonusHybrid\Setup\UpgradeSchema\A;

use \Praxigento\BonusHybrid\Repo\Data\Registry\Downgrade as Downgrade;

/**
 * Add customers downgrade registry.
 */
class V0_1_1
{
    /** @var \Praxigento\Core\App\Setup\Dem\Tool */
    private $toolDem;

    public function __construct(
        \Praxigento\Core\App\Setup\Dem\Tool $toolDem

    ) {
        $this->toolDem = $toolDem;
    }

    /**
     * @param \Magento\Framework\Setup\SchemaSetupInterface $setup
     * @param \Praxigento\Core\Data $demPackage
     */
    public function exec($setup, $demPackage = null)
    {
        $demEntity = $demPackage->get('package/Registry/entity/Downgrade');
        $this->toolDem->createEntity(Downgrade::ENTITY_NAME, $demEntity);
    }
}