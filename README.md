# Standard_SedecoRedondeo

**Módulo Magento 2** | Redondeo obligatorio — Resolución SEDECO N° 1670/2022 (Paraguay)

---

## ¿Qué hace este módulo?

Implementa el redondeo automático de precios exigido por la **Resolución SEDECO N° 1670/2022** de Paraguay. El sistema aplica un ajuste **siempre a favor del consumidor** (hacia abajo), llevando el Grand Total del pedido al múltiplo de 50 guaraníes inferior más cercano.

| Grand Total original | Redondeo aplicado | Grand Total final |
|----------------------|-------------------|-------------------|
| Gs. 9.990            | − Gs. 40          | **Gs. 9.950**     |
| Gs. 10.025           | − Gs. 25          | **Gs. 10.000**    |
| Gs. 10.050           | 0                 | **Gs. 10.050**    |
| Gs. 75.133           | − Gs. 33          | **Gs. 75.100**    |

---

## Requisitos

- Magento 2.4.x
- PHP 8.1+

---

## Instalación

### 1. Copiar el módulo

```bash
cp -r Standard_SedecoRedondeo /var/www/html/app/code/Standard/SedecoRedondeo
```

### 2. Activar y actualizar

```bash
cd /var/www/html

# Habilitar el módulo
php bin/magento module:enable Standard_SedecoRedondeo

# Aplicar el esquema de base de datos (crea columnas sedeco_round_amount)
php bin/magento setup:upgrade

# Compilar DI (producción)
php bin/magento setup:di:compile

# Desplegar assets estáticos
php bin/magento setup:static-content:deploy es_PY en_US -f

# Limpiar caché
php bin/magento cache:flush
```

---

## Arquitectura del Módulo

```
Standard_SedecoRedondeo/
├── registration.php
├── composer.json
├── etc/
│   ├── module.xml              # Declaración del módulo + dependencias
│   ├── sales.xml               # Registra el Total Collector (sort_order=150)
│   ├── db_schema.xml           # Columnas sedeco_round_amount en BD
│   └── di.xml                  # Plugins de propagación y totales
│
├── Model/
│   └── Quote/Address/Total/
│       └── SedecoRounding.php  # ★ Lógica de cálculo del redondeo
│
├── Plugin/
│   ├── Quote/Model/Quote/Address/
│   │   ├── ToOrder.php         # Propaga Quote Address → Order
│   │   └── ToOrderAddress.php  # Propaga Quote Address → Order Address
│   └── Sales/Model/
│       ├── Service/
│       │   └── InvoiceService.php # Propaga Order → Invoice
│       └── Order/
│           └── Invoice.php     # Ajusta Grand Total de Invoice
│
├── Block/Adminhtml/Sales/
│   ├── Order/Totals/
│   │   └── SedecoRounding.php  # Bloque admin: totales de Orden
│   └── Invoice/Totals/
│       └── SedecoRounding.php  # Bloque admin: totales de Invoice
│
├── view/
│   ├── frontend/
│   │   ├── layout/
│   │   │   ├── checkout_index_index.xml  # Inyecta componente KO en checkout
│   │   │   └── checkout_cart_index.xml   # Muestra en carrito
│   │   ├── requirejs-config.js
│   │   ├── templates/cart/
│   │   │   └── sedeco-rounding.phtml     # Template carrito
│   │   └── web/
│   │       ├── js/view/checkout/summary/
│   │       │   └── sedeco-rounding.js    # Componente Knockout.js
│   │       └── template/checkout/summary/
│   │           └── sedeco-rounding.html  # Template KO HTML
│   └── adminhtml/
│       └── layout/
│           ├── sales_order_view.xml      # Admin: vista de Orden
│           ├── sales_invoice_view.xml    # Admin: vista de Invoice
│           └── sales_order_invoice_new.xml # Admin: nueva Invoice
│
└── i18n/
    └── es_PY.csv
```

---

## Base de Datos

El módulo agrega la columna `sedeco_round_amount` a las siguientes tablas:

| Tabla              | Propósito                                      |
|--------------------|------------------------------------------------|
| `quote`            | Monto de redondeo durante el proceso de compra |
| `quote_address`    | Monto por dirección de envío                   |
| `sales_order`      | Monto inmutable una vez generado el pedido     |
| `sales_invoice`    | Monto reflejado en la factura                  |
| `sales_creditmemo` | Monto en notas de crédito                      |

---

## Compatibilidad con ERP

El redondeo se almacena en `sedeco_round_amount` (valor **negativo**) en la `sales_order`. Para integrar con tu ERP:

- **Opción A (recomendada):** Leer `sedeco_round_amount` directamente del pedido y procesarlo como una **línea de ajuste negativa** en el ERP.
- **Opción B:** El `grand_total` de la orden ya incluye el redondeo, por lo que el monto enviado al ERP siempre será el monto exacto redondeado.

> ⚠️ El campo `discount_amount` nativo de Magento NO se modifica para evitar conflictos con reglas de precio y reportes nativos. Se recomienda usar `sedeco_round_amount` como campo independiente en la sincronización con el ERP.

---

## Funcionamiento de la Lógica de Redondeo

```php
// En SedecoRounding.php
$totalInt  = (int) round($grandTotal);
$remainder = $totalInt % 50;

if ($remainder > 0) {
    $roundingAmount = -1 * $remainder; // Siempre negativo (a favor del cliente)
}
```

El `sort_order=150` en `sales.xml` garantiza que el cálculo ocurre **después** de:
- Subtotal (sort_order=10)
- Descuentos (sort_order=20)
- Envío (sort_order=30)
- Impuestos/IVA (sort_order=100)

---

## Visualización

| Contexto                         | Implementación                              |
|----------------------------------|---------------------------------------------|
| Carrito (Cart Summary)           | `sedeco-rounding.phtml`                     |
| Checkout (Order Summary)         | Componente Knockout.js + template HTML      |
| Admin > Orders > View            | `Block/Adminhtml/Sales/Order/Totals/`       |
| Admin > Invoices > View          | `Block/Adminhtml/Sales/Invoice/Totals/`     |
| Admin > Orders > New Invoice     | Mismo bloque de Invoice                     |
| PDF de Invoice                   | Ajustado vía Plugin en `Invoice::collectTotals` |

---

## Verificación Manual

1. Agregar productos al carrito con un total que **no** sea múltiplo de 50 (ej. Gs. 9.990).
2. Ir al **Checkout** → Verificar en el Order Summary la línea:
   > *Redondeo Resolución SEDECO 1670/22 … − Gs. 40*
3. Completar el pedido y verificar en **Admin > Sales > Orders** que el total ajustado se guarda correctamente.
4. Crear una **Invoice** del pedido → Verificar que la línea de redondeo aparece y el total es el correcto.

---

## Licencia

OSL-3.0 — Open Software License 3.0
