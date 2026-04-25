<?php
/**
 * Standard_SedecoRedondeo
 * Plugin: Agrega el total de redondeo SEDECO al bloque de totales de la Orden en Admin.
 */
declare(strict_types=1);

namespace Standard\SedecoRedondeo\Plugin\Block\Adminhtml\Order;

use Magento\Framework\DataObject;
use Magento\Sales\Block\Adminhtml\Order\Totals as OrderTotals;

class AddSedecoTotal
{
    public function beforeToHtml(OrderTotals $subject): array
    {
        $order = $subject->getOrder();
        if (!$order) {
            return [];
        }

        $roundAmount = (float) $order->getData('sedeco_round_amount');
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
