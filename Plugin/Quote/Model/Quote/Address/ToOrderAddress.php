<?php
/**
 * Standard_SedecoRedondeo
 *
 * Plugin: Quote Address → Order Address
 * Propaga el atributo sedeco_round_amount desde quote_address → order_address.
 *
 * @author    Standard SA
 */
declare(strict_types=1);

namespace Standard\SedecoRedondeo\Plugin\Quote\Model\Quote\Address;

use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Magento\Sales\Api\Data\OrderAddressInterface;

class ToOrderAddress
{
    /**
     * @param \Magento\Quote\Model\Quote\Address\ToOrderAddress $subject
     * @param OrderAddressInterface                              $result
     * @param QuoteAddress                                       $quoteAddress
     * @return OrderAddressInterface
     */
    public function afterConvert(
        \Magento\Quote\Model\Quote\Address\ToOrderAddress $subject,
        OrderAddressInterface $result,
        QuoteAddress $quoteAddress
    ): OrderAddressInterface {
        $roundAmount = $quoteAddress->getData('sedeco_round_amount');

        if ($roundAmount !== null) {
            $result->setData('sedeco_round_amount', (float) $roundAmount);
        }

        return $result;
    }
}
