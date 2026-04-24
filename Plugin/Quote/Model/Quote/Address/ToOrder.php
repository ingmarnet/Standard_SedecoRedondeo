<?php
/**
 * Standard_SedecoRedondeo
 *
 * Plugin: Quote Address → Order
 * Propaga el atributo sedeco_round_amount desde el address de la cotización
 * hacia la Order, para que el valor quede inmutable en el pedido.
 *
 * @author    Standard SA
 */
declare(strict_types=1);

namespace Standard\SedecoRedondeo\Plugin\Quote\Model\Quote\Address;

use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Magento\Sales\Api\Data\OrderInterface;

class ToOrder
{
    /**
     * @param \Magento\Quote\Model\Quote\Address\ToOrder $subject
     * @param OrderInterface                              $result
     * @param QuoteAddress                                $quoteAddress
     * @return OrderInterface
     */
    public function afterConvert(
        \Magento\Quote\Model\Quote\Address\ToOrder $subject,
        OrderInterface $result,
        QuoteAddress $quoteAddress
    ): OrderInterface {
        $roundAmount = $quoteAddress->getData('sedeco_round_amount');

        if ($roundAmount !== null) {
            $result->setData('sedeco_round_amount', (float) $roundAmount);
        }

        return $result;
    }
}
