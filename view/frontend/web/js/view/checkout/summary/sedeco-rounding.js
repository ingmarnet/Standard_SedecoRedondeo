/**
 * Standard_SedecoRedondeo
 *
 * Componente Knockout.js para mostrar el monto de redondeo SEDECO
 * en el Order Summary del checkout de Magento 2.
 *
 * Extiende el componente base de totales de Magento para obtener
 * automáticamente el valor del segmento 'sedeco_redondeo'.
 */
define([
    'Magento_Checkout/js/view/summary/abstract-total',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/totals',
    'Magento_Catalog/js/price-utils'
], function (Component, quote, totals, priceUtils) {
    'use strict';

    return Component.extend({

        defaults: {
            template: 'Standard_SedecoRedondeo/checkout/summary/sedeco-rounding',
            title: 'Redondeo Resolución SEDECO 1670/22'
        },

        /**
         * Retorna true si el segmento de totales sedeco_redondeo existe y tiene valor.
         *
         * @returns {boolean}
         */
        isDisplayed: function () {
            var segment = this.getSegment();
            return segment !== null && parseFloat(segment.value) !== 0;
        },

        /**
         * Obtiene el segmento de totales de Magento para sedeco_redondeo.
         *
         * @returns {Object|null}
         */
        getSegment: function () {
            if (!totals.getSegment('sedeco_redondeo')) {
                return null;
            }
            return totals.getSegment('sedeco_redondeo');
        },

        /**
         * Retorna el valor del redondeo formateado con la moneda activa.
         *
         * @returns {string}
         */
        getValue: function () {
            var segment = this.getSegment();
            if (!segment) {
                return priceUtils.formatPrice(0, quote.getPriceFormat());
            }
            return priceUtils.formatPrice(segment.value, quote.getPriceFormat());
        },

        /**
         * Retorna el título de la línea.
         *
         * @returns {string}
         */
        getTitle: function () {
            return this.title;
        }
    });
});
