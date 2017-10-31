<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Setup;


include_once(__DIR__ . '/../phpunit_bootstrap.php');

class InstallSchema_Test
    extends \Praxigento\Core\Test\BaseCase\Manual
{


    public function test_exec()
    {
        /** @var  $obj \Praxigento\BonusHybrid\Setup\InstallSchema */
        $obj = $this->manObj->get(\Praxigento\BonusHybrid\Setup\InstallSchema::class);
        $setup = $this->manObj->get(\Magento\Setup\Module\Setup::class);
        $context = $this->manObj->create(\Magento\Setup\Model\ModuleContext::class, ['version' => '0.1.0']);
        $obj->install($setup, $context);
        $this->assertTrue(true);
    }

}