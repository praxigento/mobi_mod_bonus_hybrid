<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Repo\Query\Stats\Plain;


include_once(__DIR__ . '/../../../../phpunit_bootstrap.php');

class Builder_ManualTest
    extends \Praxigento\Core\Test\BaseCase\Manual
{


    public function test_getSelectQuery()
    {
        $obm = \Magento\Framework\App\ObjectManager::getInstance();
        /** @var  $obj \Praxigento\BonusHybrid\Repo\Query\Stats\Plain\Builder */
        $obj = $obm->get(\Praxigento\BonusHybrid\Repo\Query\Stats\Plain\Builder::class);
        $res = $obj->getSelectQuery();
        $this->assertTrue($res instanceof \Magento\Framework\DB\Select);
    }

}