<?php
/**
 * Create DB schema.
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Setup;

use Praxigento\BonusHybrid\Entity\Cache\Downline\Plain as CacheDwnlPlain;
use Praxigento\BonusHybrid\Entity\Cfg\Override as CfgOverride;
use Praxigento\BonusHybrid\Entity\Cfg\Param as CfgParam;
use Praxigento\BonusHybrid\Entity\Compression\Oi as OiCompress;
use Praxigento\BonusHybrid\Entity\Compression\Ptc as PtcCompress;
use Praxigento\BonusHybrid\Entity\Registry\Pto as RegPto;
use Praxigento\BonusHybrid\Entity\Registry\SignupDebit as SignupDebit;

class InstallSchema extends \Praxigento\Core\Setup\Schema\Base
{
    protected function _setup()
    {
        /** Read and parse JSON schema. */
        $pathToFile = __DIR__ . '/../etc/dem.json';
        $pathToNode = '/dBEAR/package/Praxigento/package/Bonus/package/Hybrid';
        $demPackage = $this->_toolDem->readDemPackage($pathToFile, $pathToNode);

        /* Cache Downline Plain */
        $entityAlias = CacheDwnlPlain::ENTITY_NAME;
        $demEntity = $demPackage->get('package/Cache/package/Downline/entity/Plain');
        $this->_toolDem->createEntity($entityAlias, $demEntity);

        /* Config Override */
        $entityAlias = CfgOverride::ENTITY_NAME;
        $demEntity = $demPackage->get('package/Config/entity/Override');
        $this->_toolDem->createEntity($entityAlias, $demEntity);

        /* Config Param */
        $entityAlias = CfgParam::ENTITY_NAME;
        $demEntity = $demPackage->get('package/Config/entity/Parameter');
        $this->_toolDem->createEntity($entityAlias, $demEntity);

        /* Compression PTC */
        $entityAlias = PtcCompress::ENTITY_NAME;
        $demEntity = $demPackage->get('package/Compression/entity/PTC');
        $this->_toolDem->createEntity($entityAlias, $demEntity);

        /* Compression OI */
        $entityAlias = OiCompress::ENTITY_NAME;
        $demEntity = $demPackage->get('package/Compression/entity/OI');
        $this->_toolDem->createEntity($entityAlias, $demEntity);

        /* Registry Sign Up Volume Debit */
        $entityAlias = SignupDebit::ENTITY_NAME;
        $demEntity = $demPackage->get('package/Registry/entity/SignUpVolumeDebit');
        $this->_toolDem->createEntity($entityAlias, $demEntity);

        /* Registry Sign Up Volume Debit */
        $entityAlias = RegPto::ENTITY_NAME;
        $demEntity = $demPackage->get('package/Registry/entity/PTO');
        $this->_toolDem->createEntity($entityAlias, $demEntity);
    }


}