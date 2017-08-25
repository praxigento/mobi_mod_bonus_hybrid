<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Service;

use Praxigento\BonusHybrid\Service\Period\Request;
use Praxigento\BonusHybrid\Service\Period\Response;

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
     *
     * @deprecated use \Praxigento\BonusBase\Service\Period\Calc\Get\IBasis
     */
    public function getForWriteOff(Request\GetForWriteOff $request);

}