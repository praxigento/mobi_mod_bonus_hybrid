<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Repo\Data\Downline;

/**
 * Customer qualification data for downline trees.
 */
class Qualification
    extends \Praxigento\Core\App\Repo\Data\Entity\Base
{
    /* names of the entity attributes (table columns) */
    const A_RANK_REF = 'rank_ref';
    const A_TREE_ENTRY_REF = 'tree_entry_ref';

    /* entity (table) name */
    const ENTITY_NAME = 'prxgt_bon_hyb_dwnl_qual';

    public static function getPrimaryKeyAttrs()
    {
        return [self::A_TREE_ENTRY_REF];
    }

    public function getRankRef()
    {
        $result = parent::get(self::A_RANK_REF);
        return $result;
    }

    public function getTreeEntryRef()
    {
        $result = parent::get(self::A_TREE_ENTRY_REF);
        return $result;
    }

    public function setRankRef($data)
    {
        parent::set(self::A_RANK_REF, $data);
    }

    public function setTreeEntryRef($data)
    {
        parent::set(self::A_TREE_ENTRY_REF, $data);
    }

}