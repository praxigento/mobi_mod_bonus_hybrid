<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Forecast;

use Praxigento\BonusHybrid\Defaults as Def;
use Praxigento\Downline\Repo\Query\Snap\OnDate\Builder as QBSnapOnDate;
use Praxigento\Downline\Repo\Query\Snap\OnDate\ForDcp\Builder as QBForDcp;

/**
 * Collect data and compose array of \Praxigento\BonusHybrid\Repo\Data\Entity\Actual\Downline\Plain entities to populate
 * with additional values and to save in the end.
 */
class GetDownline
{
    const CTX_DATE_ON = 'dateOn';

    /** @var \Praxigento\Downline\Repo\Query\Snap\OnDate\ForDcp\Builder */
    protected $qbldDcpSnap;
    /** @var \Praxigento\Downline\Repo\Query\Snap\OnDate\Builder */
    protected $qbuildSnapOnDate;

    public function __construct(
        \Praxigento\Downline\Repo\Query\Snap\OnDate\Builder $qbuildSnapOnDate,
        \Praxigento\Downline\Repo\Query\Snap\OnDate\ForDcp\Builder $qbldDcpSnap
    ) {
        $this->qbuildSnapOnDate = $qbuildSnapOnDate;
        $this->qbldDcpSnap = $qbldDcpSnap;
    }

    /**
     * @param \Flancer32\Lib\Data $ctx
     * @return \Praxigento\BonusHybrid\Repo\Data\Entity\Actual\Downline\Plain[]
     */
    public function exec(\Flancer32\Lib\Data $ctx)
    {
        $dateOn = $ctx->get(self::CTX_DATE_ON);

        $query = $this->qbldDcpSnap->getSelectQuery($this->qbuildSnapOnDate);
        $conn = $query->getConnection();
        $bind = [QBSnapOnDate::BIND_ON_DATE => $dateOn];
        $rows = $conn->fetchAll($query, $bind);
        $result = [];
        foreach ($rows as $row) {
            /* extract repo data */
            $customerId = $row[QBSnapOnDate::A_CUST_ID];
            $mlmId = $row[QBForDcp::A_MLM_ID];
            $email = $row[QBForDcp::A_EMAIL];
            $nameFirst = trim($row[QBForDcp::A_NAME_FIRST]);
            $nameMiddle = trim($row[QBForDcp::A_NAME_MIDDLE]);
            $nameLast = trim($row[QBForDcp::A_NAME_LAST]);
            $name = "$nameFirst $nameMiddle $nameLast";
            $name = str_replace('  ', ' ', $name);
            $parentId = $row[QBSnapOnDate::A_PARENT_ID];
            $depth = $row[QBSnapOnDate::A_DEPTH];
            $path = $row[QBSnapOnDate::A_PATH];
            /* prepare result data object */
            $item = new \Praxigento\BonusHybrid\Repo\Data\Entity\Actual\Downline\Plain();
            $item->setCustomerRef($customerId);
            $item->setMlmId($mlmId);
            $item->setEmail($email);
            $item->setName($name);
            $item->setParentRef($parentId);
            $item->setDepth($depth);
            $item->setPath($path);
            /* init PV/TV/OV */
            $item->setPv(0);
            $item->setTv(0);
            $item->setOv(0);
            /* init ranks */
            $item->setRankCode(Def::RANK_DISTRIBUTOR);
            $item->setUnqMonths(0);
            $result[$customerId] = $item;
        }
        return $result;
    }
}