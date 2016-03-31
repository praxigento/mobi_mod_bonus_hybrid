<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\Bonus\Hybrid\Lib\Service\Calc\Sub;

use Praxigento\Core\Lib\Context;

include_once(__DIR__ . '/../../../phpunit_bootstrap.php');

class Db_ManualTest extends \Praxigento\Core\Lib\Test\BaseTestCase {

    public function test_getDownlineSnapshot() {
        $obm = Context::instance()->getObjectManager();
        /** @var  $db \Praxigento\Bonus\Hybrid\Lib\Service\Calc\Sub\Db */
        $db = $obm->get('Praxigento\Bonus\Hybrid\Lib\Service\Calc\Sub\Db');
        $data = $db->getDownlineSnapshot('20151231');
        $this->assertNotNull($data);
    }

    public function test_getOperationsForWriteOff() {
        $obm = Context::instance()->getObjectManager();
        /** @var  $db \Praxigento\Bonus\Hybrid\Lib\Service\Calc\Sub\Db */
        $db = $obm->get('Praxigento\Bonus\Hybrid\Lib\Service\Calc\Sub\Db');
        $data = $db->getDataForWriteOff(1, '2015-01-01', '2015-12-31');
        $this->assertNotNull($data);
    }

    public function test_getSaleOrdersForRebate() {
        $obm = Context::instance()->getObjectManager();
        /** @var  $db \Praxigento\Bonus\Hybrid\Lib\Service\Calc\Sub\Db */
        $db = $obm->get('Praxigento\Bonus\Hybrid\Lib\Service\Calc\Sub\Db');
        $data = $db->getSaleOrdersForRebate('2016-01-01 00:00:00', '2016-01-31 23:59:59');
        $this->assertNotNull($data);
    }


}