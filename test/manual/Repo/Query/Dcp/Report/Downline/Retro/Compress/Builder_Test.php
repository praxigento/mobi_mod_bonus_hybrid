<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Repo\Query\Dcp\Report\Downline\Retro\Compress;

include_once(__DIR__ . '/../../../../../../../phpunit_bootstrap.php');

class Builder_ManualTest
    extends \Praxigento\Core\Test\BaseCase\Manual
{

    public function test_build()
    {
        /** @var  $obj \Praxigento\BonusHybrid\Repo\Query\Dcp\Report\Downline\Retro\Compress\Builder */
        $obj = $this->manObj->get(\Praxigento\BonusHybrid\Repo\Query\Dcp\Report\Downline\Retro\Compress\Builder::class);
        $res = $obj->build();
        $this->assertTrue($res instanceof \Magento\Framework\DB\Select);
    }

}