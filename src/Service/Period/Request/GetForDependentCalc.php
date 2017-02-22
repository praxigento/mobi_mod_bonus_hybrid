<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Service\Period\Request;

/**
 * Set BaseCalcCode and DependentCalcCode in request.
 *
 * @method string getBaseCalcTypeCode()
 * @method void setBaseCalcTypeCode(string $data)
 * @method string getDependentCalcTypeCode()
 * @method void setDependentCalcTypeCode(string $data)
 * @method string getAllowIncompleteBaseCalc()
 * @method void setAllowIncompleteBaseCalc(bool $data)
 */
class GetForDependentCalc extends \Praxigento\Core\Service\Base\Request {
}