<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\Bonus\Hybrid\Lib\Service\Calc;

use Praxigento\Bonus\Base\Lib\Entity\Calculation;
use Praxigento\Bonus\Base\Lib\Entity\Period;
use Praxigento\Bonus\Hybrid\Lib\Defaults as Def;
use Praxigento\Bonus\Hybrid\Lib\Entity\Compression\Oi as OiCompress;
use Praxigento\Bonus\Hybrid\Lib\Service\Calc\Sub\Calc;
use Praxigento\Bonus\Hybrid\Lib\Service\ICalc;
use Praxigento\Bonus\Hybrid\Lib\Service\Period\Request\GetForDependentCalc as PeriodGetForDependentCalcRequest;
use Praxigento\Bonus\Hybrid\Lib\Service\Period\Request\GetForWriteOff as PeriodGetForWriteOffRequest;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\Core\Service\Base\Call as BaseCall;

class Call extends BaseCall implements ICalc
{
    /** @var  \Praxigento\Accounting\Lib\Service\IAccount */
    protected $_callAcc;
    /** @var \Praxigento\Bonus\Hybrid\Lib\Service\IPeriod */
    protected $_callPeriod;
    /** @var  Sub\Calc */
    protected $_subCalc;
    /** @var  Sub\Db */
    protected $_subDb;
    /** @var  \Praxigento\Bonus\Hybrid\Lib\Tool\IScheme */
    protected $_toolScheme;
    /** @var  \Praxigento\Core\Tool\IPeriod */
    protected $_toolPeriod;
    /** @var  \Praxigento\Core\Repo\ITransactionManager */
    protected $_manTrans;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Praxigento\Core\Tool\IPeriod $toolPeriod,
        \Praxigento\Bonus\Hybrid\Lib\Tool\IScheme $toolScheme,
        \Praxigento\Core\Repo\ITransactionManager $manTrans,
        \Praxigento\Accounting\Lib\Service\IAccount $callAcc,
        \Praxigento\Bonus\Hybrid\Lib\Service\IPeriod $callBonusPeriod,
        Sub\Db $subDb,
        Sub\Calc $subCalc
    ) {
        parent::__construct($logger);
        $this->_toolPeriod = $toolPeriod;
        $this->_toolScheme = $toolScheme;
        $this->_manTrans = $manTrans;
        $this->_callAcc = $callAcc;
        $this->_callPeriod = $callBonusPeriod;
        $this->_subDb = $subDb;
        $this->_subCalc = $subCalc;
    }

    /**
     * Return calculations scheme (DEFAULT or EU).
     *
     * @param $val
     *
     * @return string
     */
    private function _getCalculationsScheme($val)
    {
        $result = $val == Def::SCHEMA_EU ? Def::SCHEMA_EU : Def::SCHEMA_DEFAULT;
        return $result;
    }

    public function bonusCourtesy(Request\BonusCourtesy $request)
    {
        $result = new Response\BonusCourtesy();
        $courtesyPercent = $request->getCourtesyBonusPercent();
        $datePerformed = $request->getDatePerformed();
        $dateApplied = $request->getDateApplied();
        $this->_logger->info("'Courtesy Bonus' calculation is started.");
        $reqGetPeriod = new PeriodGetForDependentCalcRequest();
        $reqGetPeriod->setBaseCalcTypeCode(Cfg::CODE_TYPE_CALC_VALUE_TV);
        $reqGetPeriod->setDependentCalcTypeCode(Cfg::CODE_TYPE_CALC_BONUS_COURTESY);
        $respGetPeriod = $this->_callPeriod->getForDependentCalc($reqGetPeriod);
        if ($respGetPeriod->isSucceed()) {
            $trans = $this->_manTrans->transactionBegin();
            try {
                /* working vars */
                $thisPeriodData = $respGetPeriod->getDependentPeriodData();
                $thisPeriodId = $thisPeriodData[Period::ATTR_ID];
                $thisCalcData = $respGetPeriod->getDependentCalcData();
                $thisCalcId = $thisCalcData[Calculation::ATTR_ID];
                $basePeriodData = $respGetPeriod->getBasePeriodData();
                $baseDsBegin = $basePeriodData[Period::ATTR_DSTAMP_BEGIN];
                $baseDsEnd = $basePeriodData[Period::ATTR_DSTAMP_END];
                /* get PTC Compression calculation ID for this period */
                $ptcCompressCalcId = $this->_subDb->getLastCalculationIdForPeriod(
                    Cfg::CODE_TYPE_CALC_COMPRESS_FOR_PTC,
                    $baseDsBegin,
                    $baseDsEnd
                );
                /* calculation itself */
                $this->_logger->info("Processing period #$thisPeriodId ($baseDsBegin-$baseDsEnd)");
                /* get compressed data by calculation ID */
                $compressPtc = $this->_subDb->getCompressedPtcData($ptcCompressCalcId);
                /* get levels to calculate Personal and Team bonuses */
                $levelsPersonal = $this->_subDb->getBonusLevels(Cfg::CODE_TYPE_CALC_BONUS_PERSONAL_DEF);
                $levelsTeam = $this->_subDb->getBonusLevels(Cfg::CODE_TYPE_CALC_BONUS_TEAM_DEF);
                /* calculates bonus and save operation with transactions */
                $updates = $this->_subCalc->bonusCourtesy($compressPtc, $courtesyPercent, $levelsPersonal, $levelsTeam);
                /* prepare data for updates */
                $updatesForWallets = [];
                foreach ($updates as $custId => $items) {
                    foreach ($items as $item) {
                        $bonus = $item[Calc::A_VALUE];
                        $childId = $item[Calc::A_OTHER_ID];
                        if ($bonus > Cfg::DEF_ZERO) {
                            $updatesForWallets[] = [
                                Calc::A_CUST_ID => $custId,
                                Calc::A_VALUE => $bonus,
                                Calc::A_OTHER_ID => $childId
                            ];
                        }
                    }
                }
                $respAdd = $this->_subDb->saveOperationWalletActive(
                    $updatesForWallets,
                    Cfg::CODE_TYPE_OPER_BONUS_COURTESY,
                    $datePerformed,
                    $dateApplied
                );
                $operId = $respAdd->getOperationId();
                $transIds = $respAdd->getTransactionsIds();
                /* save orders and correspondent transactions into the log */
                $this->_subDb->saveLogCustomers($updatesForWallets, $transIds);
                /* save processed operation to calculations log and mark calculation as complete */
                $this->_subDb->saveLogOperations($operId, $thisCalcId);
                $this->_subDb->markCalcComplete($thisCalcId);
                /* finalize response as succeed */
                $this->_manTrans->transactionCommit($trans);
                $result->markSucceed();
                $result->setPeriodId($thisPeriodId);
                $result->setCalcId($thisCalcId);
            } finally {
                $this->_manTrans->transactionClose($trans);
            }
        }
        $this->_logMemoryUsage();
        $this->_logger->info("'Courtesy Bonus' calculation is completed.");
        return $result;
    }

    public function bonusInfinity(Request\BonusInfinity $request)
    {
        $result = new Response\BonusInfinity();
        $scheme = $this->_getCalculationsScheme($request->getScheme());
        $datePerformed = $request->getDatePerformed();
        $dateApplied = $request->getDateApplied();
        $this->_logger->info("'Infinity Bonus' calculation is started ($scheme scheme).");
        if ($scheme == Def::SCHEMA_EU) {
            $calcTypeBase = Cfg::CODE_TYPE_CALC_COMPRESS_FOR_OI_EU;
            $calcType = Cfg::CODE_TYPE_CALC_BONUS_INFINITY_EU;
        } else {
            $calcTypeBase = Cfg::CODE_TYPE_CALC_COMPRESS_FOR_OI_DEF;
            $calcType = Cfg::CODE_TYPE_CALC_BONUS_INFINITY_DEF;
        }
        $reqGetPeriod = new PeriodGetForDependentCalcRequest();
        $reqGetPeriod->setBaseCalcTypeCode($calcTypeBase);
        $reqGetPeriod->setDependentCalcTypeCode($calcType);
        $respGetPeriod = $this->_callPeriod->getForDependentCalc($reqGetPeriod);
        if ($respGetPeriod->isSucceed()) {
            $trans = $this->_manTrans->transactionBegin();
            try {
                /* working vars */
                $thisPeriodData = $respGetPeriod->getDependentPeriodData();
                $thisPeriodId = $thisPeriodData[Period::ATTR_ID];
                $thisCalcData = $respGetPeriod->getDependentCalcData();
                $thisCalcId = $thisCalcData[Calculation::ATTR_ID];
                $basePeriodData = $respGetPeriod->getBasePeriodData();
                $baseDsBegin = $basePeriodData[Period::ATTR_DSTAMP_BEGIN];
                $baseDsEnd = $basePeriodData[Period::ATTR_DSTAMP_END];
                $baseCalcData = $respGetPeriod->getBaseCalcData();
                $baseCalcId = $baseCalcData[Calculation::ATTR_ID];
                /* calculation itself */
                $this->_logger->info("Processing period #$thisPeriodId ($baseDsBegin-$baseDsEnd)");
                /* get compressed data by calculation ID */
                $compressOi = $this->_subDb->getCompressedOiData($baseCalcId);
                /* get configuration for Override and Infinity bonuses */
                $cfgParams = $this->_subDb->getCfgParams();
                /* calculate bonus amounts */
                $updates = $this->_subCalc->bonusInfinity($compressOi, $scheme, $cfgParams);
                /* prepare update data */
                $updatesForWallets = [];
                $updatesForPvInf = [];
                foreach ($updates as $custId => $item) {
                    $pv = $item[Calc::A_PV];
                    $bonusEntries = $item[Calc::A_ENTRIES];
                    $updatesForPvInf[$custId] = [OiCompress::ATTR_PV_INF => $pv];
                    foreach ($bonusEntries as $entry) {
                        $bonus = $entry[Calc::A_VALUE];
                        $childId = $entry[Calc::A_OTHER_ID];
                        if ($bonus > Cfg::DEF_ZERO) {
                            $updatesForWallets[] = [
                                Calc::A_CUST_ID => $custId,
                                Calc::A_VALUE => $bonus,
                                Calc::A_OTHER_ID => $childId
                            ];
                        }
                    }
                }
                unset($updates);

                /* update wallets */
                $respAdd = $this->_subDb->saveOperationWalletActive(
                    $updatesForWallets,
                    Cfg::CODE_TYPE_OPER_BONUS_INFINITY,
                    $datePerformed,
                    $dateApplied
                );
                $operId = $respAdd->getOperationId();
                $transIds = $respAdd->getTransactionsIds();
                /* save orders and correspondent transactions into the log */
                $this->_subDb->saveLogCustomers($updatesForWallets, $transIds);
                unset($updatesForWallets);
                /* update OI Compressed data */
                $this->_subDb->saveUpdatesOiCompress($updatesForPvInf, $baseCalcId);
                /* save processed operation to calculations log and mark calculation as complete */
                $this->_subDb->saveLogOperations($operId, $thisCalcId);
                $this->_subDb->markCalcComplete($thisCalcId);
                /* finalize response as succeed */
                $this->_manTrans->transactionCommit($trans);
                $result->markSucceed();
                $result->setPeriodId($thisPeriodId);
                $result->setCalcId($thisCalcId);
            } finally {
                $this->_manTrans->transactionClose($trans);
            }
        }
        $this->_logMemoryUsage();
        $this->_logger->info("'Infinity Bonus' calculation is completed.");
        return $result;
    }

    /**
     * @param Request\BonusOverride $request
     *
     * @return Response\BonusOverride
     */
    public function bonusOverride(Request\BonusOverride $request)
    {
        $result = new Response\BonusOverride();
        $scheme = $this->_getCalculationsScheme($request->getScheme());
        $datePerformed = $request->getDatePerformed();
        $dateApplied = $request->getDateApplied();
        $this->_logger->info("'Override Bonus' calculation is started ($scheme scheme).");
        if ($scheme == Def::SCHEMA_EU) {
            $calcTypeBase = Cfg::CODE_TYPE_CALC_COMPRESS_FOR_OI_EU;
            $calcType = Cfg::CODE_TYPE_CALC_BONUS_OVERRIDE_EU;
        } else {
            $calcTypeBase = Cfg::CODE_TYPE_CALC_COMPRESS_FOR_OI_DEF;
            $calcType = Cfg::CODE_TYPE_CALC_BONUS_OVERRIDE_DEF;
        }
        $reqGetPeriod = new PeriodGetForDependentCalcRequest();
        $reqGetPeriod->setBaseCalcTypeCode($calcTypeBase);
        $reqGetPeriod->setDependentCalcTypeCode($calcType);
        $respGetPeriod = $this->_callPeriod->getForDependentCalc($reqGetPeriod);
        if ($respGetPeriod->isSucceed()) {
            $trans = $this->_manTrans->transactionBegin();
            try {
                /* working vars */
                $thisPeriodData = $respGetPeriod->getDependentPeriodData();
                $thisPeriodId = $thisPeriodData[Period::ATTR_ID];
                $thisCalcData = $respGetPeriod->getDependentCalcData();
                $thisCalcId = $thisCalcData[Calculation::ATTR_ID];
                $basePeriodData = $respGetPeriod->getBasePeriodData();
                $baseDsBegin = $basePeriodData[Period::ATTR_DSTAMP_BEGIN];
                $baseDsEnd = $basePeriodData[Period::ATTR_DSTAMP_END];
                $baseCalcData = $respGetPeriod->getBaseCalcData();
                $baseCalcId = $baseCalcData[Calculation::ATTR_ID];
                /* calculation itself */
                $this->_logger->info("Processing period #$thisPeriodId ($baseDsBegin-$baseDsEnd)");
                /* get compressed data by calculation ID */
                $compressOi = $this->_subDb->getCompressedOiData($baseCalcId);
                /* get configuration for Override and Infinity bonuses */
                $cfgOverride = $this->_subDb->getCfgOverride();
                /* calculates bonus and save operation with transactions */
                $updates = $this->_subCalc->bonusOverride($compressOi, $scheme, $cfgOverride);
                /* prepare data for updates */
                $updatesForWallets = [];
                foreach ($updates as $item) {
                    $custId = $item[Calc::A_CUST_ID];
                    $bonusData = $item[Calc::A_ENTRIES];
                    foreach ($bonusData as $entry) {
                        $bonus = $entry[Calc::A_VALUE];
                        $childId = $entry[Calc::A_OTHER_ID];
                        if ($bonus > Cfg::DEF_ZERO) {
                            $updatesForWallets[] = [
                                Calc::A_CUST_ID => $custId,
                                Calc::A_VALUE => $bonus,
                                Calc::A_OTHER_ID => $childId
                            ];
                        }
                    }
                }
                unset($updates);
                /* update wallets */
                $respAdd = $this->_subDb->saveOperationWalletActive(
                    $updatesForWallets,
                    Cfg::CODE_TYPE_OPER_BONUS_OVERRIDE,
                    $datePerformed,
                    $dateApplied
                );
                $operId = $respAdd->getOperationId();
                $transIds = $respAdd->getTransactionsIds();
                /* save orders and correspondent transactions into the log */
                $this->_subDb->saveLogCustomers($updatesForWallets, $transIds);
                unset($updatesForWallets);
                /* save processed operation to calculations log and mark calculation as complete */
                $this->_subDb->saveLogOperations($operId, $thisCalcId);
                $this->_subDb->markCalcComplete($thisCalcId);
                /* finalize response as succeed */
                $this->_manTrans->transactionCommit($trans);
                $result->markSucceed();
                $result->setPeriodId($thisPeriodId);
                $result->setCalcId($thisCalcId);
            } finally {
                $this->_manTrans->transactionClose($trans);
            }
        }
        $this->_logMemoryUsage();
        $this->_logger->info("'Override Bonus' calculation is completed.");
        return $result;
    }

    public function bonusPersonal(Request\BonusPersonal $request)
    {
        $result = new Response\BonusPersonal();
        $scheme = $this->_getCalculationsScheme($request->getScheme());
        $datePerformed = $request->getDatePerformed();
        $dateApplied = $request->getDateApplied();
        $this->_logger->info("'Personal Bonus' calculation is started. Scheme: $scheme, performed at: $datePerformed, applied at: $dateApplied.");
        $reqGetPeriod = new PeriodGetForDependentCalcRequest();
        $calcTypeBase = Cfg::CODE_TYPE_CALC_COMPRESS_FOR_PTC;
        if ($scheme == Def::SCHEMA_EU) {
            $calcType = Cfg::CODE_TYPE_CALC_BONUS_PERSONAL_EU;
        } else {
            $calcType = Cfg::CODE_TYPE_CALC_BONUS_PERSONAL_DEF;
        }
        $reqGetPeriod->setBaseCalcTypeCode($calcTypeBase);
        $reqGetPeriod->setDependentCalcTypeCode($calcType);
        $respGetPeriod = $this->_callPeriod->getForDependentCalc($reqGetPeriod);
        if ($respGetPeriod->isSucceed()) {
            $trans = $this->_manTrans->transactionBegin();
            try {
                /* working vars */
                $thisPeriodData = $respGetPeriod->getDependentPeriodData();
                $thisPeriodId = $thisPeriodData[Period::ATTR_ID];
                $thisCalcData = $respGetPeriod->getDependentCalcData();
                $thisCalcId = $thisCalcData[Calculation::ATTR_ID];
                $basePeriodData = $respGetPeriod->getBasePeriodData();
                $baseDsBegin = $basePeriodData[Period::ATTR_DSTAMP_BEGIN];
                $baseDsEnd = $basePeriodData[Period::ATTR_DSTAMP_END];
                $baseCalcData = $respGetPeriod->getBaseCalcData();
                $baseCalcId = $baseCalcData[Calculation::ATTR_ID];
                /* calculation itself */
                $this->_logger->info("Processing period #$thisPeriodId ($baseDsBegin-$baseDsEnd)");
                /* get compressed data by calculation ID */
                $compressPtc = $this->_subDb->getCompressedPtcData($baseCalcId);
                /* calculates bonus according to the calculation scheme */
                if ($scheme == Def::SCHEMA_EU) {
                    /* use EU scheme */
                    $treeFlat = $this->_subDb->getDownlineSnapshot($baseDsEnd);
                    $orders = $this->_subDb->getSaleOrdersForRebate($baseDsBegin, $baseDsEnd);
                    $updates = $this->_subCalc->bonusPersonalEu($treeFlat, $compressPtc, $orders);
                    /* convert */
                    $respAdd = $this->_subDb->saveOperationWalletActive(
                        $updates,
                        Cfg::CODE_TYPE_OPER_BONUS_REBATE,
                        $datePerformed,
                        $dateApplied
                    );
                    $operId = $respAdd->getOperationId();
                    $transIds = $respAdd->getTransactionsIds();
                    /* save orders and correspondent transactions into the log */
                    $this->_subDb->saveLogSaleOrders($updates, $transIds);
                } else {
                    /* use DEFAULT scheme */
                    /* get levels to calculate Personal bonus */
                    $levelsPersonal = $this->_subDb->getBonusLevels($calcType);
                    $updates = $this->_subCalc->bonusPersonalDef($compressPtc, $levelsPersonal);
                    /* save bonus operation with transactions */
                    $respAdd = $this->_subDb->saveOperationWalletActive(
                        $updates,
                        Cfg::CODE_TYPE_OPER_BONUS_PERSONAL,
                        $datePerformed,
                        $dateApplied
                    );
                    $operId = $respAdd->getOperationId();
                }
                /* mark calculation as complete */
                $this->_subDb->saveLogOperations($operId, $thisCalcId);
                $this->_subDb->markCalcComplete($thisCalcId);
                /* finalize response as succeed */
                $this->_manTrans->transactionCommit($trans);
                $result->markSucceed();
                $result->setPeriodId($thisPeriodId);
                $result->setCalcId($thisCalcId);
            } finally {
                // transaction will be rolled back if commit is not done (otherwise - do nothing)
                $this->_manTrans->transactionClose($trans);
            }
        }
        $this->_logMemoryUsage();
        $this->_logger->info("'Personal Bonus' calculation is completed.");
        return $result;
    }

    public function bonusTeam(Request\BonusTeam $request)
    {
        $result = new Response\BonusTeam();
        $scheme = $this->_getCalculationsScheme($request->getScheme());
        $courtesyPercent = $request->getCourtesyBonusPercent();
        $teamBonusPercent = $request->getTeamBonusPercent();
        $datePerformed = $request->getDatePerformed();
        $dateApplied = $request->getDateApplied();
        $this->_logger->info("'Team Bonus' calculation is started.");
        $reqGetPeriod = new PeriodGetForDependentCalcRequest();
        $calcTypeBase = Cfg::CODE_TYPE_CALC_VALUE_TV;
        if ($scheme == Def::SCHEMA_EU) {
            $calcType = Cfg::CODE_TYPE_CALC_BONUS_TEAM_EU;
        } else {
            $calcType = Cfg::CODE_TYPE_CALC_BONUS_TEAM_DEF;
        }
        $reqGetPeriod->setBaseCalcTypeCode($calcTypeBase);
        $reqGetPeriod->setDependentCalcTypeCode($calcType);
        $respGetPeriod = $this->_callPeriod->getForDependentCalc($reqGetPeriod);
        if ($respGetPeriod->isSucceed()) {
            $trans = $this->_manTrans->transactionBegin();
            try {
                /* working vars */
                $thisPeriodData = $respGetPeriod->getDependentPeriodData();
                $thisPeriodId = $thisPeriodData[Period::ATTR_ID];
                $thisCalcData = $respGetPeriod->getDependentCalcData();
                $thisCalcId = $thisCalcData[Calculation::ATTR_ID];
                $basePeriodData = $respGetPeriod->getBasePeriodData();
                $baseDsBegin = $basePeriodData[Period::ATTR_DSTAMP_BEGIN];
                $baseDsEnd = $basePeriodData[Period::ATTR_DSTAMP_END];
                /* get the last PTC compression calc id for this period */
                $ptcCompressCalcId = $this->_subDb->getLastCalculationIdForPeriod(
                    Cfg::CODE_TYPE_CALC_COMPRESS_FOR_PTC,
                    $baseDsBegin,
                    $baseDsEnd
                );
                /* calculation itself */
                $this->_logger->info("Processing period #$thisPeriodId ($baseDsBegin-$baseDsEnd)");
                /* get compressed data by calculation ID */
                $compressPtc = $this->_subDb->getCompressedPtcData($ptcCompressCalcId);
                /* calculate bonus values according to DEFAULT or EU schemes */
                if ($scheme == Def::SCHEMA_EU) {
                    $updates = $this->_subCalc->bonusTeamEu($compressPtc, $teamBonusPercent);
                    /* save operation with transactions */
                    $respAdd = $this->_subDb->saveOperationWalletActive(
                        $updates,
                        Cfg::CODE_TYPE_OPER_BONUS_TEAM,
                        $datePerformed,
                        $dateApplied
                    );
                    $operId = $respAdd->getOperationId();
                    $transIds = $respAdd->getTransactionsIds();
                    /* save customers and correspondent transactions into the log */
                    $this->_subDb->saveLogCustomers($updates, $transIds);
                } else {
                    /* get levels to calculate Personal and Team bonuses */
                    $levelsPersonal = $this->_subDb->getBonusLevels(Cfg::CODE_TYPE_CALC_BONUS_PERSONAL_DEF);
                    $levelsTeam = $this->_subDb->getBonusLevels(Cfg::CODE_TYPE_CALC_BONUS_TEAM_DEF);
                    $updates = $this->_subCalc->bonusTeamDef($compressPtc, $levelsPersonal, $levelsTeam,
                        $courtesyPercent);
                    /* save operation with transactions */
                    $respAdd = $this->_subDb->saveOperationWalletActive(
                        $updates,
                        Cfg::CODE_TYPE_OPER_BONUS_TEAM,
                        $datePerformed,
                        $dateApplied
                    );
                    $operId = $respAdd->getOperationId();
                }

                /* save processed operation to calculations log and mark calculation as complete */
                $this->_subDb->saveLogOperations($operId, $thisCalcId);
                $this->_subDb->markCalcComplete($thisCalcId);
                /* finalize response as succeed */
                $this->_manTrans->transactionCommit($trans);
                $result->markSucceed();
                $result->setPeriodId($thisPeriodId);
                $result->setCalcId($thisCalcId);
            } finally {
                $this->_manTrans->transactionClose($trans);
            }
        }
        $this->_logMemoryUsage();
        $this->_logger->info("'Team Bonus' calculation is completed.");
        return $result;
    }

    public function compressOi(Request\CompressOi $request)
    {
        $result = new Response\CompressOi();
        $scheme = $this->_getCalculationsScheme($request->getScheme());
        $this->_logger->info("'OI Compression' calculation is started ($scheme scheme).");
        if ($scheme == Def::SCHEMA_EU) {
            $calcType = Cfg::CODE_TYPE_CALC_COMPRESS_FOR_OI_EU;
        } else {
            $calcType = Cfg::CODE_TYPE_CALC_COMPRESS_FOR_OI_DEF;
        }
        $reqGetPeriod = new PeriodGetForDependentCalcRequest();
        $reqGetPeriod->setBaseCalcTypeCode(Cfg::CODE_TYPE_CALC_VALUE_OV);
        $reqGetPeriod->setDependentCalcTypeCode($calcType);
        $respGetPeriod = $this->_callPeriod->getForDependentCalc($reqGetPeriod);
        if ($respGetPeriod->isSucceed()) {
            $trans = $this->_manTrans->transactionBegin();
            try {
                /* working vars */
                $thisPeriodData = $respGetPeriod->getDependentPeriodData();
                $thisPeriodId = $thisPeriodData[Period::ATTR_ID];
                $thisCalcData = $respGetPeriod->getDependentCalcData();
                $thisCalcId = $thisCalcData[Calculation::ATTR_ID];
                $basePeriodData = $respGetPeriod->getBasePeriodData();
                $baseDsBegin = $basePeriodData[Period::ATTR_DSTAMP_BEGIN];
                $baseDsEnd = $basePeriodData[Period::ATTR_DSTAMP_END];
                /* get the last PTC compression calc id for this period */
                $ptcCompressCalcId = $this->_subDb->getLastCalculationIdForPeriod(
                    Cfg::CODE_TYPE_CALC_COMPRESS_FOR_PTC,
                    $baseDsBegin,
                    $baseDsEnd
                );
                /* calculation itself */
                $this->_logger->info("Processing period #$thisPeriodId ($baseDsBegin-$baseDsEnd)");
                /* get compressed data by calculation ID */
                $compressPtc = $this->_subDb->getCompressedPtcData($ptcCompressCalcId);
                /* ranks configuration (ranks, schemes, qualification levels, etc.)*/
                $cfgParams = $this->_subDb->getCfgParams();
                /* calculate updates */
                $updates = $this->_subCalc->compressOi($compressPtc, $cfgParams, $scheme);
                /* save updates and mark calculation complete */
                $this->_subDb->saveCompressedOi($updates, $thisCalcId);
                $this->_subDb->markCalcComplete($thisCalcId);
                $this->_manTrans->transactionCommit($trans);
                $result->markSucceed();
                $result->setPeriodId($thisPeriodId);
                $result->setCalcId($thisCalcId);
            } finally {
                $this->_manTrans->transactionClose($trans);
            }
        }
        $this->_logMemoryUsage();
        $this->_logger->info("'OI Compression' calculation is completed.");
        return $result;
    }

    /**
     * @param Request\CompressPtc $request
     *
     * @return Response\CompressPtc
     */
    public function compressPtc(Request\CompressPtc $request)
    {
        $result = new Response\CompressPtc();
        $this->_logger->info("'PTC Compression' calculation is started.");
        $reqGetPeriod = new PeriodGetForDependentCalcRequest();
        $reqGetPeriod->setBaseCalcTypeCode(Cfg::CODE_TYPE_CALC_PV_WRITE_OFF);
        $reqGetPeriod->setDependentCalcTypeCode(Cfg::CODE_TYPE_CALC_COMPRESS_FOR_PTC);
        $respGetPeriod = $this->_callPeriod->getForDependentCalc($reqGetPeriod);
        if ($respGetPeriod->isSucceed()) {
            $trans = $this->_manTrans->transactionBegin();
            try {
                /* working vars */
                $thisPeriodData = $respGetPeriod->getDependentPeriodData();
                $thisPeriodId = $thisPeriodData[Period::ATTR_ID];
                $thisDsBegin = $thisPeriodData[Period::ATTR_DSTAMP_BEGIN];
                $thisDsEnd = $thisPeriodData[Period::ATTR_DSTAMP_END];
                $thisCalcData = $respGetPeriod->getDependentCalcData();
                $thisCalcId = $thisCalcData[Calculation::ATTR_ID];
                $baseCalcData = $respGetPeriod->getBaseCalcData();
                $baseCalcIdId = $baseCalcData[Calculation::ATTR_ID];
                /* calculation itself */
                $this->_logger->info("Processing period #$thisPeriodId ($thisDsBegin-$thisDsEnd)");
                $downlineSnap = $this->_subDb->getDownlineSnapshot($thisDsEnd);
                $customersData = $this->_subDb->getDownlineCustomersData();
                $transData = $this->_subDb->getDataForPvCompression($baseCalcIdId);
                $updates = $this->_subCalc->compressPtc($downlineSnap, $customersData, $transData);
                $this->_subDb->saveCompressedPtc($updates, $thisCalcId);
                $this->_subDb->markCalcComplete($thisCalcId);
                $this->_manTrans->transactionCommit($trans);
                $result->markSucceed();
                $result->setPeriodId($thisPeriodId);
                $result->setCalcId($thisCalcId);
            } finally {
                $this->_manTrans->transactionClose($trans);
            }
        }
        $this->_logMemoryUsage();
        $this->_logger->info("'PTC Compression' calculation is completed.");
        return $result;
    }

    /**
     * @param Request\PvWriteOff $request
     *
     * @return Response\PvWriteOff
     */
    public function pvWriteOff(Request\PvWriteOff $request)
    {
        $result = new Response\PvWriteOff();
        $datePerformed = $request->getDatePerformed();
        $this->_logger->info("'PV Write Off' calculation is started.");
        $reqGetPeriod = new PeriodGetForWriteOffRequest();
        $respGetPeriod = $this->_callPeriod->getForWriteOff($reqGetPeriod);
        if ($respGetPeriod->isSucceed()) {
            if ($respGetPeriod->hasNoPvTransactionsYet()) {
                $this->_logger->info("There is no PV transactions yet. Nothing to calculate.");
                $result->markSucceed();
            } else {
                $trans = $this->_manTrans->transactionBegin();
                try {
                    /* working vars */
                    $periodData = $respGetPeriod->getPeriodData();
                    $periodId = $periodData[Period::ATTR_ID];
                    $calcData = $respGetPeriod->getCalcData();
                    $calcId = $calcData[Calculation::ATTR_ID];
                    $periodBegin = $periodData[Period::ATTR_DSTAMP_BEGIN];
                    $periodEnd = $periodData[Period::ATTR_DSTAMP_END];
                    $this->_logger->info("Processing period #$periodId ($periodBegin-$periodEnd), calculation #$calcId.");
                    $transData = $this->_subDb->getDataForWriteOff($calcId, $periodBegin, $periodEnd);
                    $updates = $this->_subCalc->pvWriteOff($transData);
                    $dateApplied = $this->_toolPeriod->getTimestampTo($periodEnd);
                    $operId = $this->_subDb->saveOperationPvWriteOff($updates, $datePerformed, $dateApplied);
                    $this->_subDb->saveLogPvWriteOff($transData, $operId, $calcId);
                    $this->_subDb->markCalcComplete($calcId);
                    $this->_manTrans->transactionCommit($trans);
                    $result->setPeriodId($periodId);
                    $result->setCalcId($calcId);
                    $result->markSucceed();
                } finally {
                    $this->_manTrans->transactionClose($trans);
                }
            }
        }
        $this->_logMemoryUsage();
        $this->_logger->info("'PV Write Off' calculation is completed.");
        return $result;
    }

    /**
     * @param Request\ValueOv $request
     *
     * @return Response\ValueOv
     */
    public function valueOv(Request\ValueOv $request)
    {
        $result = new Response\ValueOv();
        $this->_logger->info("'OV Value' calculation is started.");
        $reqGetPeriod = new PeriodGetForDependentCalcRequest();
        $reqGetPeriod->setBaseCalcTypeCode(Cfg::CODE_TYPE_CALC_COMPRESS_FOR_PTC);
        $reqGetPeriod->setDependentCalcTypeCode(Cfg::CODE_TYPE_CALC_VALUE_OV);
        $respGetPeriod = $this->_callPeriod->getForDependentCalc($reqGetPeriod);
        if ($respGetPeriod->isSucceed()) {
            $trans = $this->_manTrans->transactionBegin();
            try {
                /* working vars */
                $thisPeriodData = $respGetPeriod->getDependentPeriodData();
                $thisPeriodId = $thisPeriodData[Period::ATTR_ID];
                $thisCalcData = $respGetPeriod->getDependentCalcData();
                $thisCalcId = $thisCalcData[Calculation::ATTR_ID];
                $basePeriodData = $respGetPeriod->getBasePeriodData();
                $baseDsBegin = $basePeriodData[Period::ATTR_DSTAMP_BEGIN];
                $baseDsEnd = $basePeriodData[Period::ATTR_DSTAMP_END];
                $baseCalcData = $respGetPeriod->getBaseCalcData();
                $baseCalcId = $baseCalcData[Calculation::ATTR_ID];
                /* calculation itself */
                $this->_logger->info("Processing period #$thisPeriodId ($baseDsBegin-$baseDsEnd)");
                /* get compressed data by calculation ID */
                $compressPtc = $this->_subDb->getCompressedPtcData($baseCalcId);
                $updates = $this->_subCalc->valueOv($compressPtc);
                $this->_subDb->saveValueOv($updates, $baseCalcId);
                $this->_subDb->markCalcComplete($thisCalcId);
                $this->_manTrans->transactionCommit($trans);
                $result->markSucceed();
                $result->setPeriodId($thisPeriodId);
                $result->setCalcId($thisCalcId);
            } finally {
                $this->_manTrans->transactionClose($trans);
            }
        }
        $this->_logMemoryUsage();
        $this->_logger->info("'OV Value' calculation is completed.");
        return $result;
    }

    public function valueTv(Request\ValueTv $request)
    {
        $result = new Response\ValueTv();
        $this->_logger->info("'TV Value' calculation is started.");
        $reqGetPeriod = new PeriodGetForDependentCalcRequest();
        $reqGetPeriod->setBaseCalcTypeCode(Cfg::CODE_TYPE_CALC_COMPRESS_FOR_PTC);
        $reqGetPeriod->setDependentCalcTypeCode(Cfg::CODE_TYPE_CALC_VALUE_TV);
        $respGetPeriod = $this->_callPeriod->getForDependentCalc($reqGetPeriod);
        if ($respGetPeriod->isSucceed()) {
            $trans = $this->_manTrans->transactionBegin();
            try {
                /* working vars */
                $thisPeriodData = $respGetPeriod->getDependentPeriodData();
                $thisPeriodId = $thisPeriodData[Period::ATTR_ID];
                $thisCalcData = $respGetPeriod->getDependentCalcData();
                $thisCalcId = $thisCalcData[Calculation::ATTR_ID];
                $basePeriodData = $respGetPeriod->getBasePeriodData();
                $baseDsBegin = $basePeriodData[Period::ATTR_DSTAMP_BEGIN];
                $baseDsEnd = $basePeriodData[Period::ATTR_DSTAMP_END];
                $baseCalcData = $respGetPeriod->getBaseCalcData();
                $baseCalcId = $baseCalcData[Calculation::ATTR_ID];
                /* calculation itself */
                $this->_logger->info("Processing period #$thisPeriodId ($baseDsBegin-$baseDsEnd)");
                /* get compressed data by calculation ID */
                $compressPtc = $this->_subDb->getCompressedPtcData($baseCalcId);
                $updates = $this->_subCalc->valueTv($compressPtc);
                $this->_subDb->saveValueTv($updates, $baseCalcId);
                $this->_subDb->markCalcComplete($thisCalcId);
                $this->_manTrans->transactionCommit($trans);
                $result->markSucceed();
                $result->setPeriodId($thisPeriodId);
                $result->setCalcId($thisCalcId);
            } finally {
                $this->_manTrans->transactionClose($trans);
            }
        }
        $this->_logMemoryUsage();
        $this->_logger->info("'TV Value' calculation is completed.");
        return $result;
    }
}