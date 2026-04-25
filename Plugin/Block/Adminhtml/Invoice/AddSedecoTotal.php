<?php
/**
 * Standard_SedecoRedondeo
 * Plugin: Agrega el total de redondeo SEDECO al bloque de totales de la Invoice en Admin.
 */
declare(strict_types=1);

namespace Standard\SedecoRedondeo\Plugin\Block\Adminhtml\Invoice;

use Magento\Framework\DataObject;
use Magento\Sales\Block\Adminhtml\Order\Invoice\Totals as InvoiceTotals;

class AddSedecoTotal
{
    public function beforeToHtml(InvoiceTotals $subject): array
    {
        $invoice = $subject->getInvoice();
        if (!$invoice) {
            return [];
        }

        $roundAmount = (float) $invoice->getData('sedeco_round_amount');
        if ($roundAmount === 0.0) {
            return [];
        }

        $subject->addTotal(
            new DataObject([
                'code'       => 'sedeco_redondeo',
                'strong'     => false,
                'value'      => $roundAmount,
                'base_value' => $roundAmount,
                'label'      => __('Redondeo Resolución SEDECO 1670/22'),
            ]),
            'shipping'
        );

        return [];
    }
}
