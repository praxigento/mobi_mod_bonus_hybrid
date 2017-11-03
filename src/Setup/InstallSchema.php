<?php
/**
 * Create DB schema.
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Setup;

use Praxigento\BonusHybrid\Repo\Entity\Data\Cfg\Override as CfgOverride;
use Praxigento\BonusHybrid\Repo\Entity\Data\Cfg\Param as CfgParam;
use Praxigento\BonusHybrid\Repo\Entity\Data\Compression\Phase1\Transfer\Pv as Phase1TransPv;
use Praxigento\BonusHybrid\Repo\Entity\Data\Compression\Phase2\Legs as Phase2Legs;
use Praxigento\BonusHybrid\Repo\Entity\Data\Downline as Dwnl;
use Praxigento\BonusHybrid\Repo\Entity\Data\Downline\Qualification as DwnlQual;
use Praxigento\BonusHybrid\Repo\Entity\Data\Registry\SignupDebit as SignupDebit;

class InstallSchema
    extends \Praxigento\Core\Setup\Schema\Base
{
    protected function _setup()
    {
        /** Read and parse JSON schema. */
        $pathToFile = __DIR__ . '/../etc/dem.json';
        $pathToNode = '/dBEAR/package/Praxigento/package/Bonus/package/Hybrid';
        $demPackage = $this->_toolDem->readDemPackage($pathToFile, $pathToNode);

        /* Downline Tree (common for actual/retro & plain/compressed cases)*/
        $entityAlias = Dwnl::ENTITY_NAME;
        $demEntity = $demPackage->get('entity/Downline');
        $this->_toolDem->createEntity($entityAlias, $demEntity);

        /* Config Override */
        $entityAlias = CfgOverride::ENTITY_NAME;
        $demEntity = $demPackage->get('package/Config/entity/Override');
        $this->_toolDem->createEntity($entityAlias, $demEntity);

        /* Config Param */
        $entityAlias = CfgParam::ENTITY_NAME;
        $demEntity = $demPackage->get('package/Config/entity/Parameter');
        $this->_toolDem->createEntity($entityAlias, $demEntity);

        /* Registry Sign Up Volume Debit */
        $entityAlias = SignupDebit::ENTITY_NAME;
        $demEntity = $demPackage->get('package/Registry/entity/SignUpVolumeDebit');
        $this->_toolDem->createEntity($entityAlias, $demEntity);

        /* Compression / Phase I / Transfer / PV */
        $entityAlias = Phase1TransPv::ENTITY_NAME;
        $demEntity = $demPackage->get('package/Compression/package/Phase1/package/Transfer/entity/Pv');
        $this->_toolDem->createEntity($entityAlias, $demEntity);

        /* Compression / Phase II / Legs */
        $entityAlias = Phase2Legs::ENTITY_NAME;
        $demEntity = $demPackage->get('package/Compression/package/Phase2/entity/Legs');
        $this->_toolDem->createEntity($entityAlias, $demEntity);

        /* Downline / Qualification */
        $entityAlias = DwnlQual::ENTITY_NAME;
        $demEntity = $demPackage->get('package/Downline/entity/Qualification');
        $this->_toolDem->createEntity($entityAlias, $demEntity);

    }


}