<?php
/**
 * Create DB schema.
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Praxigento\Bonus\Hybrid\Lib\Entity\Cfg\Override as CfgOverride;
use Praxigento\Bonus\Hybrid\Lib\Entity\Cfg\Param as CfgParam;
use Praxigento\Bonus\Hybrid\Lib\Entity\Compression\Oi as OiCompress;
use Praxigento\Bonus\Hybrid\Lib\Entity\Compression\Ptc as PtcCompress;
use Praxigento\Bonus\Hybrid\Lib\Entity\Config as HybridCfg;
use Praxigento\Core\Lib\Setup\Db as Db;

class InstallSchema extends \Praxigento\Core\Setup\Schema\Base
{
    protected function _setup(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        /** Read and parse JSON schema. */
        $pathToFile = __DIR__ . '/../etc/dem.json';
        $pathToNode = '/dBEAR/package/Praxigento/package/Bonus/package/Hybrid';
        $demPackage = $this->_toolDem->readDemPackage($pathToFile, $pathToNode);

        /* Config Override */
        $entityAlias = CfgOverride::ENTITY_NAME;
        $demEntity = $demPackage['package']['Config']['entity']['Override'];
        $this->_toolDem->createEntity($entityAlias, $demEntity);

        /* Config Param */
        $entityAlias = CfgParam::ENTITY_NAME;
        $demEntity = $demPackage['package']['Config']['entity']['Parameter'];
        $this->_toolDem->createEntity($entityAlias, $demEntity);

        /* Compression PTC */
        $entityAlias = PtcCompress::ENTITY_NAME;
        $demEntity = $demPackage['package']['Compression']['entity']['PTC'];
        $this->_toolDem->createEntity($entityAlias, $demEntity);

        /* Compression OI */
        $entityAlias = OiCompress::ENTITY_NAME;
        $demEntity = $demPackage['package']['Compression']['entity']['OI'];
        $this->_toolDem->createEntity($entityAlias, $demEntity);
    }


}