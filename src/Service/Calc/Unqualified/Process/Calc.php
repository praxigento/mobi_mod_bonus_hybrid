<?php
/**
 * Created by PhpStorm.
 * User: alex
 * Date: 17.4.12
 * Time: 20:29
 */

namespace Praxigento\BonusHybrid\Service\Calc\Unqualified\Process;

use Praxigento\BonusHybrid\Repo\Entity\Data\Downline as EBonDwnl;

/**
 * Routine to process plain downline & to perform qualification compression.
 */
class Calc
{
    /** Max count of the unq. months in a row allowed for distributors. */
    const MAX_UNQ_MONTHS = 6;
    /** @var \Praxigento\Downline\Service\ICustomer */
    private $callDwnlCust;
    /** @var \Praxigento\Downline\Helper\Tree */
    private $hlpDwnlTree;

    public function __construct(
        \Praxigento\Downline\Helper\Tree $hlpDwnlTree,
        \Praxigento\Downline\Service\ICustomer $callDwnlCust
    )
    {
        $this->hlpDwnlTree = $hlpDwnlTree;
        $this->callDwnlCust = $callDwnlCust;
    }

    private function changeParent($custId, $parentId)
    {
        $req = new \Praxigento\Downline\Service\Customer\Request\ChangeParent();
        $req->setCustomerId($custId);
        $req->setNewParentId($parentId);
        $this->callDwnlCust->changeParent($req);
    }

    /**
     * @param EBonDwnl[] $tree
     */
    public function exec($tree)
    {
        /* collect teams by customer */
        $mapTeams = $this->hlpDwnlTree->mapByTeams($tree, EBonDwnl::ATTR_CUST_REF, EBonDwnl::ATTR_PARENT_REF);
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
                        $this->changeParent($childId, $parentId);
                    }
                }
            }
        }
    }
}