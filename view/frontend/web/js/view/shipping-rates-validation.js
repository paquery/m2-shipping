/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

define([
    'uiComponent',
    'Magento_Checkout/js/model/shipping-rates-validator',
    'Magento_Checkout/js/model/shipping-rates-validation-rules',
    'Paquery_Shipping/js/model/shipping-rates-validator',
    'Paquery_Shipping/js/model/shipping-rates-validation-rules'
], function (
    Component,
    defaultShippingRatesValidator,
    defaultShippingRatesValidationRules,
    paqueryShippingRatesValidator,
    paqueryShippingRatesValidationRules
) {
    'use strict';

    defaultShippingRatesValidator.registerValidator('paquery', paqueryShippingRatesValidator);
    defaultShippingRatesValidationRules.registerRules('paquery', paqueryShippingRatesValidationRules);

    return Component;
});
