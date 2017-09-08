<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Service\Calc;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Defaults as Def;
use Praxigento\BonusHybrid\Repo\Entity\Data\Compression\Oi as OiCompress;
use Praxigento\BonusHybrid\Service\Calc\Sub\Calc;
use Praxigento\BonusHybrid\Service\Period\Request\GetForDependentCalc as PeriodGetForDependentCalcRequest;

/**
 * @SuppressWarnings(PHPMD.CamelCasePropertyName)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Call
    extends \Praxigento\Core\Service\Base\Call
    implements \Praxigento\BonusHybrid\Service\ICalc
{
    /** @var  \Praxigento\Accounting\Service\IAccount */
    protected $_callAcc;
    /** @var \Praxigento\BonusHybrid\Service\IPeriod */
    protected $_callPeriod;
    /** @var  \Praxigento\Core\Transaction\Database\IManager */
    protected $_manTrans;
    /** @var  \Praxigento\BonusHybrid\Service\Calc\Sub\Calc */
    protected $_subCalc;
    /** @var  Sub\Db */
    protected $_subDb;
    /** @var  \Praxigento\Core\Tool\IPeriod */
    protected $_toolPeriod;
    /** @var  \Praxigento\BonusHybrid\Tool\IScheme */
    protected $_toolScheme;
    /** @var  \Praxigento\BonusHybrid\Service\Calc\Sub\CompressOi */
    protected $subCompressOi;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Sub\Pto */
    protected $subPto;

    public function __construct(
        \Praxigento\Core\Fw\Logger\App $logger,
        \Magento\Framework\ObjectManagerInterface $manObj,
        \Praxigento\Core\Tool\IPeriod $toolPeriod,
        \Praxigento\BonusHybrid\Tool\IScheme $toolScheme,
        \Praxigento\Core\Transaction\Database\IManager $manTrans,
        \Praxigento\Accounting\Service\IAccount $callAcc,
        \Praxigento\BonusHybrid\Service\IPeriod $callBonusPeriod,
        \Praxigento\BonusHybrid\Service\Calc\Sub\Db $subDb,
        \Praxigento\BonusHybrid\Service\Calc\Sub\Calc $subCalc,
        \Praxigento\BonusHybrid\Service\Calc\Sub\CompressOi $subCompressOi,
        \Praxigento\BonusHybrid\Service\Calc\Sub\Pto $subPto
    ) {
        parent::__construct($logger, $manObj);
        $this->_toolPeriod = $toolPeriod;
        $this->_toolScheme = $toolScheme;
        $this->_manTrans = $manTrans;
        $this->_callAcc = $callAcc;
        $this->_callPeriod = $callBonusPeriod;
        $this->_subDb = $subDb;
        $this->_subCalc = $subCalc;
        $this->subCompressOi = $subCompressOi;
        $this->subPto = $subPto;
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

    public function bonusInfinity(Request\BonusInfinity $request)
    {
        $result = new Response\BonusInfinity();
        $scheme = $this->_getCalculationsScheme($request->getScheme());
        $datePerformed = $request->getDatePerformed();
        $dateApplied = $request->getDateApplied();
        $this->logger->info("'Infinity Bonus' calculation is started ($scheme scheme).");
        if ($scheme == Def::SCHEMA_EU) {
            $calcTypeBase = Cfg::CODE_TYPE_CALC_COMPRESS_PHASE2_EU;
            $calcType = Cfg::CODE_TYPE_CALC_BONUS_INFINITY_EU;
        } else {
            $calcTypeBase = Cfg::CODE_TYPE_CALC_COMPRESS_PHASE2_DEF;
            $calcType = Cfg::CODE_TYPE_CALC_BONUS_INFINITY_DEF;
        }
        $reqGetPeriod = new PeriodGetForDependentCalcRequest();
        $reqGetPeriod->setBaseCalcTypeCode($calcTypeBase);
        $reqGetPeriod->setDependentCalcTypeCode($calcType);
        $respGetPeriod = $this->_callPeriod->getForDependentCalc($reqGetPeriod);
        if ($respGetPeriod->isSucceed()) {
            $def = $this->_manTrans->begin();
            try {
                /* working vars */
                $thisPeriodData = $respGetPeriod->getDependentPeriodData();
                $thisPeriodId = $thisPeriodData->getId();
                $thisCalcData = $respGetPeriod->getDependentCalcData();
                $thisCalcId = $thisCalcData->getId();
                $basePeriodData = $respGetPeriod->getBasePeriodData();
                $baseDsBegin = $basePeriodData->getDstampBegin();
                $baseDsEnd = $basePeriodData->getDstampEnd();
                $baseCalcData = $respGetPeriod->getBaseCalcData();
                $baseCalcId = $baseCalcData->getId();
                /* calculation itself */
                $this->logger->info("Processing period #$thisPeriodId ($baseDsBegin-$baseDsEnd)");
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
                $this->_manTrans->commit($def);
                $result->markSucceed();
                $result->setPeriodId($thisPeriodId);
                $result->setCalcId($thisCalcId);
            } finally {
                $this->_manTrans->end($def);
            }
        }
        $this->logMemoryUsage();
        $this->logger->info("'Infinity Bonus' calculation is completed.");
        return $result;
    }

}