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
    private const MAX_UNQ_MONTHS = 6;

    /** @var \Praxigento\BonusHybrid\Helper\Config */
    private $hlpCfg;
    /** @var \Praxigento\Downline\Api\Helper\Tree */
    private $hlpDwnlTree;
    /** @var \Praxigento\Core\Api\Helper\Period */
    private $hlpPeriod;
    /** @var \Magento\Customer\Api\CustomerRepositoryInterface */
    private $repoCust;
    /** @var \Praxigento\Downline\Api\Service\Customer\Parent\Change */
    private $servDwnlChangeParent;
    /** @var \Psr\Log\LoggerInterface */
    private $logger;
    public function __construct(
        \Praxigento\Core\Api\App\Logger\Main $logger,
        \Magento\Customer\Api\CustomerRepositoryInterface $repoCust,
        \Praxigento\Core\Api\Helper\Period $hlpPeriod,
        \Praxigento\Downline\Api\Helper\Tree $hlpDwnlTree,
        \Praxigento\BonusHybrid\Helper\Config $hlpCfg,
        \Praxigento\Downline\Api\Service\Customer\Parent\Change $servDwnlChangeParent
    )
    {
        $this->logger = $logger;
        $this->repoCust = $repoCust;
        $this->hlpPeriod = $hlpPeriod;
        $this->hlpDwnlTree = $hlpDwnlTree;
        $this->hlpCfg = $hlpCfg;
        $this->servDwnlChangeParent = $servDwnlChangeParent;
    }

    private function changeParent($custId, $parentId, $dateChanged)
    {
        $req = new \Praxigento\Downline\Api\Service\Customer\Parent\Change\Request();
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
        /* group ID for unqualified customers */
        $groupIdUnq = $this->hlpCfg->getDowngradeGroupUnqual();
        /* register changes by the last date of the period */
        $dateChanged = $this->hlpPeriod->getTimestampTo($period);
        /* collect teams by customer */
        $mapTeams = $this->hlpDwnlTree->mapByTeams($tree, EBonDwnl::A_CUST_REF, EBonDwnl::A_PARENT_REF);
        /** @var EBonDwnl $item */
        foreach ($tree as $item) {
            $unqMonths = $item->getUnqMonths();
            if ($unqMonths >= self::MAX_UNQ_MONTHS) {
                /* we need to rebuild downline tree to pull up unqualified customer's downline to his parent */
                $custId = $item->getCustomerRef();
                $parentId = $item->getParentRef();
                if (isset($mapTeams[$custId])) {
                    $team = $mapTeams[$custId];
                    foreach ($team as $childId) {
                        $this->changeParent($childId, $parentId, $dateChanged);
                    }
                }
                /*... then we should change customer group */
                try {
                    $cust = $this->repoCust->getById($custId);
                    $groupId = $cust->getGroupId();
                    if ($groupId != $groupIdUnq) {
                        $cust->setGroupId($groupIdUnq);
                        $this->repoCust->save($cust);
                        $this->logger->info("Customer #$custId is downgraded ( from group $groupId to #$groupIdUnq).");
                    }
                } catch (\Throwable $e) {
                    $this->logger->error("Cannot update customer group on unqualified customer ($custId) downgrade.");
                    throw $e;
                }
            }
        }
    }
}