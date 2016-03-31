<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\Bonus\Hybrid\Lib\Service\Period\Sub;

use Flancer32\Lib\DataObject;
use Praxigento\Accounting\Lib\Entity\Account;
use Praxigento\Accounting\Lib\Entity\Transaction;
use Praxigento\Accounting\Lib\Entity\Type\Asset as TypeAsset;
use Praxigento\Bonus\Base\Lib\Entity\Calculation;
use Praxigento\Bonus\Base\Lib\Entity\Period;
use Praxigento\Bonus\Base\Lib\Service\Period\Request\GetLatest as BonusBasePeriodGetLatestRequest;
use Praxigento\Bonus\Base\Lib\Service\Type\Calc\Request\GetByCode as TypeCalcRequestGetByCode;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\Core\Lib\Service\Repo\Request\AddEntity as RepoAddEntityRequest;

class Db extends \Praxigento\Core\Lib\Service\Base\Sub\Db {
    const DATA_CALC = 'calc';
    const DATA_PERIOD = 'period';
    /**
     * @var \Praxigento\Bonus\Base\Lib\Service\IPeriod
     */
    private $_callBonusBasePeriod;
    /**
     * @var \Praxigento\Bonus\Base\Lib\Service\ITypeCalc
     */
    private $_callTypeCalc;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Praxigento\Core\Lib\Context\IDbAdapter $dba,
        \Praxigento\Core\Lib\IToolbox $toolbox,
        \Praxigento\Core\Lib\Service\IRepo $callRepo,
        \Praxigento\Bonus\Base\Lib\Service\IPeriod $callBonusBasePeriod,
        \Praxigento\Bonus\Base\Lib\Service\ITypeCalc $callTypeCalc
    ) {
        parent::__construct($logger, $dba, $toolbox, $callRepo);
        $this->_callBonusBasePeriod = $callBonusBasePeriod;
        $this->_callTypeCalc = $callTypeCalc;
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
    public function addNewPeriodAndCalc($calcTypeId, $dsBegin, $dsEnd) {
        $result = new DataObject();
        /* add new period */
        $periodData = [
            Period::ATTR_CALC_TYPE_ID => $calcTypeId,
            Period::ATTR_DSTAMP_BEGIN => $dsBegin,
            Period::ATTR_DSTAMP_END   => $dsEnd
        ];
        $reqAdd = new RepoAddEntityRequest(Period::ENTITY_NAME, $periodData);
        $respAdd = $this->_callRepo->addEntity($reqAdd);
        $periodId = $respAdd->getIdInserted();
        $this->_logger->info("New period #$periodId for calculation type #$calcTypeId is registered ($dsBegin-$dsEnd).");
        $periodData[Period::ATTR_ID] = $periodId;
        $result->setData(self::DATA_PERIOD, $periodData);
        /* add related calculation */
        $dateStarted = $this->_toolbox->getDate()->getUtcNowForDb();
        $calcData = [
            Calculation::ATTR_PERIOD_ID    => $periodId,
            Calculation::ATTR_DATE_STARTED => $dateStarted,
            Calculation::ATTR_DATE_ENDED   => null,
            Calculation::ATTR_STATE        => Cfg::CALC_STATE_STARTED
        ];
        $reqAdd = new RepoAddEntityRequest(Calculation::ENTITY_NAME, $calcData);
        $respAdd = $this->_callRepo->addEntity($reqAdd);
        $calcId = $respAdd->getIdInserted();
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
    public function getCalcIdByCode($calcCode) {
        $reqTypeCalc = new TypeCalcRequestGetByCode($calcCode);
        $respTypeCalc = $this->_callTypeCalc->getByCode($reqTypeCalc);
        $result = $respTypeCalc->getId();
        return $result;
    }

    /**
     * Return timestamp for the first transaction related to PV.
     */
    public function getFirstDateForPvTransactions() {
        $asAcc = 'paa';
        $asTrans = 'pat';
        $asType = 'pata';
        $tblAcc = $this->_getTableName(Account::ENTITY_NAME);
        $tblTrans = $this->_getTableName(Transaction::ENTITY_NAME);
        $tblType = $this->_getTableName(TypeAsset::ENTITY_NAME);
        // SELECT FROM prxgt_acc_transaction pat
        $query = $this->_getConn()->select();
        $query->from([ $asTrans => $tblTrans ], [ Transaction::ATTR_DATE_APPLIED ]);
        // LEFT JOIN prxgt_acc_account paa ON paa.id = pat.debit_acc_id
        $on = $asAcc . '.' . Account::ATTR_ID . '=' . $asTrans . '.' . Transaction::ATTR_DEBIT_ACC_ID;
        $query->join([ $asAcc => $tblAcc ], $on, null);
        // LEFT JOIN prxgt_acc_type_asset pata ON paa.asset_type_id = pata.id
        $on = $asAcc . '.' . Account::ATTR_ASSET_TYPE__ID . '=' . $asType . '.' . TypeAsset::ATTR_ID;
        $query->join([ $asType => $tblType ], $on, null);
        // WHERE
        $where = $asType . '.' . TypeAsset::ATTR_CODE . '=' . $this->_getConn()->quote(Cfg::CODE_TYPE_ASSET_PV);
        $query->where($where);
        // ORDER & LIMIT
        $query->order($asTrans . '.' . Transaction::ATTR_DATE_APPLIED . ' ASC');
        $query->limit(1);
        // $sql = (string)$query;
        $result = $this->_getConn()->fetchOne($query);
        return $result;
    }

    /**
     * @param $calcTypeId
     *
     * @return \Praxigento\Bonus\Base\Lib\Service\Period\Response\GetLatest
     */
    public function getLastPeriodData($calcTypeId) {
        $reqLastPeriod = new BonusBasePeriodGetLatestRequest();
        $reqLastPeriod->setCalcTypeId($calcTypeId);
        $reqLastPeriod->setShouldGetLatestCalc(true);
        $result = $this->_callBonusBasePeriod->getLatest($reqLastPeriod);
        return $result;
    }
}