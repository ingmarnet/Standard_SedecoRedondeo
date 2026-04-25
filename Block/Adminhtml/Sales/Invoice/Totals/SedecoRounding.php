<?php
/**
 * Standard_SedecoRedondeo
 *
 * Block: Totales de Invoice en el Admin (Sales > Invoices > View)
 * Renderiza la línea "Redondeo Resolución SEDECO 1670/22" en el panel
 * de totales de la vista de la factura en el backend.
 *
 * @author    Standard SA
 */
declare(strict_types=1);

namespace Standard\SedecoRedondeo\Block\Adminhtml\Sales\Invoice\Totals;

use Magento\Framework\DataObject;
use Magento\Framework\View\Element\Template;
use Magento\Sales\Model\Order\Invoice;

class SedecoRounding extends Template
{
    /**
     * @var Invoice|null
     */
    private ?Invoice $invoice = null;

    /**
     * @return $this
     */
    public function initTotals(): static
    {
        $parent = $this->getParentBlock();

        // Magento\Sales\Block\Adminhtml\Order\Invoice\Totals puede exponer
        // la invoice via getInvoice() o via getSource() según la versión.
        $this->invoice = $parent->getInvoice()
            ?? ($parent->hasMethod('getSource') ? $parent->getSource() : null);

        if (!$this->invoice) {
            return $this;
        }

        $roundAmount = (float) $this->invoice->getData('sedeco_round_amount');

        if ($roundAmount === 0.0) {
            return $this;
        }

        $parent->addTotal(
            new DataObject([
                'code'       => 'sedeco_redondeo',
                'strong'     => false,
                'value'      => $roundAmount,
                'base_value' => $roundAmount,
                'label'      => __('Redondeo Resolución SEDECO 1670/22'),
            ]),
            'grand_total'
        );

        return $this;
    }
}
