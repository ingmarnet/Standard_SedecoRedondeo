<?php
/**
 * Standard_SedecoRedondeo
 *
 * Plugin: Sales Order Invoice
 * Ajusta el Grand Total de la invoice para reflejar el redondeo SEDECO.
 * Esto garantiza que el monto en el PDF y en la vista del backend sea correcto.
 *
 * @author    Standard SA
 */
declare(strict_types=1);

namespace Standard\SedecoRedondeo\Plugin\Sales\Model\Order;

use Magento\Sales\Model\Order\Invoice as MagentoInvoice;

class Invoice
{
    /**
     * Después de calcular los totales de la invoice, ajusta el Grand Total
     * con el monto de redondeo SEDECO (valor negativo = descuento).
     *
     * @param MagentoInvoice $subject
     * @param MagentoInvoice $result
     * @return MagentoInvoice
     */
    public function afterCollectTotals(
        MagentoInvoice $subject,
        MagentoInvoice $result
    ): MagentoInvoice {
        $order = $result->getOrder();
        if (!$order) {
            return $result;
        }

        $roundAmount = (float) $order->getData('sedeco_round_amount');

        if ($roundAmount !== 0.0) {
            $result->setData('sedeco_round_amount', $roundAmount);
            $currentGrandTotal = (float) $result->getGrandTotal();
            $result->setGrandTotal($currentGrandTotal + $roundAmount);
            $result->setBaseGrandTotal((float) $result->getBaseGrandTotal() + $roundAmount);
        }

        return $result;
    }
}
