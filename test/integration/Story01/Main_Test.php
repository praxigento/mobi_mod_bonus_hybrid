<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\Bonus\Hybrid\Lib\Test\Story01;

use Praxigento\Accounting\Lib\Entity\Account;
use Praxigento\Accounting\Lib\Entity\Transaction;
use Praxigento\Accounting\Lib\Service\Account\Request\GetRepresentative as AccGetRepresentativeRequest;
use Praxigento\Accounting\Lib\Service\Operation\Request\Add as AccOperationAddRequest;
use Praxigento\Accounting\Lib\Service\Type\Asset\Request\GetByCode as AccTypeAssetGetByCodeRequest;
use Praxigento\Bonus\Base\Lib\Entity\Calculation;
use Praxigento\Bonus\Base\Lib\Entity\Level;
use Praxigento\Bonus\Base\Lib\Entity\Period;
use Praxigento\Bonus\Base\Lib\Service\Type\Calc\Request\GetByCode as BonusTypeCalcGetByCodeRequest;
use Praxigento\Bonus\Hybrid\Lib\Defaults as Def;
use Praxigento\Bonus\Hybrid\Lib\Service\Calc\Request\BonusCourtesy as BonusCalcCourtesyBonusRequest;
use Praxigento\Bonus\Hybrid\Lib\Service\Calc\Request\BonusPersonal as BonusCalcPersonalBonusRequest;
use Praxigento\Bonus\Hybrid\Lib\Service\Calc\Request\BonusTeam as BonusCalcTeamBonusRequest;
use Praxigento\Bonus\Hybrid\Lib\Service\Calc\Request\CompressPtc as BonusCalcPvCompressionRequest;
use Praxigento\Bonus\Hybrid\Lib\Service\Calc\Request\PvWriteOff as BonusCalcPvWriteOffRequest;
use Praxigento\Bonus\Hybrid\Lib\Service\Calc\Request\ValueOv as BonusCalcOvCompressionRequest;
use Praxigento\Bonus\Hybrid\Lib\Service\Calc\Request\ValueTv as BonusCalcTvCompressionRequest;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\Core\Lib\Context;
use Praxigento\Core\Lib\Service\Repo\Request\AddEntity as RepoAddEntityRequest;
use Praxigento\Core\Lib\Service\Repo\Request\GetEntities as RepoGetEntitiesRequest;
use Praxigento\Core\Lib\Service\Repo\Request\GetEntityByPk as RepoGetByPkRequest;
use Praxigento\Core\Lib\Test\BaseIntegrationTest;
use Praxigento\Pv\Lib\Service\Transfer\Request\CreditToCustomer as PvTransferCreditToCustomerRequest;

include_once(__DIR__ . '/../phpunit_bootstrap.php');

class Main_IntegrationTest extends BaseIntegrationTest
{
    const COURTESY_BONUS_PERCENT = 0.05;
    const DATE_FEB_PERIOD_BEGIN = '20150201';
    const DATE_FEB_PERIOD_END = '20150228';
    const DATE_JAN_PERIOD_END = '20150131';
    const DATE_MAR_PERIOD_BEGIN = '20150301';
    const DATE_PERIOD_BEGIN = '20150101';
    const PV_QUALIFICATION_LEVEL_DEF = 50;
    const PV_QUALIFICATION_LEVEL_EU = 100;
    /** @var \Praxigento\Accounting\Lib\Service\IAccount */
    private $_callAccAccount;
    /** @var \Praxigento\Accounting\Lib\Service\IOperation */
    private $_callAccOperation;
    /** @var \Praxigento\Accounting\Lib\Service\ITypeAsset */
    private $_callAccTypeAsset;
    /** @var \Praxigento\Bonus\Base\Lib\Service\ITypeCalc */
    private $_callBonusTypeCalc;
    /** @var \Praxigento\Bonus\Hybrid\Lib\Service\ICalc */
    private $_callCalc;
    /** @var \Praxigento\Bonus\Hybrid\Lib\Service\IPeriod */
    private $_callPeriod;
    /** @var  \Praxigento\Pv\Lib\Service\ITransfer */
    private $_callPvTransfer;
    /** @var \Praxigento\Core\Lib\Service\IRepo */
    private $_callRepo;

    public function __construct()
    {
        parent::__construct();
        $this->_callAccAccount = $this->_obm->get(\Praxigento\Accounting\Lib\Service\IAccount::class);
        $this->_callAccOperation = $this->_obm->get(\Praxigento\Accounting\Lib\Service\IOperation::class);
        $this->_callAccTypeAsset = $this->_obm->get(\Praxigento\Accounting\Lib\Service\ITypeAsset::class);
        $this->_callBonusTypeCalc = $this->_obm->get(\Praxigento\Bonus\Base\Lib\Service\ITypeCalc::class);
        $this->_callCalc = $this->_obm->get(\Praxigento\Bonus\Hybrid\Lib\Service\ICalc::class);
        $this->_callPeriod = $this->_obm->get(\Praxigento\Bonus\Hybrid\Lib\Service\IPeriod::class);
        $this->_callPvTransfer = $this->_obm->get(\Praxigento\Pv\Lib\Service\ITransfer::class);
        $this->_callRepo = $this->_obm->get(\Praxigento\Core\Lib\Service\IRepo::class);
    }

    /**
     * Add PV to every customer. PV amount is equal to (350 - 25 * customer #) (in the tree, not Magento ID!).
     *
     * @param $dsBegin string date stamp for first day of the period (YYYYMMDD).
     */
    private function _addPvToCustomers($dsBegin)
    {
        $PV_STEP = 25;
        $PV_MAX = 350;
        $dsToday = $dsBegin;
        $reqAddPv = new PvTransferCreditToCustomerRequest();
        foreach ($this->_mapCustomerMageIdByIndex as $ref => $custId) {
            $pvToAdd = $PV_MAX - $ref * $PV_STEP;
            $ts = $this->_toolbox->getPeriod()->getTimestampTo($dsToday);
            $reqAddPv->setData(PvTransferCreditToCustomerRequest::TO_CUSTOMER_ID, $custId);
            $reqAddPv->setData(PvTransferCreditToCustomerRequest::VALUE, $pvToAdd);
            $reqAddPv->setData(PvTransferCreditToCustomerRequest::DATE_APPLIED, $ts);
            $respAddPv = $this->_callPvTransfer->creditToCustomer($reqAddPv);
            if ($respAddPv->isSucceed()) {
                $this->_logger->debug("'$pvToAdd' PV have been added to customer #$ref (mageID: $custId).");
            } else {
                $this->_logger->debug("Cannot add '$pvToAdd' PV to customer #$ref (mageID: $custId).");
            }
            $dsToday = $this->_toolbox->getPeriod()->getPeriodNext($dsToday);
        }
    }

    private function _calcBonusCourtesy($nextPeriodBegin, $expectedBegin, $expectedEnd)
    {
        /* perform operation by the first date of the next period */
        $datePerformed = $this->_toolbox->getPeriod()->getTimestampTo($nextPeriodBegin);
        $request = new BonusCalcCourtesyBonusRequest();
        $request->setDatePerformed($datePerformed);
        $request->setCourtesyBonusPercent(self::COURTESY_BONUS_PERCENT);
        $response = $this->_callCalc->bonusCourtesy($request);
        $this->assertTrue($response->isSucceed());
        $periodId = $response->getPeriodId();
        $calcId = $response->getCalcId();
        /* validate period */
        $this->assertNotNull($periodId);
        $reqGetEntity = new RepoGetByPkRequest(Period::ENTITY_NAME, [Period::ATTR_ID => $periodId]);
        $respGetEntity = $this->_callRepo->getEntityByPk($reqGetEntity);
        $this->assertEquals($expectedBegin, $respGetEntity->getData(Period::ATTR_DSTAMP_BEGIN));
        $this->assertEquals($expectedEnd, $respGetEntity->getData(Period::ATTR_DSTAMP_END));
        /* validate calculation */
        $this->assertNotNull($calcId);
        $reqGetEntity = new RepoGetByPkRequest(Calculation::ENTITY_NAME, [Calculation::ATTR_ID => $calcId]);
        $respGetEntity = $this->_callRepo->getEntityByPk($reqGetEntity);
        $this->assertEquals(Cfg::CALC_STATE_COMPLETE, $respGetEntity->getData(Calculation::ATTR_STATE));
        /* validate WALLET_ACTIVE balances */
        $this->_validateWalletsAfterCourtesy();
    }

    private function _calcBonusPersonal($nextPeriodBegin, $expectedBegin, $expectedEnd)
    {
        /* perform operation by the first date of the next period */
        $datePerformed = $this->_toolbox->getPeriod()->getTimestampTo($nextPeriodBegin);
        $request = new BonusCalcPersonalBonusRequest();
        $request->setDatePerformed($datePerformed);
        $response = $this->_callCalc->bonusPersonal($request);
        $this->assertTrue($response->isSucceed());
        $periodId = $response->getPeriodId();
        $calcId = $response->getCalcId();
        /* validate period */
        $this->assertNotNull($periodId);
        $reqGetEntity = new RepoGetByPkRequest(Period::ENTITY_NAME, [Period::ATTR_ID => $periodId]);
        $respGetEntity = $this->_callRepo->getEntityByPk($reqGetEntity);
        $this->assertEquals($expectedBegin, $respGetEntity->getData(Period::ATTR_DSTAMP_BEGIN));
        $this->assertEquals($expectedEnd, $respGetEntity->getData(Period::ATTR_DSTAMP_END));
        /* validate calculation */
        $this->assertNotNull($calcId);
        $reqGetEntity = new RepoGetByPkRequest(Calculation::ENTITY_NAME, [Calculation::ATTR_ID => $calcId]);
        $respGetEntity = $this->_callRepo->getEntityByPk($reqGetEntity);
        $this->assertEquals(Cfg::CALC_STATE_COMPLETE, $respGetEntity->getData(Calculation::ATTR_STATE));
        /* validate WALLET_ACTIVE balances */
        $this->_validateWalletsAfterPersonal();
    }

    private function _calcBonusTeam($nextPeriodBegin, $expectedBegin, $expectedEnd)
    {
        /* perform operation by the first date of the next period */
        $datePerformed = $this->_toolbox->getPeriod()->getTimestampTo($nextPeriodBegin);
        $request = new BonusCalcTeamBonusRequest();
        $request->setDatePerformed($datePerformed);
        $response = $this->_callCalc->bonusTeam($request);
        $this->assertTrue($response->isSucceed());
        $periodId = $response->getPeriodId();
        $calcId = $response->getCalcId();
        /* validate period */
        $this->assertNotNull($periodId);
        $reqGetEntity = new RepoGetByPkRequest(Period::ENTITY_NAME, [Period::ATTR_ID => $periodId]);
        $respGetEntity = $this->_callRepo->getEntityByPk($reqGetEntity);
        $this->assertEquals($expectedBegin, $respGetEntity->getData(Period::ATTR_DSTAMP_BEGIN));
        $this->assertEquals($expectedEnd, $respGetEntity->getData(Period::ATTR_DSTAMP_END));
        /* validate calculation */
        $this->assertNotNull($calcId);
        $reqGetEntity = new RepoGetByPkRequest(Calculation::ENTITY_NAME, [Calculation::ATTR_ID => $calcId]);
        $respGetEntity = $this->_callRepo->getEntityByPk($reqGetEntity);
        $this->assertEquals(Cfg::CALC_STATE_COMPLETE, $respGetEntity->getData(Calculation::ATTR_STATE));
        /* validate WALLET_ACTIVE balances */
        $this->_validateWalletsAfterTeam();
    }

    private function _calcCompressionPtc($nextPeriodBegin, $expectedBegin, $expectedEnd)
    {
        /* perform operation by the first date of the next period */
        $datePerformed = $this->_toolbox->getPeriod()->getTimestampTo($nextPeriodBegin);
        $request = new BonusCalcPvCompressionRequest();
        $request->setDatePerformed($datePerformed);
        $request->setQualificationLevels([
            Def::SCHEMA_DEFAULT => Def::PV_QUALIFICATION_LEVEL_DEF,
            Def::SCHEMA_EU => Def::PV_QUALIFICATION_LEVEL_EU
        ]);
        $response = $this->_callCalc->compressPtc($request);
        $this->assertTrue($response->isSucceed());
        $periodId = $response->getPeriodId();
        $calcId = $response->getCalcId();
        /* validate period */
        $this->assertNotNull($periodId);
        $reqGetEntity = new RepoGetByPkRequest(Period::ENTITY_NAME, [Period::ATTR_ID => $periodId]);
        $respGetEntity = $this->_callRepo->getEntityByPk($reqGetEntity);
        $this->assertEquals($expectedBegin, $respGetEntity->getData(Period::ATTR_DSTAMP_BEGIN));
        $this->assertEquals($expectedEnd, $respGetEntity->getData(Period::ATTR_DSTAMP_END));
        /* validate calculation */
        $this->assertNotNull($calcId);
        $reqGetEntity = new RepoGetByPkRequest(Calculation::ENTITY_NAME, [Calculation::ATTR_ID => $calcId]);
        $respGetEntity = $this->_callRepo->getEntityByPk($reqGetEntity);
        $this->assertEquals(Cfg::CALC_STATE_COMPLETE, $respGetEntity->getData(Calculation::ATTR_STATE));
    }

    private function _calcPersonalBonusBefore()
    {
        $request = new BonusCalcPersonalBonusRequest();
        $response = $this->_callCalc->bonusPersonal($request);
        $this->assertFalse($response->isSucceed());
        $periodId = $response->getPeriodId();
        $calcId = $response->getCalcId();
        /* validate calculation */
        $this->assertNull($periodId);
        $this->assertNull($calcId);
    }

    private function _calcPvWriteOffBefore()
    {
        $request = new BonusCalcPvWriteOffRequest();
        $response = $this->_callCalc->pvWriteOff($request);
        $this->assertTrue($response->isSucceed());
        $periodId = $response->getPeriodId();
        $calcId = $response->getCalcId();
        /* validate calculation */
        $this->assertNull($periodId);
        $this->assertNull($calcId);
    }

    private function _calcPvWriteOffPeriod($nextPeriodBegin, $expectedBegin, $expectedEnd)
    {
        /* perform operation by the first date of the next period */
        $datePerformed = $this->_toolbox->getPeriod()->getTimestampTo($nextPeriodBegin);
        $request = new BonusCalcPvWriteOffRequest();
        $request->setDatePerformed($datePerformed);
        $response = $this->_callCalc->pvWriteOff($request);
        $this->assertTrue($response->isSucceed());
        $periodId = $response->getPeriodId();
        $calcId = $response->getCalcId();
        /* validate period */
        $this->assertNotNull($periodId);
        $reqGetEntity = new RepoGetByPkRequest(Period::ENTITY_NAME, [Period::ATTR_ID => $periodId]);
        $respGetEntity = $this->_callRepo->getEntityByPk($reqGetEntity);
        $this->assertEquals($expectedBegin, $respGetEntity->getData(Period::ATTR_DSTAMP_BEGIN));
        $this->assertEquals($expectedEnd, $respGetEntity->getData(Period::ATTR_DSTAMP_END));
        /* validate calculation */
        $this->assertNotNull($calcId);
        $reqGetEntity = new RepoGetByPkRequest(Calculation::ENTITY_NAME, [Calculation::ATTR_ID => $calcId]);
        $respGetEntity = $this->_callRepo->getEntityByPk($reqGetEntity);
        $this->assertEquals(Cfg::CALC_STATE_COMPLETE, $respGetEntity->getData(Calculation::ATTR_STATE));
    }

    private function _calcValueOv($nextPeriodBegin, $expectedBegin, $expectedEnd)
    {
        /* perform operation by the first date of the next period */
        $datePerformed = $this->_toolbox->getPeriod()->getTimestampTo($nextPeriodBegin);
        $request = new BonusCalcOvCompressionRequest();
        $request->setDatePerformed($datePerformed);
        $response = $this->_callCalc->valueOv($request);
        $this->assertTrue($response->isSucceed());
        $periodId = $response->getPeriodId();
        $calcId = $response->getCalcId();
        /* validate period */
        $this->assertNotNull($periodId);
        $reqGetEntity = new RepoGetByPkRequest(Period::ENTITY_NAME, [Period::ATTR_ID => $periodId]);
        $respGetEntity = $this->_callRepo->getEntityByPk($reqGetEntity);
        $this->assertEquals($expectedBegin, $respGetEntity->getData(Period::ATTR_DSTAMP_BEGIN));
        $this->assertEquals($expectedEnd, $respGetEntity->getData(Period::ATTR_DSTAMP_END));
        /* validate calculation */
        $this->assertNotNull($calcId);
        $reqGetEntity = new RepoGetByPkRequest(Calculation::ENTITY_NAME, [Calculation::ATTR_ID => $calcId]);
        $respGetEntity = $this->_callRepo->getEntityByPk($reqGetEntity);
        $this->assertEquals(Cfg::CALC_STATE_COMPLETE, $respGetEntity->getData(Calculation::ATTR_STATE));
    }

    private function _calcValueTv($nextPeriodBegin, $expectedBegin, $expectedEnd)
    {
        /* perform operation by the first date of the next period */
        $datePerformed = $this->_toolbox->getPeriod()->getTimestampTo($nextPeriodBegin);
        $request = new BonusCalcTvCompressionRequest();
        $request->setDatePerformed($datePerformed);
        $response = $this->_callCalc->valueTv($request);
        $this->assertTrue($response->isSucceed());
        $periodId = $response->getPeriodId();
        $calcId = $response->getCalcId();
        /* validate period */
        $this->assertNotNull($periodId);
        $reqGetEntity = new RepoGetByPkRequest(Period::ENTITY_NAME, [Period::ATTR_ID => $periodId]);
        $respGetEntity = $this->_callRepo->getEntityByPk($reqGetEntity);
        $this->assertEquals($expectedBegin, $respGetEntity->getData(Period::ATTR_DSTAMP_BEGIN));
        $this->assertEquals($expectedEnd, $respGetEntity->getData(Period::ATTR_DSTAMP_END));
        /* validate calculation */
        $this->assertNotNull($calcId);
        $reqGetEntity = new RepoGetByPkRequest(Calculation::ENTITY_NAME, [Calculation::ATTR_ID => $calcId]);
        $respGetEntity = $this->_callRepo->getEntityByPk($reqGetEntity);
        $this->assertEquals(Cfg::CALC_STATE_COMPLETE, $respGetEntity->getData(Calculation::ATTR_STATE));
    }

    /**
     * Get all balances for WALLET_ACTIVE asset and then reset it to zero.
     */
    private function _resetWalletBalances()
    {
        /* get WALLET_ACTIVE asset ID */
        $respAssetTypeId = $this->_callAccTypeAsset->getByCode(new AccTypeAssetGetByCodeRequest(Cfg::CODE_TYPE_ASSET_WALLET_ACTIVE));
        $assetId = $respAssetTypeId->getId();
        /* get WALLET_ACTIVE representative account */
        $reqGetRepres = new AccGetRepresentativeRequest();
        $reqGetRepres->setAssetTypeCode(Cfg::CODE_TYPE_ASSET_WALLET_ACTIVE);
        $respGetRepres = $this->_callAccAccount->getRepresentative($reqGetRepres);
        $accountIdRepres = $respGetRepres->getData(Account::ATTR_ID);;
        /* get all customer balances for WALLET_ACTIVE and create transactions */
        $whereByAsset = Account::ATTR_ASSET_TYPE__ID . '=' . $assetId;
        $whereByRepres = Account::ATTR_ID . '<>' . $accountIdRepres;
        $reqGetBalances = new RepoGetEntitiesRequest(Account::ENTITY_NAME, "$whereByAsset AND $whereByRepres");
        $respGetEntities = $this->_callRepo->getEntities($reqGetBalances);
        $accounts = $respGetEntities->getData();
        $trans = [];
        $datePerformed = $this->_toolbox->getPeriod()->getTimestampTo(self::DATE_FEB_PERIOD_BEGIN);
        $dateApplied = $datePerformed;
        foreach ($accounts as $account) {
            $balance = $account[Account::ATTR_BALANCE];
            if ($balance != 0) {
                $tran = [
                    Transaction::ATTR_DEBIT_ACC_ID => $account[Account::ATTR_ID],
                    Transaction::ATTR_CREDIT_ACC_ID => $accountIdRepres,
                    Transaction::ATTR_VALUE => $account[Account::ATTR_BALANCE],
                    Transaction::ATTR_DATE_APPLIED => $dateApplied
                ];
                $trans[] = $tran;
            }
        }
        /* create operation to write off all WALLET_ACTIVE balances */
        $reqOper = new AccOperationAddRequest();
        $reqOper->setOperationTypeCode(Cfg::CODE_TYPE_OPER_WALLET_TRANSFER);
        $reqOper->setDatePerformed($datePerformed);
        $reqOper->setTransactions($trans);
        $respOper = $this->_callAccOperation->add($reqOper);
        $this->assertTrue($respOper->isSucceed());
    }

    private function _setBonusLevelsPersonal()
    {
        $resp = $this->_callBonusTypeCalc->getByCode(new BonusTypeCalcGetByCodeRequest(Cfg::CODE_TYPE_CALC_BONUS_PERSONAL_DEF));
        $calTypeId = $resp->getId();
        $data = [
            [Level::ATTR_CALC_TYPE_ID => $calTypeId, Level::ATTR_LEVEL => 0, Level::ATTR_PERCENT => 0],
            [Level::ATTR_CALC_TYPE_ID => $calTypeId, Level::ATTR_LEVEL => 50, Level::ATTR_PERCENT => 0.05],
            [Level::ATTR_CALC_TYPE_ID => $calTypeId, Level::ATTR_LEVEL => 100, Level::ATTR_PERCENT => 0.1]
        ];
        $req = new RepoAddEntityRequest(Level::ENTITY_NAME);
        foreach ($data as $item) {
            $req->setBind($item);
            $this->_callRepo->addEntity($req);
        }
        $this->_logger->debug("Personal Bonus levels are set.");
    }

    private function _setBonusLevelsTeam()
    {
        $resp = $this->_callBonusTypeCalc->getByCode(new BonusTypeCalcGetByCodeRequest(Cfg::CODE_TYPE_CALC_BONUS_TEAM_DEF));
        $calTypeId = $resp->getId();
        $data = [
            [Level::ATTR_CALC_TYPE_ID => $calTypeId, Level::ATTR_LEVEL => 0, Level::ATTR_PERCENT => 0],
            [Level::ATTR_CALC_TYPE_ID => $calTypeId, Level::ATTR_LEVEL => 500, Level::ATTR_PERCENT => 0.10],
            [Level::ATTR_CALC_TYPE_ID => $calTypeId, Level::ATTR_LEVEL => 1000, Level::ATTR_PERCENT => 0.15]
        ];
        $req = new RepoAddEntityRequest(Level::ENTITY_NAME);
        foreach ($data as $item) {
            $req->setBind($item);
            $this->_callRepo->addEntity($req);
        }
        $this->_logger->debug("Team Bonus levels are set.");
    }

    private function _validatePvAccsEmpty()
    {
        /* get Asset Type ID foe WALLET_ACTIVE */
        $respAssetTypeId = $this->_callAccTypeAsset->getByCode(new AccTypeAssetGetByCodeRequest(Cfg::CODE_TYPE_ASSET_PV));
        $assetTypeId = $respAssetTypeId->getId();
        /* get representative account for PV */
        $reqRepresAcc = new AccGetRepresentativeRequest();
        $reqRepresAcc->setAssetTypeId($assetTypeId);
        /* get data for PV accounts */
        $where = Account::ATTR_ASSET_TYPE__ID . '=' . $assetTypeId;
        $reqGetBalances = new RepoGetEntitiesRequest(Account::ENTITY_NAME, $where);
        $respGetBalances = $this->_callRepo->getEntities($reqGetBalances);
        $balanceData = $respGetBalances->getData();
        /* convert balances to form that is relative to customer index (not id) */
        foreach ($balanceData as $one) {
            $balance = $one[Account::ATTR_BALANCE];
            $this->assertEquals(0, $balance);
        }
    }

    private function _validateWalletsAfterCourtesy()
    {
        $EXPECT_REPRES_AMOUNT = -97.50;
        $EXPECT_BALANCE = [
            1 => 28.75,
            2 => 23.75,
            3 => 18.75,
            4 => 0,
            5 => 0,
            6 => 13.75,
            7 => 10.00,
            8 => 0,
            9 => 0,
            10 => 2.50,
            11 => 0,
            12 => 0,
            13 => 0
        ];
        /* get Asset Type ID foe WALLET_ACTIVE */
        $respAssetTypeId = $this->_callAccTypeAsset->getByCode(new AccTypeAssetGetByCodeRequest(Cfg::CODE_TYPE_ASSET_WALLET_ACTIVE));
        $assetTypeId = $respAssetTypeId->getId();
        /* get representative account for WALLET_ACTIVE */
        $reqRepresAcc = new AccGetRepresentativeRequest();
        $reqRepresAcc->setAssetTypeId($assetTypeId);
        $respRepresAcc = $this->_callAccAccount->getRepresentative($reqRepresAcc);
        $represAccId = $respRepresAcc->getData(Account::ATTR_ID);;
        /* get data for WALLET_ACTIVE accounts */
        $where = Account::ATTR_ASSET_TYPE__ID . '=' . $assetTypeId;
        $reqGetBalances = new RepoGetEntitiesRequest(Account::ENTITY_NAME, $where);
        $respGetBalances = $this->_callRepo->getEntities($reqGetBalances);
        $balanceData = $respGetBalances->getData();
        /* convert balances to form that is relative to customer index (not id) */
        foreach ($balanceData as $one) {
            $accId = $one[Account::ATTR_ID];
            $custId = $one[Account::ATTR_CUST_ID];
            $balance = $one[Account::ATTR_BALANCE];
            if ($accId == $represAccId) {
                $this->assertEquals($EXPECT_REPRES_AMOUNT, $balance);
            } else {
                /* get customer index by customer id */
                $index = $this->_mapCustomerIndexByMageId[$custId];
                $expBalance = $EXPECT_BALANCE[$index];
                $this->assertEquals($expBalance, $balance);
            }
        }
    }

    private function _validateWalletsAfterPersonal()
    {
        $EXPECT_REPRES_AMOUNT = -221.2500;
        $EXPECT_BALANCE = [
            1 => 32.50,
            2 => 30.00,
            3 => 27.50,
            4 => 25.00,
            5 => 22.50,
            6 => 20.00,
            7 => 17.50,
            8 => 15.00,
            9 => 12.50,
            10 => 12.50,
            11 => 3.75,
            12 => 2.50,
            13 => 0
        ];
        /* get Asset Type ID for WALLET_ACTIVE */
        $respAssetTypeId = $this->_callAccTypeAsset->getByCode(new AccTypeAssetGetByCodeRequest(Cfg::CODE_TYPE_ASSET_WALLET_ACTIVE));
        $assetTypeId = $respAssetTypeId->getId();
        /* get representative account for WALLET_ACTIVE */
        $reqRepresAcc = new AccGetRepresentativeRequest();
        $reqRepresAcc->setAssetTypeId($assetTypeId);
        $respRepresAcc = $this->_callAccAccount->getRepresentative($reqRepresAcc);
        $represAccId = $respRepresAcc->getData(Account::ATTR_ID);;
        /* get data for WALLET_ACTIVE accounts */
        $where = Account::ATTR_ASSET_TYPE__ID . '=' . $assetTypeId;
        $reqGetBalances = new RepoGetEntitiesRequest(Account::ENTITY_NAME, $where);
        $respGetBalances = $this->_callRepo->getEntities($reqGetBalances);
        $balanceData = $respGetBalances->getData();
        /* convert balances to form that is relative to customer index (not id) */
        foreach ($balanceData as $one) {
            $accId = $one[Account::ATTR_ID];
            $custId = $one[Account::ATTR_CUST_ID];
            $balance = $one[Account::ATTR_BALANCE];
            if ($accId == $represAccId) {
                $this->assertEquals($EXPECT_REPRES_AMOUNT, $balance);
            } else {
                /* get customer index by customer id */
                $index = $this->_mapCustomerIndexByMageId[$custId];
                $expBalance = $EXPECT_BALANCE[$index];
                $this->assertEquals($expBalance, $balance);
            }
        }
    }

    private function _validateWalletsAfterTeam()
    {
        $EXPECT_REPRES_AMOUNT = -6.25;
        $EXPECT_BALANCE = [
            1 => 0.00,
            2 => 0,
            3 => 6.25,
            4 => 0,
            5 => 0,
            6 => 0,
            7 => 0,
            8 => 0,
            9 => 0,
            10 => 0,
            11 => 0,
            12 => 0,
            13 => 0
        ];
        /* get Asset Type ID foe WALLET_ACTIVE */
        $respAssetTypeId = $this->_callAccTypeAsset->getByCode(new AccTypeAssetGetByCodeRequest(Cfg::CODE_TYPE_ASSET_WALLET_ACTIVE));
        $assetTypeId = $respAssetTypeId->getId();
        /* get representative account for WALLET_ACTIVE */
        $reqRepresAcc = new AccGetRepresentativeRequest();
        $reqRepresAcc->setAssetTypeId($assetTypeId);
        $respRepresAcc = $this->_callAccAccount->getRepresentative($reqRepresAcc);
        $represAccId = $respRepresAcc->getData(Account::ATTR_ID);
        /* get data for WALLET_ACTIVE accounts */
        $where = Account::ATTR_ASSET_TYPE__ID . '=' . $assetTypeId;
        $reqGetBalances = new RepoGetEntitiesRequest(Account::ENTITY_NAME, $where);
        $respGetBalances = $this->_callRepo->getEntities($reqGetBalances);
        $balanceData = $respGetBalances->getData();
        /* convert balances to form that is relative to customer index (not id) */
        foreach ($balanceData as $one) {
            $accId = $one[Account::ATTR_ID];
            $custId = $one[Account::ATTR_CUST_ID];
            $balance = $one[Account::ATTR_BALANCE];
            if ($accId == $represAccId) {
                $this->assertEquals($EXPECT_REPRES_AMOUNT, $balance);
            } else {
                /* get customer index by customer id */
                $index = $this->_mapCustomerIndexByMageId[$custId];
                $expBalance = $EXPECT_BALANCE[$index];
                $this->assertEquals($expBalance, $balance);
            }
        }
    }

    public function setUp()
    {
        parent::setUp();
        /* clear cached data */
        $this->_callAccAccount->cacheReset();
        $this->_callPvTransfer->cacheReset();
    }

    public function test_main()
    {
        $this->_logger->debug('Story01 in Hybrid Bonus Integration tests is started.');
        $this->_conn->beginTransaction();
        try {
            /* create test data */
            $this->_setBonusLevelsPersonal();
            $this->_setBonusLevelsTeam();
            /* create customers */
            $this->_createMageCustomers(13);
            $this->_createDownlineCustomers(self::DATE_PERIOD_BEGIN, true);
            $this->_createDownlineSnapshots(self::DATE_JAN_PERIOD_END);
            /* check periods before */
            $this->_calcPvWriteOffBefore();
            $this->_calcPersonalBonusBefore();
            /* add PV for "January" and calculate bonuses */
            $this->_addPvToCustomers(self::DATE_PERIOD_BEGIN);
            $this->_calcPvWriteOffPeriod(self::DATE_FEB_PERIOD_BEGIN, '20150101', '20150131');
            $this->_validatePvAccsEmpty();
            $this->_calcCompressionPtc(self::DATE_FEB_PERIOD_BEGIN, '20150101', '20150131');
            $this->_calcValueTv(self::DATE_FEB_PERIOD_BEGIN, '20150101', '20150131');
            $this->_calcValueOv(self::DATE_FEB_PERIOD_BEGIN, '20150101', '20150131');
            $this->_calcBonusPersonal(self::DATE_FEB_PERIOD_BEGIN, '20150101', '20150131');
            $this->_resetWalletBalances();
            $this->_calcBonusTeam(self::DATE_FEB_PERIOD_BEGIN, '20150101', '20150131');
            $this->_resetWalletBalances();
            $this->_calcBonusCourtesy(self::DATE_FEB_PERIOD_BEGIN, '20150101', '20150131');
            $this->_resetWalletBalances();
            /* add PV for "February" and calculate bonuses */
            $this->_addPvToCustomers(self::DATE_FEB_PERIOD_BEGIN);
            $this->_calcPvWriteOffPeriod(self::DATE_MAR_PERIOD_BEGIN, '20150201', '20150228');
            $this->_validatePvAccsEmpty();
            $this->_calcCompressionPtc(self::DATE_MAR_PERIOD_BEGIN, '20150201', '20150228');
            $this->_calcValueTv(self::DATE_MAR_PERIOD_BEGIN, '20150201', '20150228');
            $this->_calcValueOv(self::DATE_MAR_PERIOD_BEGIN, '20150201', '20150228');
            $this->_calcBonusPersonal(self::DATE_MAR_PERIOD_BEGIN, '20150201', '20150228');
            $this->_resetWalletBalances();
            $this->_calcBonusTeam(self::DATE_MAR_PERIOD_BEGIN, '20150201', '20150228');
            $this->_resetWalletBalances();
            $this->_calcBonusCourtesy(self::DATE_MAR_PERIOD_BEGIN, '20150201', '20150228');
            $this->_resetWalletBalances();
        } finally {
            // $this->_conn->commit();
            $this->_conn->rollBack();
        }
        $this->_logger->debug('Story01 in Hybrid Bonus Integration tests is completed, all transactions are rolled back.');
    }
}