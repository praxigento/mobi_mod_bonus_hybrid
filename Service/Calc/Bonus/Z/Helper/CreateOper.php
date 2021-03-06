<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Bonus\Z\Helper;

use Praxigento\BonusHybrid\Service\Calc\Bonus\Z\Helper\CreateOper\Result as DResult;


/**
 * Create bonus operation using transaction and period data and operation type.
 */
class CreateOper
{
    /** @var \Praxigento\Accounting\Service\Operation\Create */
    private $callOper;
    /** @var \Praxigento\Core\Api\Helper\Date */
    private $hlpDate;

    public function __construct(
        \Praxigento\Core\Api\Helper\Date $hlpDate,
        \Praxigento\Accounting\Service\Operation\Create $callOper
    )
    {
        $this->hlpDate = $hlpDate;
        $this->callOper = $callOper;
    }

    /**
     * @param string $calcTypeCode
     * @param \Praxigento\Accounting\Repo\Data\Transaction[] $trans
     * @param \Praxigento\BonusBase\Repo\Data\Period $period
     * @return \Praxigento\BonusHybrid\Service\Calc\Bonus\Z\Helper\CreateOper\Result
     */
    public function exec($calcTypeCode, $trans, $period)
    {
        $result = new DResult();

        $dsBegin = $period->getDstampBegin();
        $dsEnd = $period->getDstampEnd();
        $datePerformed = $this->hlpDate->getUtcNowForDb();
        $req = new \Praxigento\Accounting\Api\Service\Operation\Create\Request();
        $req->setOperationTypeCode($calcTypeCode);
        $req->setDatePerformed($datePerformed);
        $req->setTransactions($trans);
        $note = "$calcTypeCode ($dsBegin-$dsEnd)";
        $req->setOperationNote($note);
        /* add key to link newly created transaction IDs with donators */
        $req->setAsTransRef(\Praxigento\BonusHybrid\Service\Calc\Bonus\Z\Helper\PrepareTrans::REF_DONATOR_ID);
        $resp = $this->callOper->exec($req);
        $operId = $resp->getOperationId();
        $transIds = $resp->getTransactionsIds();
        $result->setOperationId($operId);
        $result->setTransactionsIds($transIds);

        return $result;
    }

}