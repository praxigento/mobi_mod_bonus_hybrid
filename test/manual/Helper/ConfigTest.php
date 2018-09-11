<?php
/**
 * Authors: Alex Gusev <alex@flancer64.com>
 * Since: 2018
 */

namespace Test\raxigento\BonusHybrid\Helper;

include_once(__DIR__ . '/../phpunit_bootstrap.php');

class ConfigTest
    extends \Praxigento\Core\Test\BaseCase\Manual
{


    public function test_all()
    {
        /** @var  $obj \Praxigento\BonusHybrid\Helper\Config */
        $obj = $this->manObj->get(\Praxigento\BonusHybrid\Helper\Config::class);
        $res = $obj->getDowngradeGroupUnqual();
        $this->assertTrue(is_int($res));
    }

}