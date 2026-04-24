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

        // Solo calcula en la dirección de envío (evita doble cómputo)
        $address = $shippingAssignment->getShipping()->getAddress();
        if ($address->getAddressType() !== Quote\Address::ADDRESS_TYPE_SHIPPING) {
            return $this;
        }

        // Grand Total actual (después de impuestos y descuentos)
        $grandTotal = $total->getGrandTotal();

        // Calcular el ajuste de redondeo
        $roundingAmount = $this->calculateRoundingAmount($grandTotal);

        if ($roundingAmount === 0.0) {
            // No es necesario ajustar
            $address->setSedecoRoundAmount(0);
            $quote->setSedecoRoundAmount(0);
            return $this;
        }

        // Aplicar el ajuste (valor negativo = descuento para el cliente)
        $total->addTotalAmount($this->getCode(), $roundingAmount);
        $total->addBaseTotalAmount($this->getCode(), $roundingAmount);
        $total->setGrandTotal($grandTotal + $roundingAmount);
        $total->setBaseGrandTotal($total->getBaseGrandTotal() + $roundingAmount);

        // Persistir en Quote Address y Quote
        $address->setSedecoRoundAmount($roundingAmount);
        $quote->setSedecoRoundAmount($roundingAmount);

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
        $roundingAmount = $quote->getSedecoRoundAmount() ?? 0;

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
