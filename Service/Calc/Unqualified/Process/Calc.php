<?php
/**
 * Created by PhpStorm.
 * User: alex
 * Date: 17.4.12
 * Time: 20:29
 */

namespace Praxigento\BonusHybrid\Service\Calc\Unqualified\Process;

use Praxigento\BonusHybrid\Repo\Data\Downline as EBonDwnl;

/**
 * Routine to process plain downline & to perform qualification compression (re-link downlines of the unqualified
 * customers to the his parents).
 */
class Calc
{
    /** Max count of the unq. months in a row allowed for distributors. */
    const MAX_UNQ_MONTHS = 6;

    /** @var \Praxigento\Downline\Api\Helper\Tree */
    private $hlpDwnlTree;
    /** @var \Praxigento\Core\Api\Helper\Period */
    private $hlpPeriod;
    /** @var \Praxigento\Downline\Api\Service\Customer\ChangeParent */
    private $servDwnlChangeParent;

    public function __construct(
        \Praxigento\Core\Api\Helper\Period $hlpPeriod,
        \Praxigento\Downline\Api\Helper\Tree $hlpDwnlTree,
        \Praxigento\Downline\Api\Service\Customer\ChangeParent $servDwnlChangeParent
    )
    {
        $this->hlpPeriod = $hlpPeriod;
        $this->hlpDwnlTree = $hlpDwnlTree;
        $this->servDwnlChangeParent = $servDwnlChangeParent;
    }

    private function changeParent($custId, $parentId, $dateChanged)
    {
        $req = new \Praxigento\Downline\Api\Service\Customer\ChangeParent\Request();
        $req->setCustomerId($custId);
        $req->setNewParentId($parentId);
        $req->setDate($dateChanged);
        $this->servDwnlChangeParent->exec($req);
    }

    /**
     * @param EBonDwnl[] $tree
     * @param string $period YYYYMMDD
     */
    public function exec($tree, $period)
    {
        /* register changes by the last date of the period */
        $dateChanged = $this->hlpPeriod->getTimestampTo($period);
        /* collect teams by customer */
        $mapTeams = $this->hlpDwnlTree->mapByTeams($tree, EBonDwnl::A_CUST_REF, EBonDwnl::A_PARENT_REF);
        /** @var EBonDwnl $item */
        foreach ($tree as $item) {
            $unqMonths = $item->getUnqMonths();
            if ($unqMonths > self::MAX_UNQ_MONTHS) {
                /* we need to rebuild downline tree to pull up unqualified customer's downline to his parent */
                $custId = $item->getCustomerRef();
                $parentId = $item->getParentRef();
                if (isset($mapTeams[$custId])) {
                    $team = $mapTeams[$custId];
                    foreach ($team as $childId) {
                        $this->changeParent($childId, $parentId, $dateChanged);
                    }
                }
            }
        }
    }
}