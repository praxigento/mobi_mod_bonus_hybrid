<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Repo\Entity\Data\Downline;

/**
 * Customer qualification data for downline trees.
 */
class Qualification
    extends \Praxigento\Core\Data\Entity\Base
{
    /* names of the entity attributes (table columns) */
    const ATTR_RANK_REF = 'rank_ref';
    const ATTR_TREE_ENTRY_REF = 'tree_entry_ref';

    /* entity (table) name */
    const ENTITY_NAME = 'prxgt_bon_hyb_dwnl_qual';

    public static function getPrimaryKeyAttrs()
    {
        return [self::ATTR_TREE_ENTRY_REF];
    }

    public function getRankRef()
    {
        $result = parent::get(self::ATTR_RANK_REF);
        return $result;
    }

    public function getTreeEntryRef()
    {
        $result = parent::get(self::ATTR_TREE_ENTRY_REF);
        return $result;
    }

    public function setRankRef($data)
    {
        parent::set(self::ATTR_RANK_REF, $data);
    }

    public function setTreeEntryRef($data)
    {
        parent::set(self::ATTR_TREE_ENTRY_REF, $data);
    }

}