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
         * Forzamos 0 decimales ya que la moneda PYG no los utiliza para este ajuste.
         *
         * @returns {string}
         */
        getValue: function () {
            var segment = this.getSegment();
            var format = JSON.parse(JSON.stringify(quote.getPriceFormat()));
            format.precision = 0;
            format.requiredPrecision = 0;

            if (!segment) {
                var zeroFormat = priceUtils.formatPrice(0, format);
                return zeroFormat.replace(/\.00|,00/g, '');
            }
            
            var formattedPrice = priceUtils.formatPrice(segment.value, format);
            return formattedPrice.replace(/\.00|,00/g, '');
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
