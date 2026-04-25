<?php
/**
 * Standard_SedecoRedondeo
 * Plugin: Inyecta la línea SEDECO en los totales de la Invoice (Admin).
 * Usa afterGetTotals() para garantizar que $_totals ya fue inicializado.
 */
declare(strict_types=1);

namespace Standard\SedecoRedondeo\Plugin\Block\Adminhtml\Invoice;

use Magento\Framework\DataObject;
use Magento\Sales\Block\Adminhtml\Order\Invoice\Totals as InvoiceTotals;

class AddSedecoTotal
{
    public function afterGetTotals(InvoiceTotals $subject, $result): array
    {
        $result = (array) $result;

        // Evitar duplicados
        if (isset($result['sedeco_redondeo'])) {
            return $result;
        }

        $invoice = $subject->getInvoice() ?? $subject->getSource();
        if (!$invoice) {
            return $result;
        }

        $roundAmount = (float) $invoice->getData('sedeco_round_amount');
        if ($roundAmount === 0.0) {
            return $result;
        }

        $sedecoTotal = new DataObject([
            'code'       => 'sedeco_redondeo',
            'strong'     => false,
            'value'      => $roundAmount,
            'base_value' => $roundAmount,
            'label'      => __('Redondeo Resolución SEDECO 1670/22'),
        ]);

        // Insertar antes de grand_total
        $newResult = [];
        $inserted = false;
        foreach ($result as $code => $total) {
            if ($code === 'grand_total' && !$inserted) {
                $newResult['sedeco_redondeo'] = $sedecoTotal;
                $inserted = true;
            }
            $newResult[$code] = $total;
        }
        if (!$inserted) {
            $newResult['sedeco_redondeo'] = $sedecoTotal;
        }

        return $newResult;
    }
}
