<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Cli;

use Praxigento\BonusHybrid\Service\United\Forecast\Request as ARequest;
use Praxigento\BonusHybrid\Service\United\Forecast\Response as AResponse;

/**
 * Daily calculation to forecast results on final bonus calc.
 */
class Forecast
    extends \Praxigento\Core\App\Cli\Cmd\Base
{
    /** @var \Praxigento\BonusHybrid\Service\United\Forecast */
    private $servUnited;

    public function __construct(
        \Praxigento\BonusHybrid\Service\United\Forecast $servUnited
    ) {
        parent::__construct(
            'prxgt:bonus:forecast',
            'Daily calculations to forecast results on final bonus calc.'
        );
        $this->servUnited = $servUnited;
    }

    protected function process(\Symfony\Component\Console\Input\InputInterface $input)
    {
        $req = new ARequest();
        $this->servUnited->exec($req);
    }

}