<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\Bonus\Hybrid\Lib\Service;

use Praxigento\Bonus\Hybrid\Lib\Service\Period\Request;
use Praxigento\Bonus\Hybrid\Lib\Service\Period\Response;

interface IPeriod {
    /**
     * Get period data for calculation dependent on the other calculation.
     *
     * @param Request\GetForDependentCalc $request
     *
     * @return Response\GetForDependentCalc
     */
    public function getForDependentCalc(Request\GetForDependentCalc $request);

    /**
     * @param Request\GetForWriteOff $request
     *
     * @return Response\GetForWriteOff
     */
    public function getForWriteOff(Request\GetForWriteOff $request);

}