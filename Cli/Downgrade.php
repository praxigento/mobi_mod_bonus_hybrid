<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Cli;

use Praxigento\BonusHybrid\Service\Downgrade\Request as ARequest;

/**
 * Downgrade unqualified customers.
 */
class Downgrade
    extends \Praxigento\Core\App\Cli\Cmd\Base
{
    /** @var \Praxigento\BonusHybrid\Service\Downgrade */
    private $srvDowngrade;

    public function __construct(
        \Magento\Framework\ObjectManagerInterface $manObj,
        \Magento\Framework\App\ResourceConnection $resource,
        \Praxigento\BonusHybrid\Service\Downgrade $servDowngrade
    ) {
        parent::__construct(
            'prxgt:bonus:downgrade',
            'Downgrade unqualified customers.'
        );
        $this->srvDowngrade = $servDowngrade;
    }

    protected function process(\Symfony\Component\Console\Input\InputInterface $input)
    {
        $req = new ARequest();
        $this->srvDowngrade->exec($req);
    }

}