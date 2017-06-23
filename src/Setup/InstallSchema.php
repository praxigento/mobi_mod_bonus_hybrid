<?php
/**
 * Create DB schema.
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Setup;

use Praxigento\BonusHybrid\Repo\Data\Entity\Actual\Downline\Plain as ActDwnlPlain;
use Praxigento\BonusHybrid\Repo\Data\Entity\Cfg\Override as CfgOverride;
use Praxigento\BonusHybrid\Repo\Data\Entity\Cfg\Param as CfgParam;
use Praxigento\BonusHybrid\Repo\Data\Entity\Compression\Oi as OiCompress;
use Praxigento\BonusHybrid\Repo\Data\Entity\Compression\Phase1\Transfer\Pv as Phase1TransPv;
use Praxigento\BonusHybrid\Repo\Data\Entity\Registry\SignupDebit as SignupDebit;
use Praxigento\BonusHybrid\Repo\Data\Entity\Retro\Downline\Compressed\Phase1 as CmprsPhase1;
use Praxigento\BonusHybrid\Repo\Data\Entity\Retro\Downline\Plain as RetroDwnlPlain;

class InstallSchema extends \Praxigento\Core\Setup\Schema\Base
{
    protected function _setup()
    {
        /** Read and parse JSON schema. */
        $pathToFile = __DIR__ . '/../etc/dem.json';
        $pathToNode = '/dBEAR/package/Praxigento/package/Bonus/package/Hybrid';
        $demPackage = $this->_toolDem->readDemPackage($pathToFile, $pathToNode);

        /* Actual Downline Plain Tree */
        $entityAlias = ActDwnlPlain::ENTITY_NAME;
        $demEntity = $demPackage->get('package/Actual/package/Downline/entity/Plain');
        $this->_toolDem->createEntity($entityAlias, $demEntity);

        /* Config Override */
        $entityAlias = CfgOverride::ENTITY_NAME;
        $demEntity = $demPackage->get('package/Config/entity/Override');
        $this->_toolDem->createEntity($entityAlias, $demEntity);

        /* Config Param */
        $entityAlias = CfgParam::ENTITY_NAME;
        $demEntity = $demPackage->get('package/Config/entity/Parameter');
        $this->_toolDem->createEntity($entityAlias, $demEntity);

        /* Compression / Phase I / Transfer / PV */
        $entityAlias = Phase1TransPv::ENTITY_NAME;
        $demEntity = $demPackage->get('package/Compression/package/Phase1/package/Transfer/entity/Pv');
        $this->_toolDem->createEntity($entityAlias, $demEntity);

        /* Compression OI */
        $entityAlias = OiCompress::ENTITY_NAME;
        $demEntity = $demPackage->get('package/Compression/entity/OI');
        $this->_toolDem->createEntity($entityAlias, $demEntity);

        /* Registry Sign Up Volume Debit */
        $entityAlias = SignupDebit::ENTITY_NAME;
        $demEntity = $demPackage->get('package/Registry/entity/SignUpVolumeDebit');
        $this->_toolDem->createEntity($entityAlias, $demEntity);

        /* Retrospective Plain Downline Tree */
        $entityAlias = RetroDwnlPlain::ENTITY_NAME;
        $demEntity = $demPackage->get('package/Retro/package/Downline/entity/Plain');
        $this->_toolDem->createEntity($entityAlias, $demEntity);

        /* Retrospective Downline Tree that is Compressed in Phase 1 */
        $entityAlias = CmprsPhase1::ENTITY_NAME;
        $demEntity = $demPackage->get('package/Retro/package/Downline/package/Compressed/entity/Phase1');
        $this->_toolDem->createEntity($entityAlias, $demEntity);

    }


}