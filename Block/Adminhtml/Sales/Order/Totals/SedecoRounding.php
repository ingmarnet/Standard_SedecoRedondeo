<?php
/**
 * Standard_SedecoRedondeo
 *
 * Block: Totales de Orden en el Admin (Sales > Orders > View)
 * Renderiza la línea "Redondeo Resolución SEDECO 1670/22" en el panel
 * de totales de la vista de la orden en el backend.
 *
 * Requiere layout: sales_order_view.xml → block en "order_totals"
 *
 * @author    Standard SA
 */
declare(strict_types=1);

namespace Standard\SedecoRedondeo\Block\Adminhtml\Sales\Order\Totals;

use Magento\Framework\DataObject;
use Magento\Framework\View\Element\Template;
use Magento\Sales\Model\Order;

class SedecoRounding extends Template
{
    /**
     * @var Order|null
     */
    private ?Order $order = null;

    /**
     * Agrega la línea de redondeo al bloque de totales del parent.
     *
     * @return $this
     */
    public function initTotals(): static
    {
        $parent = $this->getParentBlock();
        $this->order = $parent->getOrder();

        $roundAmount = (float) $this->order->getData('sedeco_round_amount');

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
            'grand_total' // Se inserta antes del grand_total
        );

        return $this;
    }
}
