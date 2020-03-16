<?php
/**
 * Authors: Alex Gusev <alex@flancer64.com>
 * Since: 2020
 */

namespace Praxigento\BonusHybrid\Service\Calc\SignUp;

use Praxigento\BonusBase\Api\Service\Period\Calc\Get\Dependent\Request as AGetPeriodRequest;
use Praxigento\BonusBase\Api\Service\Period\Calc\Get\Dependent\Response as AGetPeriodResponse;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Service\Calc\SignUp\Credit\A\Repo\Query\GetCustomers as QGetCust;

/**
 * Wallets credits calculation for "Sign Up" bonus.
 */
class Credit
{
    /** @var \Praxigento\BonusHybrid\Service\Calc\SignUp\Credit\A\ProcessCredits */
    private $aProcess;
    /** @var \Praxigento\BonusBase\Repo\Dao\Calculation */
    private $daoCalc;
    /** @var  \Praxigento\Core\Api\Helper\Period */
    private $hlpPeriod;
    /** @var \Psr\Log\LoggerInterface */
    private $logger;
    /** @var \Praxigento\BonusHybrid\Service\Calc\SignUp\Credit\A\Repo\Query\GetCustomers */
    private $qGetCust;
    /** @var \Praxigento\BonusBase\Api\Service\Period\Calc\Get\Dependent */
    private $servPeriodGet;

    public function __construct(
        \Praxigento\Core\Api\App\Logger\Main $logger,
        \Praxigento\Core\Api\Helper\Period $hlpPeriod,
        \Praxigento\BonusBase\Repo\Dao\Calculation $daoCalc,
        \Praxigento\BonusBase\Api\Service\Period\Calc\Get\Dependent $servPeriodGet,
        \Praxigento\BonusHybrid\Service\Calc\SignUp\Credit\A\Repo\Query\GetCustomers $qGetCust,
        \Praxigento\BonusHybrid\Service\Calc\SignUp\Credit\A\ProcessCredits $aProcess
    ) {
        $this->logger = $logger;
        $this->hlpPeriod = $hlpPeriod;
        $this->daoCalc = $daoCalc;
        $this->servPeriodGet = $servPeriodGet;
        $this->qGetCust = $qGetCust;
        $this->aProcess = $aProcess;
    }

    /**
     * Wallets credits calculation for "Sign Up" bonus.
     *
     * @param \Praxigento\BonusHybrid\Service\Calc\SignUp\Credit\Request $request
     * @return \Praxigento\BonusHybrid\Service\Calc\SignUp\Credit\Response
     */
    public function exec($request)
    {
        $result = new \Praxigento\BonusHybrid\Service\Calc\SignUp\Credit\Response();
        /**
         * perform processing
         */
        /* get related calculations */
        /**
         * @var \Praxigento\BonusBase\Repo\Data\Period $creditPeriod
         * @var \Praxigento\BonusBase\Repo\Data\Calculation $creditCalc
         * @var \Praxigento\BonusBase\Repo\Data\Calculation $debitCalc
         * @var \Praxigento\BonusBase\Repo\Data\Calculation $compressCalc
         */
        [$creditPeriod, $creditCalc, $debitCalc, $compressCalc] = $this->getCalcData();
        $this->logger->info("'Sign Up Bonus Credit' calc is started.");
        $calcState = $creditCalc->getState();
        $debitCalcId = $debitCalc->getId();
        $compressCalcId = $compressCalc->getId();
        if ($calcState != Cfg::CALC_STATE_COMPLETE) {
            $periodId = $creditPeriod->getId();
            $calcId = $creditCalc->getId();
            $periodBegin = $creditPeriod->getDstampBegin();
            $periodEnd = $creditPeriod->getDstampEnd();
            $this->logger->info("Processing period #$periodId ($periodBegin-$periodEnd), Sign Up Bonus Credit calculation #$calcId ($calcState).");
            /* get customers & sales data related to the bonus */
            $items = $this->getCustomers($debitCalcId, $compressCalcId);
            $total = count($items);
            $this->logger->info("There are '$total' orders to be processed in Sign Up Bonus Credit calculation.");
            /* create bonus credit operation */
            $dateApplied = $this->hlpPeriod->getTimestampLastSecond($periodEnd);
            $this->aProcess->exec($items, $dateApplied, $calcId);
            /* mark this calculation complete */
            $this->daoCalc->markComplete($calcId);
            /* mark process as successful */
            $result->markSucceed();
        }


        $this->logger->info("'Sign Up Bonus Credit' calc is completed.");
        return $result;
    }

    /**
     * Get data for related calculations.
     *
     * @return array [$creditPeriod, $creditCalc, $debitCalc, $compressCalc]
     */
    private function getCalcData()
    {
        /**
         * Get period & calc data.
         */
        $req = new AGetPeriodRequest();
        $req->setBaseCalcTypeCode(Cfg::CODE_TYPE_CALC_BONUS_SIGN_UP_DEBIT);
        $req->setDepCalcTypeCode(Cfg::CODE_TYPE_CALC_BONUS_SIGN_UP_CREDIT);
        /** @var AGetPeriodResponse $resp */
        $resp = $this->servPeriodGet->exec($req);
        /** @var \Praxigento\BonusBase\Repo\Data\Period $creditPeriod */
        $creditPeriod = $resp->getDepPeriodData();
        /** @var \Praxigento\BonusBase\Repo\Data\Calculation $creditCalc */
        $creditCalc = $resp->getDepCalcData();
        /** @var \Praxigento\BonusBase\Repo\Data\Calculation $debitCalc */
        $debitCalc = $resp->getBaseCalcData();
        /**
         * Get period and calc data for compression calc (basic for TV volumes).
         */
        $req = new AGetPeriodRequest();
        $req->setBaseCalcTypeCode(Cfg::CODE_TYPE_CALC_COMPRESS_PHASE1);
        $req->setDepCalcTypeCode(Cfg::CODE_TYPE_CALC_BONUS_SIGN_UP_CREDIT);
        $req->setDepIgnoreComplete(true);
        /** @var AGetPeriodResponse $resp */
        $resp = $this->servPeriodGet->exec($req);
        /** @var \Praxigento\BonusBase\Repo\Data\Calculation $compressCalc */
        $compressCalc = $resp->getBaseCalcData();
        /**
         * Compose result.
         */
        return [$creditPeriod, $creditCalc, $debitCalc, $compressCalc];
    }

    /**
     * Get Sign Up debit data (customers, sales, downline paths).
     *
     * @param int $calcIdDebit
     * @param int $calcIdCompress
     * @return array
     */
    private function getCustomers($calcIdDebit, $calcIdCompress)
    {
        $query = $this->qGetCust->build();
        $conn = $query->getConnection();
        $bind = [
            QGetCust::BND_CALC_ID_DEBIT => $calcIdDebit,
            QGetCust::BND_CALC_ID_COMPRESS => $calcIdCompress
        ];
        return $conn->fetchAll($query, $bind);
    }
}
