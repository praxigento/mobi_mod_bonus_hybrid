<?php
/**
 * Populate DB schema with module's initial data
 * .
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Setup;

class InstallData extends \Praxigento\Core\Setup\Data\Base {

    public function __construct() {
        parent::__construct('Praxigento\Bonus\Hybrid\Lib\Setup\Data');
    }

}