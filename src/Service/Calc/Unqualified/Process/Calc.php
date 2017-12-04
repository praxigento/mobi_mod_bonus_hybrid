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

    public function __construct(
        \Praxigento\Downline\Service\ICustomer $callDwnlCust
    )
    {
        $this->callDwnlCust = $callDwnlCust;
    }

    private function changeParent($custId, $parentId)
    {
        $req = new \Praxigento\Downline\Service\Customer\Request\ChangeParent();
        $req->setNewParentId($parentId);
        $resp = $this->callDwnlCust->changeParent($req);
    }

    /**
     * @param EBonDwnl[] $tree
     */
    public function exec($tree)
    {
        /** @var EBonDwnl $item */
        foreach ($tree as $item) {
            $unqMonths = $item->getUnqMonths();
            if ($unqMonths > self::MAX_UNQ_MONTHS) {
                /* we need to rebuild downline tree to pull up unqualified customer's downline to his parent */
                $custId = $item->getCustomerRef();
                $parentId = $item->getParentRef();
                $this->changeParent($custId, $parentId);
            }
        }
    }
}