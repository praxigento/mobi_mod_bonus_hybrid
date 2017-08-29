<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Service\Period\Response;

/**
 * Response contains arrays with period and calculation data for base and dependent calculations.
 *
 * @method \Praxigento\BonusBase\Repo\Entity\Data\Calculation|null getBaseCalcData()
 * @method void setBaseCalcData(\Praxigento\BonusBase\Repo\Entity\Data\Calculation | array $data)
 * @method \Praxigento\BonusBase\Repo\Entity\Data\Period|null getBasePeriodData()
 * @method void setBasePeriodData(\Praxigento\BonusBase\Repo\Entity\Data\Period | array $data)
 * @method \Praxigento\BonusBase\Repo\Entity\Data\Calculation|null getDependentCalcData()
 * @method void setDependentCalcData(\Praxigento\BonusBase\Repo\Entity\Data\Calculation | array $data)
 * @method \Praxigento\BonusBase\Repo\Entity\Data\Period|null getDependentPeriodData()
 * @method void setDependentPeriodData(\Praxigento\BonusBase\Repo\Entity\Data\Period | array $data)
 */
class GetForDependentCalc extends \Praxigento\Core\Service\Base\Response
{

}