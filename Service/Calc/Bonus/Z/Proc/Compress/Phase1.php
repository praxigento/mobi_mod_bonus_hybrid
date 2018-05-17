<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Bonus\Z\Proc\Compress;

use Praxigento\Downline\Repo\Data\Customer as ECustomer;

/**
 * Process to calculate Phase1 compression.
 */
class Phase1
    implements \Praxigento\Core\Api\App\Service\Process
{
    /** Calculation ID to reference in compressed PV transfers  */
    const IN_CALC_ID = 'calcId';
    /** Customers downline tree (plain) to the end of calculation period */
    const IN_DWNL_PLAIN = 'dwnlPlain';
    /**#@+ string: keys names for attributes in plain downline. */
    const IN_KEY_CALC_ID = 'keyCalcId';
    const IN_KEY_CUST_ID = 'keyCustId';
    const IN_KEY_DEPTH = 'keyDepth';
    const IN_KEY_PARENT_ID = 'keyParentId';
    const IN_KEY_PATH = 'keyPath';
    const IN_KEY_PV = 'keyPv';
    /**#@-  */
    /** PV by customer ID map */
    const IN_PV = 'pv';
    /** Calculation results, data for compression table */
    const OUT_COMPRESSED = 'compressed';
    /** Compressed PV transfers to save in DB */
    const OUT_PV_TRANSFERS = 'pvTransfers';

    /** @var \Praxigento\Downline\Repo\Dao\Customer */
    private $daoCustDwnl;
    /** @var  \Praxigento\BonusHybrid\Api\Helper\Scheme */
    private $hlpScheme;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Z\Helper\GetCustomersIds */
    private $hlpSignUpDebitCust;
    /** @var \Praxigento\Downline\Api\Helper\Tree */
    private $hlpTree;
    /** @var    \Praxigento\Downline\Service\ISnap */
    private $servDwnlSnap;

    public function __construct(
        \Praxigento\BonusHybrid\Api\Helper\Scheme $hlpScheme,
        \Praxigento\Downline\Api\Helper\Tree $hlpTree,
        \Praxigento\BonusHybrid\Service\Calc\Z\Helper\GetCustomersIds $hlpSignUpDebitCust,
        \Praxigento\Downline\Repo\Dao\Customer $daoCustDwnl,
        \Praxigento\Downline\Service\ISnap $servDwnlSnap
    )
    {
        $this->hlpScheme = $hlpScheme;
        $this->hlpTree = $hlpTree;
        $this->hlpSignUpDebitCust = $hlpSignUpDebitCust;
        $this->daoCustDwnl = $daoCustDwnl;
        $this->servDwnlSnap = $servDwnlSnap;
    }

    /**
     * @param array $compressionData array [$custId=>[$pv, $parentId], ... ] with compression data.
     * @return \Praxigento\Downline\Repo\Data\Snap[]
     */
    private function composeTree($compressionData)
    {
        /* prepare request data: convert to [$customer=>parent, ... ] form */
        $converted = [];
        foreach ($compressionData as $custId => $data) {
            /* 0 - PV, 1 - parentId */
            $converted[$custId] = $data[1];
        }
        $req = new \Praxigento\Downline\Service\Snap\Request\ExpandMinimal();
        $req->setTree($converted);
        $resp = $this->servDwnlSnap->expandMinimal($req);
        unset($converted);
        $snap = $resp->getSnapData();
        /* convert 2D array to array of entities */
        $result = [];
        foreach ($snap as $one) {
            $entity = new \Praxigento\Downline\Repo\Data\Snap($one);
            $result[] = $entity;
        }
        return $result;
    }

    public function exec(\Praxigento\Core\Data $ctx)
    {
        /* extract working variables from execution context */
        $calcId = $ctx->get(self::IN_CALC_ID);
        $mapPv = $ctx->get(self::IN_PV);
        $plain = $ctx->get(self::IN_DWNL_PLAIN);
        $keyCalcId = $ctx->get(self::IN_KEY_CALC_ID);
        $keyCustId = $ctx->get(self::IN_KEY_CUST_ID);
        $keyParentId = $ctx->get(self::IN_KEY_PARENT_ID);
        $keyDepth = $ctx->get(self::IN_KEY_DEPTH);
        $keyPath = $ctx->get(self::IN_KEY_PATH);
        $keyPv = $ctx->get(self::IN_KEY_PV);

        /* prepare result vars */
        $result = new \Praxigento\Core\Data();
        /* array with PV compression transfers results (see \Praxigento\BonusHybrid\Repo\Data\Entity\Compression\Phase1\Transfer\Pv) */
        $pvTransfers = [];

        /* define local working data */
        $qLevels = $this->hlpScheme->getQualificationLevels();
        $forcedCustomers = $this->hlpScheme->getForcedQualificationCustomersIds();
        $signupDebitCustomers = $this->hlpSignUpDebitCust->exec();

        /* prepare intermediary structures for calculation */
        $mapCustomer = $this->getCustomersMap();
        $mapPlain = $this->hlpTree->mapById($plain, $keyCustId);
        $mapDepth = $this->hlpTree->mapByTreeDepthDesc($plain, $keyCustId, $keyDepth);
        $mapTeams = $this->hlpTree->mapByTeams($plain, $keyCustId, $keyParentId);

        /**
         * perform processing
         */
        /* array for compression results: [$customerId => [$pvCompressed, $parentCompressed], ... ]*/
        $compression = [];
        foreach ($mapDepth as $depth => $levelCustomers) {
            foreach ($levelCustomers as $custId) {
                $pv = isset($mapPv[$custId]) ? $mapPv[$custId] : 0;
                $dwnlEntry = $mapPlain[$custId];
                $parentId = is_array($dwnlEntry) ? $dwnlEntry[$keyParentId] : $dwnlEntry->get($keyParentId);
                $custData = $mapCustomer[$custId];
                $scheme = $this->hlpScheme->getSchemeByCustomer($custData);
                $level = $qLevels[$scheme]; // qualification level for current customer
                $isForced = in_array($custId, $forcedCustomers);
                $isSignUpDebit = in_array($custId, $signupDebitCustomers);
                if (($pv >= $level) || $isForced || $isSignUpDebit) {
                    if (isset($compression[$custId])) {
                        $pvExist = $compression[$custId][0];
                        $pvNew = $pv + $pvExist;
                        $compression[$custId] = [$pvNew, $parentId];
                    } else {
                        $compression[$custId] = [$pv, $parentId];
                    }
                } else {
                    /* move PV up to the closest qualified parent (parent's level is used for qualification) */
                    $path = is_array($dwnlEntry) ? $dwnlEntry[$keyPath] : $dwnlEntry->get($keyPath);
                    $parents = $this->hlpTree->getParentsFromPathReversed($path);
                    $foundParentId = null;
                    foreach ($parents as $newParentId) {
                        $parentData = $mapCustomer[$newParentId];
                        $parentParentId = $parentData->getParentId();
                        /* MOBI-942: use parent scheme to qualify parents */
                        $parentScheme = $this->hlpScheme->getSchemeByCustomer($parentData);
                        $parentLevel = $qLevels[$parentScheme]; // qualification level for current parent
                        $pvParent = isset($mapPv[$newParentId]) ? $mapPv[$newParentId] : 0;
                        if (
                            ($pvParent >= $parentLevel) ||
                            (in_array($newParentId, $forcedCustomers))
                        ) {
                            $foundParentId = $newParentId;
                            /* add PV to the closest qualified parent */
                            if ($pv > 0) {
                                if (isset($compression[$foundParentId])) {
                                    $pvExist = $compression[$foundParentId][0];
                                    $pvNew = $pv + $pvExist;
                                    $compression[$foundParentId][0] = $pvNew;
                                } else {
                                    $compression[$foundParentId] [0] = $pv;
                                    $compression[$foundParentId] [1] = $parentParentId;
                                }
                                // $pv PV are transferred from customer #$custId to his qualified parent #$foundParentId
                                $pvTransferItem = new \Praxigento\BonusHybrid\Repo\Data\Compression\Phase1\Transfer\Pv();
                                $pvTransferItem->setCalcRef($calcId);
                                $pvTransferItem->setCustFromRef($custId);
                                $pvTransferItem->setCustToRef($foundParentId);
                                $pvTransferItem->setPv($pv);
                                $pvTransfers[] = $pvTransferItem;
                            }
                            break;
                        }
                    }
                    unset($parents);

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
        /** @var \Praxigento\Downline\Repo\Data\Snap[] $cmprsSnap */
        $cmprsSnap = $this->composeTree($compression);

        /* re-build result tree (compressed) from source tree (plain) */
        $cmprsResult = $this->rebuildTree($calcId, $cmprsSnap, $mapPlain, $keyCalcId, $keyParentId, $keyDepth, $keyPath);

        /* add compressed PV data */
        $cmprsSnap = $this->populateCompressedSnapWithPv($cmprsResult, $compression, $keyPv);

        /* put result data into output */
        $result->set(self::OUT_COMPRESSED, $cmprsSnap);
        $result->set(self::OUT_PV_TRANSFERS, $pvTransfers);
        return $result;
    }

    /**
     * Actual customers downline mapped by customer ID.
     *
     * @return \Praxigento\Downline\Repo\Data\Customer[]
     */
    private function getCustomersMap()
    {
        /** @var \Praxigento\Downline\Repo\Data\Customer[] $customers */
        $customers = $this->daoCustDwnl->get();
        $result = $this->hlpTree->mapById($customers, ECustomer::A_CUSTOMER_ID);
        return $result;
    }

    /**
     * Populate phase 1 compressed $tree with compressed PV data.
     *
     * @param array $tree compressed tree w/o PV data
     * @param array $compressionData compression data with compressed PV
     * @return array compressed tree with PV data
     */
    private function populateCompressedSnapWithPv($tree, $compressionData, $keyPv)
    {
        $result = $tree;
        foreach ($compressionData as $custId => $data) {
            /* 0 - PV, 1 - parentId */
            $pv = $data[0];
            $entry = $result[$custId];
            if (is_array($entry)) {
                $entry[$keyPv] = $pv;
            } else {
                $entry->set($keyPv, $pv);
            }
            $result[$custId] = $entry;
        }
        return $result;
    }

    /**
     * Rebuild target tree from source ($mapPlain) using compressed snap data.
     *
     * @param int $calcId phase1 compression calculation ID
     * @param \Praxigento\Downline\Repo\Data\Snap[] $compressed
     * @param array|\Praxigento\Core\Data[] $mapPlain
     * @param string $keyCalcId
     * @param string $keyParentId
     * @param string $keyDepth
     * @param string $keyPath
     * @return array|\Praxigento\Core\Data[]
     */
    private function rebuildTree($calcId, $compressed, $mapPlain, $keyCalcId, $keyParentId, $keyDepth, $keyPath)
    {
        $result = [];
        /** @var \Praxigento\Downline\Repo\Data\Snap $item */
        foreach ($compressed as $item) {
            $snapCustId = $item->getCustomerId();
            $snapParentId = $item->getParentId();
            $snapDepth = $item->getDepth();
            $snapPath = $item->getPath();
            $entry = $mapPlain[$snapCustId];
            if (is_array($entry)) {
                $entry[$keyCalcId] = $calcId;
                $entry[$keyParentId] = $snapParentId;
                $entry[$keyDepth] = $snapDepth;
                $entry[$keyPath] = $snapPath;
            } else {
                $entry->set($keyCalcId, $calcId);
                $entry->set($keyParentId, $snapParentId);
                $entry->set($keyDepth, $snapDepth);
                $entry->set($keyPath, $snapPath);
            }
            $result[$snapCustId] = $entry;
        }
        return $result;
    }
}