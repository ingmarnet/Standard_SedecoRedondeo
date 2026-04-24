<?php
/**
 * Standard_SedecoRedondeo
 *
 * Block: Totales de Invoice (Frontend y Email)
 * Renderiza la línea "Redondeo Resolución SEDECO 1670/22" en el panel
 * de totales de la factura para correos y la cuenta del cliente.
 *
 * @author    Standard SA
 */
declare(strict_types=1);

namespace Standard\SedecoRedondeo\Block\Sales\Invoice\Totals;

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
        $this->invoice = $parent->getInvoice();

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
