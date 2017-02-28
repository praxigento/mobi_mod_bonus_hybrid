<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Service\Calc\Sub;

use Praxigento\BonusHybrid\Config as Cfg;

class Pto
{

    /** Add traits */
    use \Praxigento\BonusHybrid\Service\Calc\Traits\TMap {
        mapByTreeDepthDesc as protected;
    }

    const OPT_CALC_ID = 'calc_id';
    const OPT_PERIOD_END = 'period_end';
    const OPT_UPDATES = 'updates';

    /** @var \Praxigento\Downline\Service\ISnap */
    protected $callDwnlSnap;
    /** @var \Praxigento\Accounting\Repo\Entity\IAccount */
    protected $repoAcc;
    /** @var \Praxigento\BonusHybrid\Repo\Entity\Registry\IPto */
    protected $repoRegPto;
    /** @var \Praxigento\Downline\Tool\ITree */
    protected $toolDownlineTree;

    public function __construct(
        \Praxigento\Downline\Tool\ITree $toolTree,
        \Praxigento\Accounting\Repo\Entity\IAccount $repoAcc,
        \Praxigento\BonusHybrid\Repo\Entity\Registry\IPto $repoRegPto,
        \Praxigento\Downline\Service\ISnap $callDwnlSnap
    ) {
        $this->toolDownlineTree = $toolTree;
        $this->repoAcc = $repoAcc;
        $this->repoRegPto = $repoRegPto;
        $this->callDwnlSnap = $callDwnlSnap;
    }

    /**
     * @return \Praxigento\Accounting\Data\Entity\Account[]
     */
    protected function getCustomersAccounts()
    {
        $mapAccs = $this->repoAcc->getAllByAssetTypeCode(Cfg::CODE_TYPE_ASSET_PV);
        $result = [];
        foreach ($mapAccs as $one) {
            $custId = $one->getCustomerId();
            $result[$custId] = $one;
        }
        return $result;
    }

    public function do($opts)
    {
        $calcId = $opts[self::OPT_CALC_ID];
        $periodEnd = $opts[self::OPT_PERIOD_END];
        $updates = $opts[self::OPT_UPDATES];
        /* get accounts map */
        $mapAccs = $this->getCustomersAccounts();
        /* get customers downline tree */
        $reqTree = new \Praxigento\Downline\Service\Snap\Request\GetStateOnDate();
        $reqTree->setDatestamp($periodEnd);
        $respTree = $this->callDwnlSnap->getStateOnDate($reqTree);
        $tree = $respTree->get();
        $mapByDepth = $this->mapByTreeDepthDesc(
            $tree,
            \Praxigento\Downline\Data\Entity\Snap::ATTR_CUSTOMER_ID,
            \Praxigento\Downline\Data\Entity\Snap::ATTR_DEPTH
        );
        $mapRegistry = [];
        foreach ($mapByDepth as $level => $customers) {
            foreach ($customers as $custId) {
                /* get account ID */
                if (isset($mapAccs[$custId])) {
                    $account = $mapAccs[$custId];
                    $accId = $account->getId();
                    if (isset($updates[$accId])) {
                        $parentId = $tree[$custId][\Praxigento\Downline\Data\Entity\Snap::ATTR_PARENT_ID];
                        $pv = $updates[$accId];
                        if (!isset($mapRegistry[$custId])) {
                            $mapRegistry[$custId] = [
                                \Praxigento\BonusHybrid\Entity\Registry\Pto::ATTR_CUSTOMER_REF => $custId,
                                \Praxigento\BonusHybrid\Entity\Registry\Pto::ATTR_PARENT_REF => $parentId,
                                \Praxigento\BonusHybrid\Entity\Registry\Pto::ATTR_PV => $pv,
                                \Praxigento\BonusHybrid\Entity\Registry\Pto::ATTR_TV => $pv,
                                \Praxigento\BonusHybrid\Entity\Registry\Pto::ATTR_OV => $pv
                            ];
                        } else {
                            $mapRegistry[$custId][\Praxigento\BonusHybrid\Entity\Registry\Pto::ATTR_PV] += $pv;
                            $mapRegistry[$custId][\Praxigento\BonusHybrid\Entity\Registry\Pto::ATTR_TV] += $pv;
                            $mapRegistry[$custId][\Praxigento\BonusHybrid\Entity\Registry\Pto::ATTR_OV] += $pv;
                        }
                        /* process upline */
                        $path = $tree[$custId][\Praxigento\Downline\Data\Entity\Snap::ATTR_PATH];
                        $parents = $this->toolDownlineTree->getParentsFromPathReversed($path);
                        $isFather = true;
                        foreach ($parents as $pCustId) {
                            if (!isset($mapRegistry[$pCustId])) {
                                $parentId = $tree[$pCustId][\Praxigento\Downline\Data\Entity\Snap::ATTR_PARENT_ID];
                                $mapRegistry[$pCustId] = [
                                    \Praxigento\BonusHybrid\Entity\Registry\Pto::ATTR_CUSTOMER_REF => $pCustId,
                                    \Praxigento\BonusHybrid\Entity\Registry\Pto::ATTR_PARENT_REF => $parentId,
                                    \Praxigento\BonusHybrid\Entity\Registry\Pto::ATTR_PV => 0,
                                    \Praxigento\BonusHybrid\Entity\Registry\Pto::ATTR_TV => 0,
                                    \Praxigento\BonusHybrid\Entity\Registry\Pto::ATTR_OV => $pv
                                ];
                            } else {
                                $mapRegistry[$pCustId][\Praxigento\BonusHybrid\Entity\Registry\Pto::ATTR_OV] += $pv;
                            }
                            /* collect TV */
                            if ($isFather) {
                                $mapRegistry[$pCustId][\Praxigento\BonusHybrid\Entity\Registry\Pto::ATTR_TV] += $pv;
                            }
                            $isFather = false;
                        }
                    }
                }
            }
        }
        /* save registry */
        foreach ($mapRegistry as $item) {
            $item[\Praxigento\BonusHybrid\Entity\Registry\Pto::ATTR_CALC_REF] = $calcId;
            $this->repoRegPto->create($item);
        }
    }
}