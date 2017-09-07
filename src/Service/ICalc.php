<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Service;

use Praxigento\BonusHybrid\Service\Calc\Request;
use Praxigento\BonusHybrid\Service\Calc\Response;

interface ICalc {

    /**
     * @param Request\BonusInfinity $request
     *
     * @return Response\BonusInfinity
     */
    public function bonusInfinity(Request\BonusInfinity $request);

    /**
     * @param Request\BonusOverride $request
     *
     * @return Response\BonusOverride
     */
    public function bonusOverride(Request\BonusOverride $request);

    /**
     * @param Request\CompressOi $request
     *
     * @return Response\CompressOi
     */
    public function compressOi(Request\CompressOi $request);

}