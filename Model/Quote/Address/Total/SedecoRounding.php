<?php
/**
 * Standard_SedecoRedondeo
 *
 * Total Collector principal del módulo.
 *
 * Implementa la Resolución SEDECO N° 1670/2022 de Paraguay:
 * Si el Grand Total no es múltiplo de 50, se aplica un ajuste negativo
 * (descuento) SIEMPRE hacia abajo, favoreciendo al consumidor.
 *
 * Ejemplo:
 *   Grand Total = 9.990 Gs. → redondeo = -40  → Total final = 9.950 Gs.
 *   Grand Total = 10.025 Gs. → redondeo = -25  → Total final = 10.000 Gs.
 *   Grand Total = 10.000 Gs. → redondeo = 0    → Sin ajuste.
 *
 * @author    Standard SA
 * @copyright Copyright (c) 2024 Standard SA
 */
declare(strict_types=1);

namespace Standard\SedecoRedondeo\Model\Quote\Address\Total;

use Magento\Framework\Phrase;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Address\Total\AbstractTotal;

class SedecoRounding extends AbstractTotal
{
    /**
     * Múltiplo de redondeo exigido por la Resolución 1670/2022
     */
    private const ROUNDING_UNIT = 50;

    /**
     * Código interno del total (debe coincidir con el name en sales.xml)
     */
    public function __construct()
    {
        $this->setCode('sedeco_redondeo');
    }

    /**
     * Calcula el monto de redondeo y lo aplica como ajuste negativo.
     *
     * @param Quote                       $quote
     * @param ShippingAssignmentInterface $shippingAssignment
     * @param Total                       $total
     * @return $this
     */
    public function collect(
        Quote $quote,
        ShippingAssignmentInterface $shippingAssignment,
        Total $total
    ): self {
        parent::collect($quote, $shippingAssignment, $total);

        $items = $shippingAssignment->getItems();
        if (!count($items)) {
            return $this;
        }

        // Grand Total actual (después de impuestos y descuentos).
        // getGrandTotal() puede devolver null en un carrito recién creado.
        $grandTotal = (float) ($total->getGrandTotal() ?? 0);

        // Carrito vacío o sin procesar → resetear y salir
        if ($grandTotal <= 0) {
            $quote->setSedecoRoundAmount(0.0);
            return $this;
        }

        // Calcular el ajuste SEDECO
        $roundingAmount = $this->calculateRoundingAmount($grandTotal);

        // Persistir en el Quote para que:
        //  · fetch() pueda leerlo vía REST API (/rest/V1/.../totals)
        //  · Los Blocks de admin lo lean al renderizar la orden/invoice
        $quote->setSedecoRoundAmount($roundingAmount);
        
        // También lo guardamos en el QuoteAddress para que el plugin ToOrder lo transfiera nativamente
        $shippingAssignment->getShipping()->getAddress()->setData('sedeco_round_amount', $roundingAmount);

        if ($roundingAmount === 0.0) {
            $total->addTotalAmount($this->getCode(), 0.0);
            $total->addBaseTotalAmount($this->getCode(), 0.0);
            return $this;
        }

        // Aplicar el ajuste negativo al Total object.
        // addTotalAmount() registra el monto para que el Grand Total
        // collector nativo (sort_order=200) lo sume al recalcular.
        $total->addTotalAmount($this->getCode(), $roundingAmount);
        $total->addBaseTotalAmount($this->getCode(), $roundingAmount);

        // También actualizar el GrandTotal directamente para que colectores
        // intermedios (sort_order 151-199) vean el valor ya redondeado.
        $total->setGrandTotal($grandTotal + $roundingAmount);
        $total->setBaseGrandTotal((float) ($total->getBaseGrandTotal() ?? 0) + $roundingAmount);

        return $this;
    }

    /**
     * Expone los datos del total para el frontend (Knockout / checkout summary).
     *
     * @param Quote  $quote
     * @param Total  $total
     * @return array
     */
    public function fetch(Quote $quote, Total $total): array
    {
        $roundingAmount = $total->getTotalAmount($this->getCode()) ?: $quote->getSedecoRoundAmount();
        $roundingAmount = (float) ($roundingAmount ?? 0);

        if ($roundingAmount == 0) {
            return [];
        }

        return [
            'code'  => $this->getCode(),
            'title' => $this->getLabel(),
            'value' => $roundingAmount,
        ];
    }

    /**
     * Etiqueta que aparece en el resumen del checkout y en el admin.
     *
     * @return Phrase
     */
    public function getLabel(): Phrase
    {
        return __('Redondeo Resolución SEDECO 1670/22');
    }

    // -------------------------------------------------------------------------
    // Helpers privados
    // -------------------------------------------------------------------------

    /**
     * Calcula el monto de redondeo hacia abajo (favorable al consumidor).
     *
     * @param float $grandTotal
     * @return float  Valor negativo o 0.0
     */
    private function calculateRoundingAmount(float $grandTotal): float
    {
        // Trabajar en enteros para evitar problemas de punto flotante
        // En Paraguay el Guaraní no tiene decimales, pero Magento puede
        // manejar centavos con otras monedas. Redondeamos el grandTotal
        // a entero primero para la operación de módulo.
        $totalInt = (int) round($grandTotal);
        $remainder = $totalInt % self::ROUNDING_UNIT;

        if ($remainder === 0) {
            return 0.0;
        }

        // Redondeo SIEMPRE hacia abajo (a favor del cliente) → resta el resto
        return (float) (-1 * $remainder);
    }
}
