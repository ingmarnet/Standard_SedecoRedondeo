<?php
/**
 * Standard_SedecoRedondeo
 *
 * Plugin: InvoiceService
 * Copia sedeco_round_amount de Order → Invoice al crear la factura,
 * manteniendo el valor inmutable en la Invoice.
 *
 * @author    Standard SA
 */
declare(strict_types=1);

namespace Standard\SedecoRedondeo\Plugin\Sales\Model\Service;

use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Service\InvoiceService as MagentoInvoiceService;

class InvoiceService
{
    /**
     * @param MagentoInvoiceService $subject
     * @param InvoiceInterface      $result
     * @param OrderInterface        $order
     * @return InvoiceInterface
     */
    public function afterPrepareInvoice(
        MagentoInvoiceService $subject,
        InvoiceInterface $result,
        OrderInterface $order
    ): InvoiceInterface {
        $roundAmount = $order->getData('sedeco_round_amount');

        if ($roundAmount !== null) {
            $result->setData('sedeco_round_amount', (float) $roundAmount);
        }

        return $result;
    }
}
