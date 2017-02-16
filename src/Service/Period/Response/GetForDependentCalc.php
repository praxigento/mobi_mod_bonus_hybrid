<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Service\Period\Response;

/**
 * Response contains arrays with period and calculation data for base and dependent calculations.
 *
 * @method \Praxigento\BonusBase\Data\Entity\Calculation|null getBaseCalcData()
 * @method void setBaseCalcData(\Praxigento\BonusBase\Data\Entity\Calculation|array $data)
 * @method \Praxigento\BonusBase\Data\Entity\Period|null getBasePeriodData()
 * @method void setBasePeriodData(\Praxigento\BonusBase\Data\Entity\Period|array $data)
 * @method \Praxigento\BonusBase\Data\Entity\Calculation|null getDependentCalcData()
 * @method void setDependentCalcData(\Praxigento\BonusBase\Data\Entity\Calculation|array $data)
 * @method \Praxigento\BonusBase\Data\Entity\Period|null getDependentPeriodData()
 * @method void setDependentPeriodData(\Praxigento\BonusBase\Data\Entity\Period|array $data)
 */
class GetForDependentCalc extends \Praxigento\Core\Service\Base\Response
{

}