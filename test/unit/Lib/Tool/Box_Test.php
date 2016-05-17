<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\Bonus\Hybrid\Lib\Tool;

include_once(__DIR__ . '/../../phpunit_bootstrap.php');

class Box_UnitTest extends \Praxigento\Core\Test\BaseMockeryCase
{
    protected function setUp()
    {
        parent::setUp();
        $this->markTestSkipped('Test is deprecated after M1 & M2 merge is done.');
    }

    public function test_getters()
    {
        /** === Test Data === */
        /** === Mocks === */
        $mConvert = $this->_mockFor(\Praxigento\Core\Lib\Tool\Convert::class);
        $mDate = $this->_mockFor(\Praxigento\Core\Tool\IDate::class);
        $mFormat = $this->_mockFor(\Praxigento\Core\Tool\IFormat::class);
        $mPeriod = $this->_mockFor(\Praxigento\Core\Tool\IPeriod::class);
        $mScheme = $this->_mockFor(\Praxigento\Bonus\Hybrid\Lib\Tool\IScheme::class);
        $mDownlineTree = $this->_mockFor(\Praxigento\Downline\Tool\ITree::class);
        /** === Test itself === */
        $obj = new Box($mConvert, $mDate, $mFormat, $mPeriod, $mScheme, $mDownlineTree);
        $this->assertInstanceOf(\Praxigento\Core\Lib\Tool\Convert::class, $obj->getConvert());
        $this->assertInstanceOf(\Praxigento\Core\Tool\IDate::class, $obj->getDate());
        $this->assertInstanceOf(\Praxigento\Core\Tool\IFormat::class, $obj->getFormat());
        $this->assertInstanceOf(\Praxigento\Core\Tool\IPeriod::class, $obj->getPeriod());
        $this->assertInstanceOf(\Praxigento\Bonus\Hybrid\Lib\Tool\IScheme::class, $obj->getScheme());
        $this->assertInstanceOf(\Praxigento\Downline\Tool\ITree::class, $obj->getDownlineTree());
    }
}