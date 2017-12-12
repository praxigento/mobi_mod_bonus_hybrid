<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Web\Dcp\Report\Check\Fun\Proc\MineData;

use Praxigento\BonusHybrid\Api\Web\Dcp\Report\Check\Response\Body\Customer as DCustomer;
use Praxigento\BonusHybrid\Api\Web\Dcp\Report\Check\Response\Body\Sections\QualLegs as DQualLegs;
use Praxigento\BonusHybrid\Api\Web\Dcp\Report\Check\Response\Body\Sections\QualLegs\Item as DItem;
use Praxigento\BonusHybrid\Api\Web\Dcp\Report\Check\Response\Body\Sections\QualLegs\Qualification as DQual;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Entity\Data\Compression\Phase2\Legs as ELegs;
use Praxigento\BonusHybrid\Web\Dcp\Report\Check\Fun\Proc\MineData\A\Fun\Rou\GetCalcs as RouGetCalcs;
use Praxigento\BonusHybrid\Web\Dcp\Report\Check\Fun\Proc\MineData\A\Fun\Rou\IsSchemeEu as RouIsSchemeEu;
use Praxigento\BonusHybrid\Web\Dcp\Report\Check\Fun\Proc\MineData\QualLegs\Db\Query\GetItems as QBGetItems;

/**
 * Action to build "QualificationLegs" section of the DCP's "Check" report.
 */
class QualLegs
{
    /** @var \Praxigento\Core\Tool\IPeriod */
    private $hlpPeriod;
    /** @var \Praxigento\BonusHybrid\Web\Dcp\Report\Check\Fun\Proc\MineData\QualLegs\Db\Query\GetItems */
    private $qbGetItems;
    /** @var \Praxigento\BonusHybrid\Repo\Entity\Compression\Phase2\Legs */
    private $repoLegs;
    /** @var \Praxigento\BonusHybrid\Web\Dcp\Report\Check\Fun\Proc\MineData\A\Fun\Rou\GetCalcs */
    private $rouGetCalcs;
    /** @var \Praxigento\BonusHybrid\Web\Dcp\Report\Check\Fun\Proc\MineData\A\Fun\Rou\IsSchemeEu */
    private $rouIsSchemeEu;

    public function __construct(
        \Praxigento\Core\Tool\IPeriod $hlpPeriod,
        \Praxigento\BonusHybrid\Repo\Entity\Compression\Phase2\Legs $repoLegs,
        QBGetItems $qbGetItems,
        RouGetCalcs $rouGetCalcs,
        RouIsSchemeEu $rouIsSchemeEu
    )
    {
        $this->hlpPeriod = $hlpPeriod;
        $this->repoLegs = $repoLegs;
        $this->qbGetItems = $qbGetItems;
        $this->rouGetCalcs = $rouGetCalcs;
        $this->rouIsSchemeEu = $rouIsSchemeEu;
    }

    public function exec($custId, $period): DQualLegs
    {
        /* get input and prepare working data */
        $dsBegin = $this->hlpPeriod->getPeriodFirstDate($period);
        $dsEnd = $this->hlpPeriod->getPeriodLastDate($period);

        /* perform processing */
        $calcs = $this->rouGetCalcs->exec($dsBegin, $dsEnd);
        $isSchemeEu = $this->rouIsSchemeEu->exec($custId);
        if ($isSchemeEu) {
            $calcId = $calcs[Cfg::CODE_TYPE_CALC_COMPRESS_PHASE2_EU];
        } else {
            $calcId = $calcs[Cfg::CODE_TYPE_CALC_COMPRESS_PHASE2_DEF];
        }
        $items = $this->getItems($calcId, $custId);
        $qual = $this->getQualData($calcId, $custId);

        /* compose result */
        $result = new DQualLegs();
        $result->setItems($items);
        $result->setQualification($qual);
        return $result;
    }

    /**
     * @param int $calcId
     * @param int $custId
     * @return DItem[]
     */
    private function getItems($calcId, $custId)
    {
        $query = $this->qbGetItems->build();
        $conn = $query->getConnection();
        $bind = [
            QBGetItems::BND_CALC_ID => $calcId,
            QBGetItems::BND_CUST_ID => $custId
        ];
        $rs = $conn->fetchAll($query, $bind);

        $result = [];
        foreach ($rs as $one) {
            /* get DB data */
            $custId = $one[QBGetItems::A_CUST_ID];
            $depth = $one[QBGetItems::A_DEPTH];
            $mlmId = $one[QBGetItems::A_MLM_ID];
            $nameFirst = trim($one[QBGetItems::A_NAME_FIRST]);
            $nameLast = trim($one[QBGetItems::A_NAME_LAST]);
            $ov = $one[QBGetItems::A_OV];

            /* composite values */
            $name = "$nameFirst $nameLast";

            /* compose API data */
            $customer = new DCustomer();
            $customer->setId($custId);
            $customer->setMlmId($mlmId);
            $customer->setName($name);
            $customer->setLevel($depth);
            $item = new DItem();
            $item->setCustomer($customer);
            $item->setVolume($ov);

            $result[] = $item;
        }
        return $result;
    }

    private function getQualData($calcId, $custId)
    {
        $ids = [
            ELegs::ATTR_CALC_REF => $calcId,
            ELegs::ATTR_CUST_REF => $custId
        ];
        /** @var ELegs $entity */
        $entity = $this->repoLegs->getById($ids);
        $maxLegCust = $entity->getCustMaxRef();
        $maxLegOv = $entity->getLegMax();
        $maxLegQual = $entity->getPvQualMax();
        $secondLegCust = $entity->getCustSecondRef();
        $secondLegOv = $entity->getLegSecond();
        $secondLegQual = $entity->getPvQualSecond();
        $otherLegsOv = $entity->getLegOthers();
        $otherLegsQual = $entity->getPvQualOther();

        /* compose result */
        $result = new DQual();
        $result->setMaxLegCust($maxLegCust);
        $result->setMaxLegOv($maxLegOv);
        $result->setMaxLegQual($maxLegQual);
        $result->setSecondLegCust($secondLegCust);
        $result->setSecondLegOv($secondLegOv);
        $result->setSecondLegQual($secondLegQual);
        $result->setOtherLegsOv($otherLegsOv);
        $result->setOtherLegsQual($otherLegsQual);
        return $result;
    }

}