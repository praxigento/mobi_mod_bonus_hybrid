<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\Bonus\Hybrid\Lib\Test;




abstract class BaseTestCase extends \Praxigento\Core\Test\BaseCase\Mockery {


    protected function _mockToolbox(
        $mConvert = null,
        $mDate = null,
        $mFormat = null,
        $mPeriod = null,
        $mScheme = null,
        $mDownlineTree = null
    ) {
        $result = $this->_mockFor('Praxigento\Bonus\Hybrid\Lib\IToolbox');
        if(!is_null($mConvert)) {
            $result
                ->expects($this->any())
                ->method('getConvert')
                ->willReturn($mConvert);
        }
        if(!is_null($mDate)) {
            $result
                ->expects($this->any())
                ->method('getDate')
                ->willReturn($mDate);
        }
        if(!is_null($mFormat)) {
            $result
                ->expects($this->any())
                ->method('getFormat')
                ->willReturn($mFormat);
        }
        if(!is_null($mPeriod)) {
            $result
                ->expects($this->any())
                ->method('getPeriod')
                ->willReturn($mPeriod);
        }
        if(!is_null($mScheme)) {
            $result
                ->expects($this->any())
                ->method('getScheme')
                ->willReturn($mScheme);
        }
        if(!is_null($mDownlineTree)) {
            $result
                ->expects($this->any())
                ->method('getDownlineTree')
                ->willReturn($mDownlineTree);
        }
        return $result;
    }

}