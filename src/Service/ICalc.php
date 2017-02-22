<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Service;

use Praxigento\BonusHybrid\Service\Calc\Request;
use Praxigento\BonusHybrid\Service\Calc\Response;

interface ICalc {
    /**
     * @param Request\BonusCourtesy $request
     *
     * @return Response\BonusCourtesy
     */
    public function bonusCourtesy(Request\BonusCourtesy $request);

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
     * @param Request\BonusPersonal $request
     *
     * @return Response\BonusPersonal
     */
    public function bonusPersonal(Request\BonusPersonal $request);

    /**
     * @param Request\BonusSignupDebit $request
     * @return \Praxigento\BonusHybrid\Service\Calc\Response\BonusSignupDebit
     */
    public function bonusSignupDebit(\Praxigento\BonusHybrid\Service\Calc\Request\BonusSignupDebit $request);

    /**
     * @param Request\BonusTeam $request
     *
     * @return Response\BonusTeam
     */
    public function bonusTeam(Request\BonusTeam $request);

    /**
     * @param Request\CompressOi $request
     *
     * @return Response\CompressOi
     */
    public function compressOi(Request\CompressOi $request);

    /**
     * @param Request\CompressPtc $request
     *
     * @return Response\CompressPtc
     */
    public function compressPtc(Request\CompressPtc $request);

    /**
     * @param Request\PvWriteOff $request
     *
     * @return Response\PvWriteOff
     */
    public function pvWriteOff(Request\PvWriteOff $request);

    /**
     * @param Request\ValueOv $request
     *
     * @return Response\ValueOv
     */
    public function valueOv(Request\ValueOv $request);

    /**
     * @param Request\ValueTv $request
     *
     * @return mixed
     */
    public function valueTv(Request\ValueTv $request);

}