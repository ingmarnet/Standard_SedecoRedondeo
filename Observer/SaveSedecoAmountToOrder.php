<?php
/**
 * Standard_SedecoRedondeo
 *
 * Observer: checkout_submit_all_after
 * Lee el sedeco_round_amount desde el Quote y lo persiste directamente
 * en la sales_order. Es el único punto donde AMBOS objetos están disponibles
 * y la Order ya tiene entity_id para poder guardarse.
 *
 * @author    Standard SA
 */
declare(strict_types=1);

namespace Standard\SedecoRedondeo\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\ResourceModel\Order as OrderResource;

class SaveSedecoAmountToOrder implements ObserverInterface
{
    public function __construct(
        private readonly OrderResource $orderResource
    ) {}

    /**
     * @param Observer $observer
     *   - order: \Magento\Sales\Model\Order
     *   - quote: \Magento\Quote\Model\Quote
     */
    public function execute(Observer $observer): void
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $observer->getData('order');
        /** @var \Magento\Quote\Model\Quote $quote */
        $quote = $observer->getData('quote');

        if (!$order || !$quote) {
            return;
        }

        // Si la orden ya tiene el monto guardado, no repetir el proceso
        if ((float) $order->getData('sedeco_round_amount') !== 0.0) {
            return;
        }

        $roundAmount = (float) $quote->getData('sedeco_round_amount');

        if ($roundAmount === 0.0) {
            return;
        }

        // Guardar el monto directamente en BD para que el Admin y las Invoices lo vean
        $order->setData('sedeco_round_amount', $roundAmount);
        $this->orderResource->saveAttribute($order, 'sedeco_round_amount');
    }
}
