/**
 * Override default grid provider's functionality to add part of URL to data request parameters.
 *
 * http://.../admin/bonus/downline/index/period/201812/type/plain
 *
 *  - period = YYYYMM
 *  - type = [compressed|plain]
 *
 */
define([
    "Magento_Ui/js/grid/provider"
], function (Element) {
    "use strict";

    /**
     * URL placed parameters.
     * See  \Praxigento\BonusHybrid\Ui\DataProvider\Grid\Downline::REQ_...
     */
    const REQ_PERIOD = "period";
    const REQ_TYPE = "type";

    return Element.extend({

        /**
         * Analyze current URL and add parameters before reload data.
         *
         * @returns {Promise} Reload promise object.
         */
        reload: function (options) {
            /* extract query parameters from URL */
            const url = window.location.href;
            const regexpPeriod = new RegExp("/" + REQ_PERIOD + "/(\\d+)", "i");
            /* see \Praxigento\BonusHybrid\Ui\DataProvider\Options\TreeType::VAL_... for type values */
            const regexpType = new RegExp("/" + REQ_TYPE + "/((compressed)|(plain))", "i");
            let parsedPeriod = url.match(regexpPeriod);
            let parsedType = url.match(regexpType);
            /* then add parameters to grid data request */
            if (parsedPeriod && parsedPeriod[1]) {
                this.params[REQ_PERIOD] = parsedPeriod[1];
            }
            if (parsedType && parsedType[1]) {
                this.params[REQ_TYPE] = parsedType[1];
            }
            let result = Element.prototype.reload.apply(this, [options]);
            return result;
        },

    });
});
