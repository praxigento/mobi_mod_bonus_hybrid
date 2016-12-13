<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\Bonus\Hybrid\Lib\Service\Period\Sub;

use Flancer32\Lib\DataObject;
use Praxigento\Accounting\Data\Entity\Account;
use Praxigento\Accounting\Data\Entity\Transaction;
use Praxigento\Accounting\Data\Entity\Type\Asset as TypeAsset;
use Praxigento\BonusBase\Data\Entity\Calculation;
use Praxigento\BonusBase\Data\Entity\Period;
use Praxigento\BonusBase\Service\Period\Request\GetLatest as BonusBasePeriodGetLatestRequest;
use Praxigento\BonusHybrid\Config as Cfg;

class Db
{
    const DATA_CALC = 'calc';
    const DATA_PERIOD = 'period';
    /** @var \Praxigento\BonusBase\Service\IPeriod */
    protected $_callBonusBasePeriod;
    /** @var \Magento\Framework\DB\Adapter\AdapterInterface */
    protected $_conn;
    /** @var \Psr\Log\LoggerInterface */
    protected $_logger;
    /** @var \Praxigento\Core\Repo\IGeneric */
    protected $_repoBasic;
    /** @var \Praxigento\BonusBase\Repo\Entity\Type\ICalc */
    protected $_repoTypeCalc;
    /** @var \Magento\Framework\App\ResourceConnection */
    protected $_resource;
    /** @var  \Praxigento\Core\Tool\IDate */
    protected $_toolDate;

    public function __construct(
        \Praxigento\Core\Fw\Logger\App $logger,
        \Magento\Framework\App\ResourceConnection $resource,
        \Praxigento\Core\Tool\IDate $toolDate,
        \Praxigento\Core\Repo\IGeneric $repoBasic,
        \Praxigento\BonusBase\Service\IPeriod $callBonusBasePeriod,
        \Praxigento\BonusBase\Repo\Entity\Type\ICalc $repoTypeCalc
    ) {
        $this->_logger = $logger;
        $this->_resource = $resource;
        $this->_conn = $resource->getConnection();
        $this->_toolDate = $toolDate;
        $this->_repoBasic = $repoBasic;
        $this->_callBonusBasePeriod = $callBonusBasePeriod;
        $this->_repoTypeCalc = $repoTypeCalc;
    }

    /**
     * Create new period record and related calculation record.
     *
     * @param $calcTypeId
     * @param $dsBegin
     * @param $dsEnd
     *
     * @return DataObject
     */
    public function addNewPeriodAndCalc($calcTypeId, $dsBegin, $dsEnd)
    {
        $result = new DataObject();
        /* add new period */
        $periodData = [
            Period::ATTR_CALC_TYPE_ID => $calcTypeId,
            Period::ATTR_DSTAMP_BEGIN => $dsBegin,
            Period::ATTR_DSTAMP_END => $dsEnd
        ];
        $periodId = $this->_repoBasic->addEntity(Period::ENTITY_NAME, $periodData);
        $this->_logger->info("New period #$periodId for calculation type #$calcTypeId is registered ($dsBegin-$dsEnd).");
        $periodData[Period::ATTR_ID] = $periodId;
        $result->setData(self::DATA_PERIOD, $periodData);
        /* add related calculation */
        $dateStarted = $this->_toolDate->getUtcNowForDb();
        $calcData = [
            Calculation::ATTR_PERIOD_ID => $periodId,
            Calculation::ATTR_DATE_STARTED => $dateStarted,
            Calculation::ATTR_DATE_ENDED => null,
            Calculation::ATTR_STATE => Cfg::CALC_STATE_STARTED
        ];
        $calcId = $this->_repoBasic->addEntity(Calculation::ENTITY_NAME, $calcData);
        $this->_logger->info("New calculation #$calcId for period #$periodId is registered.");
        $calcData[Calculation::ATTR_ID] = $calcId;
        $result->setData(self::DATA_CALC, $calcData);
        return $result;
    }

    /**
     * @param $calcCode
     *
     * @return int|null
     */
    public function getCalcIdByCode($calcCode)
    {
        $result = $this->_repoTypeCalc->getIdByCode($calcCode);
        return $result;
    }

    /**
     * Return timestamp for the first transaction related to PV.
     */
    public function getFirstDateForPvTransactions()
    {
        $asAcc = 'paa';
        $asTrans = 'pat';
        $asType = 'pata';
        $tblAcc = $this->_resource->getTableName(Account::ENTITY_NAME);
        $tblTrans = $this->_resource->getTableName(Transaction::ENTITY_NAME);
        $tblType = $this->_resource->getTableName(TypeAsset::ENTITY_NAME);
        // SELECT FROM prxgt_acc_transaction pat
        $query = $this->_conn->select();
        $query->from([$asTrans => $tblTrans], [Transaction::ATTR_DATE_APPLIED]);
        // LEFT JOIN prxgt_acc_account paa ON paa.id = pat.debit_acc_id
        $on = $asAcc . '.' . Account::ATTR_ID . '=' . $asTrans . '.' . Transaction::ATTR_DEBIT_ACC_ID;
        $query->join([$asAcc => $tblAcc], $on, null);
        // LEFT JOIN prxgt_acc_type_asset pata ON paa.asset_type_id = pata.id
        $on = $asAcc . '.' . Account::ATTR_ASSET_TYPE_ID . '=' . $asType . '.' . TypeAsset::ATTR_ID;
        $query->join([$asType => $tblType], $on, null);
        // WHERE
        $where = $asType . '.' . TypeAsset::ATTR_CODE . '=' . $this->_conn->quote(Cfg::CODE_TYPE_ASSET_PV);
        $query->where($where);
        // ORDER & LIMIT
        $query->order($asTrans . '.' . Transaction::ATTR_DATE_APPLIED . ' ASC');
        $query->limit(1);
        // $sql = (string)$query;
        $result = $this->_conn->fetchOne($query);
        return $result;
    }

    /**
     * @param $calcTypeId
     *
     * @return \Praxigento\BonusBase\Service\Period\Response\GetLatest
     */
    public function getLastPeriodData($calcTypeId)
    {
        $reqLastPeriod = new BonusBasePeriodGetLatestRequest();
        $reqLastPeriod->setCalcTypeId($calcTypeId);
        $result = $this->_callBonusBasePeriod->getLatest($reqLastPeriod);
        return $result;
    }
}