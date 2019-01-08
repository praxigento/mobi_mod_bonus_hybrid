<?php
/**
 * Create DB schema.
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Setup;

use Praxigento\BonusHybrid\Repo\Data\Cfg\Override as CfgOverride;
use Praxigento\BonusHybrid\Repo\Data\Cfg\Param as CfgParam;
use Praxigento\BonusHybrid\Repo\Data\Compression\Phase1\Transfer\Pv as Phase1TransPv;
use Praxigento\BonusHybrid\Repo\Data\Compression\Phase2\Legs as Phase2Legs;
use Praxigento\BonusHybrid\Repo\Data\Downline as Dwnl;
use Praxigento\BonusHybrid\Repo\Data\Downline\Inactive as DwnlInact;
use Praxigento\BonusHybrid\Repo\Data\Registry\SignUpDebit as SignUpDebit;

class InstallSchema
    extends \Praxigento\Core\App\Setup\Schema\Base
{
    protected function setup()
    {
        /** Read and parse JSON schema. */
        $pathToFile = __DIR__ . '/../etc/dem.json';
        $pathToNode = '/dBEAR/package/Praxigento/package/Bonus/package/Hybrid';
        $demPackage = $this->toolDem->readDemPackage($pathToFile, $pathToNode);

        /* Downline Tree (common for actual/retro & plain/compressed cases)*/
        $demEntity = $demPackage->get('entity/Downline');
        $this->toolDem->createEntity(Dwnl::ENTITY_NAME, $demEntity);

        /* Config Override */
        $demEntity = $demPackage->get('package/Config/entity/Override');
        $this->toolDem->createEntity(CfgOverride::ENTITY_NAME, $demEntity);

        /* Config Param */
        $demEntity = $demPackage->get('package/Config/entity/Parameter');
        $this->toolDem->createEntity(CfgParam::ENTITY_NAME, $demEntity);

        /* Registry Sign Up Volume Debit */
        $demEntity = $demPackage->get('package/Registry/entity/SignUpVolumeDebit');
        $this->toolDem->createEntity(SignUpDebit::ENTITY_NAME, $demEntity);

        /* Compression / Phase I / Transfer / PV */
        $demEntity = $demPackage->get('package/Compression/package/Phase1/package/Transfer/entity/Pv');
        $this->toolDem->createEntity(Phase1TransPv::ENTITY_NAME, $demEntity);

        /* Compression / Phase II / Legs */
        $demEntity = $demPackage->get('package/Compression/package/Phase2/entity/Legs');
        $this->toolDem->createEntity(Phase2Legs::ENTITY_NAME, $demEntity);

        /* Downline / Inactive */
        $demEntity = $demPackage->get('package/Downline/entity/Inactive');
        $this->toolDem->createEntity(DwnlInact::ENTITY_NAME, $demEntity);

    }


}