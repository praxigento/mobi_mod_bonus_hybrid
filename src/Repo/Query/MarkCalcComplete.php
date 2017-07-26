<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Repo\Query;

use Praxigento\BonusBase\Data\Entity\Calculation;
use Praxigento\BonusHybrid\Config as Cfg;

class MarkCalcComplete
    extends \Praxigento\Core\Repo\Def\Db
{
    /** @var \Praxigento\BonusBase\Repo\Entity\Def\Calculation */
    protected $repoCalc;
    /** @var  \Praxigento\Core\Tool\IDate */
    protected $toolDate;

    public function __construct(
        \Magento\Framework\App\ResourceConnection $resource,
        \Praxigento\Core\Tool\IDate $toolDate,
        \Praxigento\BonusBase\Repo\Entity\Def\Calculation $repoCalc
    ) {
        parent::__construct($resource);
        $this->toolDate = $toolDate;
        $this->repoCalc = $repoCalc;
    }

    public function exec($calcId)
    {
        $tsEnded = $this->toolDate->getUtcNowForDb();
        $bind = [
            Calculation::ATTR_DATE_ENDED => $tsEnded,
            Calculation::ATTR_STATE => Cfg::CALC_STATE_COMPLETE
        ];
        $where = Calculation::ATTR_ID . '=' . (int)$calcId;
        $result = $this->repoCalc->update($bind, $where);
        return $result;
    }
}