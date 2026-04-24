<?php
/**
 * Standard_SedecoRedondeo
 *
 * Implementa el redondeo obligatorio según Resolución SEDECO N° 1670/2022
 * de Paraguay. El ajuste es siempre hacia abajo (a favor del consumidor),
 * llevando el Grand Total al múltiplo de 50 inferior más cercano.
 *
 * @author    Standard SA
 * @copyright Copyright (c) 2024 Standard SA (https://standard.com.py)
 * @license   https://opensource.org/licenses/OSL-3.0 OSL-3.0
 */
declare(strict_types=1);

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'Standard_SedecoRedondeo',
    __DIR__
);
