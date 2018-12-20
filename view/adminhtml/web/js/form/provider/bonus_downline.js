/**
 * Override default form provider"s functionality to reload page directly from JS.
 */
define([
    "mage/url",
    "Magento_Ui/js/form/provider"
], function (url, Element) {
    "use strict";
    /* see Praxigento_BonusHybrid:view/adminhtml/ui_component/prxgt_bonus_downline_select.xml */
    const FIELDSET = "downline_select";
    const FLD_PERIOD = "period";
    const FLD_TYPE = "tree_type";
    /* see \Praxigento\BonusHybrid\Ui\DataProvider\Downline\Z\Input::REQ_... */
    const REQ_PERIOD = "period";
    const REQ_TYPE = "type";

    return Element.extend({
        /**
         * Saves currently available data.
         *
         * @param {Object} [options] - Additional request options.
         * @returns {Provider} Chainable.
         */
        save: function (options) {
            const data = this.get("data");
            const period = data[FIELDSET][FLD_PERIOD];
            const type = data[FIELDSET][FLD_TYPE];
            const redirectTo = BASE_URL + "downline/index/" + REQ_PERIOD + "/" + period + "/" + REQ_TYPE + "/" + type;
            const redirectUrl = url.build(redirectTo);
            window.location.href = redirectUrl;
            /**
             * Use this to call the parent method:
             *
             * let result = Element.prototype.save.apply(this, [options]);
             */
            return this;
        },

    });
});
