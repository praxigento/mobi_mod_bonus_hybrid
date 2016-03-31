<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\Bonus\Hybrid\Lib\Tool;

include_once(__DIR__ . '/../../phpunit_bootstrap.php');

class Box_UnitTest extends \Praxigento\Core\Lib\Test\BaseTestCase {

    public function test_getters() {
        /** === Test Data === */
        /** === Mocks === */
        $mConvert = $this->_mockFor(\Praxigento\Core\Lib\Tool\Convert::class);
        $mDate = $this->_mockFor(\Praxigento\Core\Lib\Tool\Date::class);
        $mFormat = $this->_mockFor(\Praxigento\Core\Lib\Tool\Format::class);
        $mPeriod = $this->_mockFor(\Praxigento\Core\Lib\Tool\Period::class);
        $mScheme = $this->_mockFor(\Praxigento\Bonus\Hybrid\Lib\Tool\IScheme::class);
        $mDownlineTree = $this->_mockFor(\Praxigento\Downline\Lib\Tool\ITree::class);
        /** === Test itself === */
        $obj = new Box($mConvert, $mDate, $mFormat, $mPeriod, $mScheme, $mDownlineTree);
        $this->assertInstanceOf(\Praxigento\Core\Lib\Tool\Convert::class, $obj->getConvert());
        $this->assertInstanceOf(\Praxigento\Core\Lib\Tool\Date::class, $obj->getDate());
        $this->assertInstanceOf(\Praxigento\Core\Lib\Tool\Format::class, $obj->getFormat());
        $this->assertInstanceOf(\Praxigento\Core\Lib\Tool\Period::class, $obj->getPeriod());
        $this->assertInstanceOf(\Praxigento\Bonus\Hybrid\Lib\Tool\IScheme::class, $obj->getScheme());
        $this->assertInstanceOf(\Praxigento\Downline\Lib\Tool\ITree::class, $obj->getDownlineTree());
    }
}