<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\CompressPhase1;

use Praxigento\BonusHybrid\Repo\Data\Entity\Retro\Downline\Compressed\Phase1 as ECompressPhase1;
use Praxigento\Downline\Repo\Entity\Data\Customer as ECustomer;
use Praxigento\Downline\Repo\Query\Snap\OnDate\Builder as ASnap;

/**
 * Compression calculation itself.
 */
class Calc
{
    /** Add traits */
    use \Praxigento\BonusHybrid\Service\Calc\Traits\TMap {
        mapById as protected;
        mapByTeams as protected;
        mapByTreeDepthDesc as protected;
    }

    /** (IN) Calculation ID to reference in compressed PV transfers  */
    const CTX_CALC_ID = 'calcId';
    /** (OUT) Calculation results, data for compression table */
    const CTX_COMPRESSED = 'compressed';
    /** (IN)  Customers actual data */
    const CTX_DWNL_CUST = 'dwnlCust';
    /** (IN) Customers downline tree to the end of calculation period */
    const CTX_DWNL_SNAP = 'dwnlSnap';
    /** (IN) PV by customer id map */
    const CTX_PV = 'pv';
    /** (OUT)  Compressed PV transfers to save in DB */
    const CTX_PV_TRANSFERS = 'pvTransfers';

    /** @var    \Praxigento\Downline\Service\ISnap */
    protected $callDownlineSnap;
    /** @var \Praxigento\BonusHybrid\Helper\SignupDebit\GetCustomersIds */
    protected $hlpSignupDebitCust;
    /** @var  \Praxigento\BonusHybrid\Tool\IScheme */
    protected $toolScheme;
    /** @var \Praxigento\Downline\Tool\ITree */
    protected $toolTree;

    public function __construct(
        \Praxigento\BonusHybrid\Tool\IScheme $toolScheme,
        \Praxigento\Downline\Tool\ITree $toolTree,
        \Praxigento\BonusHybrid\Helper\SignupDebit\GetCustomersIds $hlpSignupDebitCust,
        \Praxigento\Downline\Service\ISnap $callDownlineSnap
    ) {
        $this->toolScheme = $toolScheme;
        $this->toolTree = $toolTree;
        $this->hlpSignupDebitCust = $hlpSignupDebitCust;
        $this->callDownlineSnap = $callDownlineSnap;
    }

    /**
     * @param array $compressionData array [$custId=>[$pv, $parentId], ... ] with compression data.
     * @return array
     */
    protected function composeTree($compressionData)
    {
        /* prepare request data: convert to [$customer=>parent, ... ] form */
        $converted = [];
        foreach ($compressionData as $custId => $data) {
            /* 0 - PV, 1 - parentId */
            $converted[$custId] = $data[1];
        }
        $req = new \Praxigento\Downline\Service\Snap\Request\ExpandMinimal();
        $req->setTree($converted);
        $resp = $this->callDownlineSnap->expandMinimal($req);
        unset($converted);
        $result = $resp->getSnapData();
        return $result;
    }

    public function exec(\Praxigento\Core\Data $ctx)
    {
        /* extract working variables from execution context */
        $snap = $ctx->get(self::CTX_DWNL_SNAP);
        $customers = $ctx->get(self::CTX_DWNL_CUST);
        $pv = $ctx->get(self::CTX_PV);
        $calcId = $ctx->get(self::CTX_CALC_ID);

        /* prepare results structures */
        /* array with PV compression transfers results (see \Praxigento\BonusHybrid\Repo\Data\Entity\Compression\Phase1\Transfer\Pv) */
        $pvTransfers = [];

        /* perform action */
        $qLevels = $this->toolScheme->getQualificationLevels();
        $forcedCustomers = $this->toolScheme->getForcedQualificationCustomersIds();
        $signupDebitCustomers = $this->hlpSignupDebitCust->exec();

        /* prepare intermediary structures for calculation */
        $mapCustomer = $this->mapById($customers, ECustomer::ATTR_CUSTOMER_ID);
        $mapSnap = $this->mapById($snap, ASnap::A_CUST_ID);
        $mapPv = $pv;
        $mapDepth = $this->mapByTreeDepthDesc($snap, ASnap::A_CUST_ID, ASnap::A_DEPTH);
        $mapTeams = $this->mapByTeams($snap, ASnap::A_CUST_ID, ASnap::A_PARENT_ID);

        /* compression itself */
        /* array for compression results: [$customerId => [$pvCompressed, $parentCompressed], ... ]*/
        $compression = [];
        foreach ($mapDepth as $depth => $levelCustomers) {
            foreach ($levelCustomers as $custId) {
                $pv = isset($mapPv[$custId]) ? $mapPv[$custId] : 0;
                $parentId = $mapSnap[$custId][ASnap::A_PARENT_ID];
                $custData = $mapCustomer[$custId];
                $scheme = $this->toolScheme->getSchemeByCustomer($custData);
                $level = $qLevels[$scheme]; // qualification level for current customer
                $isForced = in_array($custId, $forcedCustomers);
                $isSignupDebit = in_array($custId, $signupDebitCustomers);
                if (($pv >= $level) || $isForced || $isSignupDebit) {
                    if (isset($compression[$custId])) {
                        $pvExist = $compression[$custId][0];
                        $pvNew = $pv + $pvExist;
                        $compression[$custId] = [$pvNew, $parentId];
                    } else {
                        $compression[$custId] = [$pv, $parentId];
                    }
                } else {
                    /* move PV up to the closest qualified parent (parent's level is used for qualification) */
                    $path = $mapSnap[$custId][ASnap::A_PATH];
                    $parents = $this->toolTree->getParentsFromPathReversed($path);
                    $foundParentId = null;
                    foreach ($parents as $newParentId) {
                        $parentData = $mapCustomer[$newParentId];
                        $parentScheme = $this->toolScheme->getSchemeByCustomer($parentData);
                        $parentLevel = $qLevels[$parentScheme]; // qualification level for current parent
                        $pvParent = isset($mapPv[$newParentId]) ? $mapPv[$newParentId] : 0;
                        if (
                            ($pvParent >= $parentLevel) ||
                            (in_array($newParentId, $forcedCustomers))
                        ) {
                            $foundParentId = $newParentId;
                            break;
                        }
                    }
                    unset($parents);
                    /* add PV to the closest qualified parent */
                    if (
                        !is_null($foundParentId) &&
                        ($pv > 0)
                    ) {
                        if (isset($compression[$foundParentId])) {
                            $pvExist = $compression[$foundParentId][0];
                            $pvNew = $pv + $pvExist;
                            $compression[$foundParentId][0] = $pvNew;
                        } else {
                            $compression[$foundParentId] [0] = $pv;
                        }
                        // $pv PV are transferred from customer #$custId to his qualified parent #$foundParentId
                        $pvTransferItem = new \Praxigento\BonusHybrid\Repo\Data\Entity\Compression\Phase1\Transfer\Pv();
                        $pvTransferItem->setCalcRef($calcId);
                        $pvTransferItem->setCustFromRef($custId);
                        $pvTransferItem->setCustToRef($foundParentId);
                        $pvTransferItem->setPv($pv);
                        $pvTransfers[] = $pvTransferItem;
                    }
                    /* change parent for all siblings of the unqualified customer */
                    if (isset($mapTeams[$custId])) {
                        $team = $mapTeams[$custId];
                        foreach ($team as $memberId) {
                            if (isset($compression[$memberId])) {
                                /* if null set customer own id to indicate root node */
                                $compression[$memberId][1] = is_null($foundParentId) ? $memberId : $foundParentId;
                            }
                        }
                    }
                }
            }
        }
        unset($mapCustomer);
        unset($mapDepth);
        unset($mapTeams);
        /* compose compressed tree */
        $compressedTree = $this->composeTree($compression);
        /* add compressed PV data */
        $compressedTree = $this->populateCompressedSnapWithPv($compressedTree, $compression);

        /* place results to context */
        $ctx->set(self::CTX_COMPRESSED, $compressedTree);
        $ctx->set(self::CTX_PV_TRANSFERS, $pvTransfers);
    }

    /**
     * Populate phase 1 compressed $tree with compressed PV data.
     *
     * @param array $tree compressed tree w/o PV data
     * @param array $compressionData compression data with compressed PV
     * @return array compressed tree with PV data
     */
    protected function populateCompressedSnapWithPv($tree, $compressionData)
    {
        $result = $tree;
        foreach ($compressionData as $custId => $data) {
            /* 0 - PV, 1 - parentId */
            $result[$custId][ECompressPhase1::ATTR_PV] = $data[0];
        }
        return $result;
    }
}