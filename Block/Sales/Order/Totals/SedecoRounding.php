<?php
/**
 * Standard_SedecoRedondeo
 *
 * Block: Totales de Orden (Frontend y Email)
 * Renderiza la línea "Redondeo Resolución SEDECO 1670/22" en el panel
 * de totales de la orden para correos y la cuenta del cliente.
 *
 * @author    Standard SA
 */
declare(strict_types=1);

namespace Standard\SedecoRedondeo\Block\Sales\Order\Totals;

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
     * @return $this
     */
    public function initTotals(): static
    {
        $parent = $this->getParentBlock();
        $this->order = $parent->getOrder();

        if (!$this->order) {
            return $this;
        }

        $roundAmount = (float) $this->order->getData('sedeco_round_amount');

        if ($roundAmount === 0.0) {
            return $this;
        }

        $formattedValue = $this->order->formatPrice($roundAmount);
        $formattedValue = str_replace([',00', '.00'], '', $formattedValue);

        $parent->addTotal(
            new DataObject([
                'code'        => 'sedeco_redondeo',
                'strong'      => false,
                'value'       => $formattedValue,
                'base_value'  => $formattedValue,
                'label'       => __('Redondeo Resolución SEDECO 1670/22'),
                'is_formated' => true,
            ]),
            'grand_total'
        );

        return $this;
    }
}
