<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Dcp\Report\Downline\Response;

/**
 * Accessors use 'CamelCase' naming strategy (data object default), but data inside use 'snake_case' naming strategy
 * (API default). Repo queries should use 'snake_case' namings to prepare array data, DataObject will return
 * 'snake_case' property if 'CamelCase' will not be found.
 *
 * @method string getCountry()
 * @method void setCountry(string $data)
 * @method int getCustomerRef()
 * @method void setCustomerRef(int $data)
 * @method int getDepth()
 * @method void setDepth(int $data)
 * @method string getEmail()
 * @method void setEmail(string $data)
 * @method int getMlmId()
 * @method void setMlmId(int $data)
 * @method string getNameFirst()
 * @method void setNameFirst(string $data)
 * @method string getNameLast()
 * @method void setNameLastId(string $data)
 * @method float getOv()
 * @method void setOv(float $data)
 * @method int getParentRef()
 * @method void setParentRef(int $data)
 * @method string getPath()
 * @method void setPath(string $data)
 * @method float getPv()
 * @method void setPv(float $data)
 * @method string getRankCode()
 * @method void setRankCode(string $data)
 * @method float getTv()
 * @method void setTv(float $data)
 * @method int getUnqMonths()
 * @method void setUnqMonths(int $data)
 */
class Entry
    extends \Flancer32\Lib\Data
{
    const A_COUNTRY = 'country';
    const A_CUSTOMER_REF = 'customer_ref';
    const A_DEPTH = 'depth';
    const A_EMAIL = 'email';
    const A_MLM_ID = 'mlm_id';
    const A_NAME_FIRST = 'name_first';
    const A_NAME_LAST = 'name_last';
    const A_OV = 'ov';
    const A_PARENT_REF = 'parent_ref';
    const A_PATH = 'path';
    const A_PV = 'pv';
    const A_RANK_CODE = 'rank_code';
    const A_TV = 'tv';
    const A_UNQ_MONTHS = 'unq_months';

}