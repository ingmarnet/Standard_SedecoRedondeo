# Standard_SedecoRedondeo

**Módulo Magento 2** | Redondeo obligatorio — Resolución SEDECO N° 1670/2022 (Paraguay)

Versión: `1.0.0` · Licencia: `OSL-3.0` · Namespace: `Standard\SedecoRedondeo` · Composer: `standard/module-sedeco-redondeo`

---

## Tabla de contenidos

1. [Contexto y propósito](#1-contexto-y-propósito)
2. [Reglas de negocio](#2-reglas-de-negocio)
3. [Requisitos](#3-requisitos)
4. [Instalación](#4-instalación)
5. [Estructura completa del módulo](#5-estructura-completa-del-módulo)
6. [Esquema de base de datos](#6-esquema-de-base-de-datos)
7. [Pipeline de cálculo (Total Collector)](#7-pipeline-de-cálculo-total-collector)
8. [Ciclo de vida del valor `sedeco_round_amount`](#8-ciclo-de-vida-del-valor-sedeco_round_amount)
9. [Visualización (Frontend, Admin, Email, PDF)](#9-visualización-frontend-admin-email-pdf)
10. [API REST y extension attributes](#10-api-rest-y-extension-attributes)
11. [Integración con ERP](#11-integración-con-erp)
12. [Verificación manual / QA](#12-verificación-manual--qa)
13. [Decisiones de diseño y *gotchas*](#13-decisiones-de-diseño-y-gotchas)
14. [Troubleshooting](#14-troubleshooting)
15. [Extender el módulo](#15-extender-el-módulo)
16. [Historial de cambios relevantes](#16-historial-de-cambios-relevantes)

---

## 1. Contexto y propósito

La **Resolución SEDECO N° 1670/2022** de la Secretaría de Defensa del Consumidor y del Usuario de Paraguay establece que, ante la falta de monedas fraccionarias menores a Gs. 50, todo monto cobrado al consumidor debe **redondearse al múltiplo de 50 inferior**, es decir, **siempre a favor del consumidor**.

Este módulo aplica esa normativa de forma automática y transparente al **Grand Total** del pedido en una tienda Magento 2, propagando el valor del ajuste a todas las entidades downstream (Order, Invoice, Credit Memo) y mostrándolo en todos los puntos visibles para el cliente y para back-office.

---

## 2. Reglas de negocio

### Fórmula

```
remainder      = round(grandTotal) mod 50
roundingAmount = -1 × remainder        si remainder > 0
roundingAmount =  0                    si remainder = 0
finalGrandTotal = grandTotal + roundingAmount
```

El valor `sedeco_round_amount` es **siempre ≤ 0**. Nunca redondea hacia arriba.

### Ejemplos

| Grand Total original | `sedeco_round_amount` | Grand Total final |
|----------------------|-----------------------|-------------------|
| Gs. 9.990            | − Gs. 40              | **Gs. 9.950**     |
| Gs. 10.025           | − Gs. 25              | **Gs. 10.000**    |
| Gs. 10.050           |   0                   | **Gs. 10.050**    |
| Gs. 75.133           | − Gs. 33              | **Gs. 75.100**    |

### Constantes de negocio

- Múltiplo de redondeo: **50** (constante `ROUNDING_UNIT` en [Model/Quote/Address/Total/SedecoRounding.php:34](Model/Quote/Address/Total/SedecoRounding.php#L34)).
- Etiqueta visible: **"Redondeo Resolución SEDECO 1670/22"**.
- Código interno del total: **`sedeco_redondeo`** (este código aparece en `sales.xml`, en el segmento de totales del checkout, en los plugins y en los bloques de UI; **debe mantenerse idéntico en todas las capas**).

---

## 3. Requisitos

| Componente            | Versión mínima |
|-----------------------|----------------|
| Magento 2             | 2.4.x          |
| PHP                   | 8.1+           |
| `magento/framework`   | 103.0+         |
| `magento/module-quote`| 101.2+         |
| `magento/module-sales`| 103.0+         |
| `magento/module-checkout` | 100.4+     |

Sequence declarada en [etc/module.xml](etc/module.xml): el módulo se carga **después** de `Magento_Quote`, `Magento_Sales`, `Magento_Tax` y `Magento_Checkout`.

---

## 4. Instalación

### 4.1. Copiar el módulo

```bash
cp -r Standard_SedecoRedondeo /var/www/html/app/code/Standard/SedecoRedondeo
```

> El nombre del directorio destino **debe** ser `app/code/Standard/SedecoRedondeo` para que `registration.php` lo registre correctamente como `Standard_SedecoRedondeo`.

### 4.2. Activar y desplegar

```bash
cd /var/www/html

# 1. Habilitar el módulo
php bin/magento module:enable Standard_SedecoRedondeo

# 2. Aplicar el esquema declarativo (crea las columnas sedeco_round_amount)
php bin/magento setup:upgrade

# 3. (Producción) Compilar el container DI
php bin/magento setup:di:compile

# 4. Desplegar assets estáticos (incluye el JS Knockout del checkout)
php bin/magento setup:static-content:deploy es_PY en_US -f

# 5. Limpiar todas las cachés
php bin/magento cache:flush
```

### 4.3. Verificación de instalación

```bash
php bin/magento module:status Standard_SedecoRedondeo
# Debe mostrar: Module is enabled

php bin/magento setup:db-schema:upgrade --dry-run
# No debe haber cambios pendientes para sedeco_round_amount
```

---

## 5. Estructura completa del módulo

```
Standard_SedecoRedondeo/
├── registration.php                        Registro del componente Magento
├── composer.json                           Metadatos Composer + autoload PSR-4
├── README.md                               Este documento
│
├── etc/
│   ├── module.xml                          Declaración del módulo + sequence
│   ├── sales.xml                           Registra el Total Collector (sort_order=990)
│   ├── db_schema.xml                       Esquema declarativo de columnas
│   ├── di.xml                              Plugin afterCollectTotals de Invoice
│   ├── events.xml                          Observer checkout_submit_all_after
│   └── extension_attributes.xml            sedeco_round_amount expuesto en API REST
│
├── Model/
│   └── Quote/Address/Total/
│       └── SedecoRounding.php              ★ Núcleo: cálculo + persistencia en Quote
│
├── Observer/
│   └── SaveSedecoAmountToOrder.php         ★ Persistencia Quote → Order al confirmar
│
├── Plugin/
│   ├── Quote/Model/Quote/Address/
│   │   ├── ToOrder.php                     Quote Address → Order (fallback)
│   │   └── ToOrderAddress.php              Quote Address → Order Address (fallback)
│   └── Sales/Model/
│       ├── Service/InvoiceService.php      Order → Invoice (afterPrepareInvoice)
│       └── Order/Invoice.php               Ajusta Grand Total de la Invoice
│
├── Block/
│   ├── Sales/                              Bloques para storefront / email
│   │   ├── Order/Totals/SedecoRounding.php
│   │   └── Invoice/Totals/SedecoRounding.php
│   └── Adminhtml/Sales/                    Bloques para el backend
│       ├── Order/Totals/SedecoRounding.php
│       └── Invoice/Totals/SedecoRounding.php
│
├── view/
│   ├── frontend/
│   │   ├── layout/
│   │   │   ├── checkout_index_index.xml        Inyecta componente KO en checkout
│   │   │   ├── checkout_cart_index.xml         Bloque PHTML del carrito
│   │   │   ├── sales_order_view.xml            Vista de orden (mi cuenta)
│   │   │   ├── sales_order_invoice.xml         Vista de invoice (mi cuenta)
│   │   │   ├── sales_email_order_items.xml     Email de confirmación
│   │   │   └── sales_email_order_invoice_items.xml Email de invoice
│   │   ├── requirejs-config.js
│   │   ├── templates/cart/sedeco-rounding.phtml
│   │   └── web/
│   │       ├── js/view/checkout/summary/sedeco-rounding.js
│   │       └── template/checkout/summary/sedeco-rounding.html
│   └── adminhtml/
│       └── layout/
│           ├── sales_order_view.xml            Admin > Sales > Orders > View
│           ├── sales_invoice_view.xml          Admin > Sales > Invoices > View
│           └── sales_order_invoice_new.xml     Admin > New Invoice
│
└── i18n/
    └── es_PY.csv                                Traducciones (es_PY)
```

---

## 6. Esquema de base de datos

Definido en [etc/db_schema.xml](etc/db_schema.xml). El módulo agrega la misma columna a las cinco tablas del flujo de ventas:

| Tabla              | Columna                | Tipo            | Default | Nullable | Propósito                                      |
|--------------------|------------------------|-----------------|---------|----------|------------------------------------------------|
| `quote`            | `sedeco_round_amount`  | decimal(20,4)   | 0       | sí       | Monto vivo durante el checkout                 |
| `quote_address`    | `sedeco_round_amount`  | decimal(20,4)   | 0       | sí       | Monto por dirección de envío                   |
| `sales_order`      | `sedeco_round_amount`  | decimal(20,4)   | 0       | sí       | Monto inmutable del pedido confirmado          |
| `sales_invoice`    | `sedeco_round_amount`  | decimal(20,4)   | 0       | sí       | Monto reflejado en factura                     |
| `sales_creditmemo` | `sedeco_round_amount`  | decimal(20,4)   | 0       | sí       | Monto en notas de crédito                      |

**El valor almacenado es siempre negativo o cero.** Aunque la moneda PYG no maneja decimales, la precisión `(20,4)` se mantiene por consistencia con los otros campos monetarios de Magento (`grand_total`, `subtotal`, etc.).

---

## 7. Pipeline de cálculo (Total Collector)

### 7.1. Registro

El Total Collector se registra en [etc/sales.xml](etc/sales.xml):

```xml
<section name="quote">
    <group name="totals">
        <item name="sedeco_redondeo"
              instance="Standard\SedecoRedondeo\Model\Quote\Address\Total\SedecoRounding"
              sort_order="990"/>
    </group>
</section>
```

### 7.2. Por qué `sort_order=990`

Magento ejecuta los Total Collectors en orden ascendente de `sort_order`. Para que el redondeo se calcule sobre el **Grand Total final**, debe ejecutarse **después** de:

| Collector nativo  | sort_order |
|-------------------|------------|
| subtotal          |  10        |
| shipping          | 350        |
| tax               | 450        |
| grand_total       | 550        |
| **sedeco_redondeo** | **990**  |

> ⚠️ Una versión inicial usaba `sort_order=150`, lo que provocaba que iframes de pago externos (PagoPar, Bancard) recalcularan totales después del redondeo y "deshicieran" el ajuste. Ver commit `7a0f6a2`. **No bajar este valor sin entender la implicancia.**

### 7.3. Implementación

[Model/Quote/Address/Total/SedecoRounding.php](Model/Quote/Address/Total/SedecoRounding.php) extiende `Magento\Quote\Model\Quote\Address\Total\AbstractTotal` e implementa dos métodos:

- **`collect(Quote, ShippingAssignment, Total)`** — calcula el ajuste y lo aplica:
  - Toma `$total->getGrandTotal()` como base (con cast a `float` por seguridad — puede venir `null` en carrito recién creado).
  - Si el carrito está vacío o `grandTotal <= 0`, resetea `sedeco_round_amount` a 0 y retorna.
  - Calcula `roundingAmount` con `calculateRoundingAmount()` (módulo 50 trabajando en enteros para evitar errores de punto flotante).
  - Persiste `sedeco_round_amount` en el Quote (`$quote->setData(...)`) — esto es crítico para `fetch()` y para el Observer.
  - Llama `$total->addTotalAmount()` y `addBaseTotalAmount()` para que el Grand Total native lo sume al recalcular.
  - Adicionalmente sobrescribe `$total->setGrandTotal($grandTotal + $roundingAmount)` y la versión Base, para que cualquier collector intermedio (sort_order 551–989) vea ya el valor redondeado.

- **`fetch(Quote, Total)`** — expone el valor al frontend:
  - Devuelve `['code', 'title', 'value']` para que el segmento aparezca en la API `/rest/V1/.../totals` y lo consuma el componente Knockout.
  - Si el monto es 0, retorna array vacío (no se muestra la línea).

---

## 8. Ciclo de vida del valor `sedeco_round_amount`

Diagrama de flujo del dato a través de las entidades de Magento:

```
┌──────────────────────────────────────────────────────────────────────────┐
│  CHECKOUT (proceso de compra)                                            │
│                                                                          │
│  Quote.collectTotals()                                                   │
│    └─► SedecoRounding::collect()                                         │
│         · Calcula  remainder = grandTotal % 50                           │
│         · quote.sedeco_round_amount = -remainder                         │
│         · quote_address.sedeco_round_amount = -remainder (vía Total)     │
│         · Ajusta grand_total y base_grand_total                          │
│                                                                          │
│  Frontend lee vía API /rest/V1/.../totals                                │
│    └─► SedecoRounding::fetch() devuelve segment 'sedeco_redondeo'        │
│         · Componente Knockout sedeco-rounding.js renderiza la línea      │
│                                                                          │
└──────────────────────────────────────────────────────────────────────────┘
                                   │
                                   │  Submit del pedido
                                   ▼
┌──────────────────────────────────────────────────────────────────────────┐
│  CONVERSIÓN QUOTE → ORDER                                                │
│                                                                          │
│  QuoteManagement::submit()                                               │
│    ├─► Plugin\Quote\Model\Quote\Address\ToOrder::afterConvert            │
│    │     · Lee quote_address.sedeco_round_amount (con fallback a quote)  │
│    │     · order.sedeco_round_amount = roundAmount                       │
│    │                                                                     │
│    └─► Plugin\Quote\Model\Quote\Address\ToOrderAddress::afterConvert     │
│          · Propaga al order_address (back-up redundante)                 │
│                                                                          │
│  Evento: checkout_submit_all_after                                       │
│    └─► Observer\SaveSedecoAmountToOrder::execute                         │
│         · ★ Punto canónico de persistencia                               │
│         · Si order.sedeco_round_amount aún es 0 y quote tiene valor:     │
│           → orderResource.saveAttribute(order, 'sedeco_round_amount')    │
│                                                                          │
│  Evento fallback: sales_model_service_quote_submit_success               │
│    └─► (mismo Observer, dispara para gateways que se saltan el evento    │
│         estándar — PayPal, iframes externos, etc.)                       │
│                                                                          │
└──────────────────────────────────────────────────────────────────────────┘
                                   │
                                   │  Crear Invoice (Admin o automática)
                                   ▼
┌──────────────────────────────────────────────────────────────────────────┐
│  GENERACIÓN DE INVOICE                                                   │
│                                                                          │
│  InvoiceService::prepareInvoice()                                        │
│    └─► Plugin\Sales\Model\Service\InvoiceService::afterPrepareInvoice    │
│         · invoice.sedeco_round_amount = order.sedeco_round_amount        │
│                                                                          │
│  Invoice::collectTotals()                                                │
│    └─► Plugin\Sales\Model\Order\Invoice::afterCollectTotals              │
│         · invoice.grand_total += sedeco_round_amount                     │
│         · invoice.base_grand_total += sedeco_round_amount                │
│         · (se aplica para PDF y vista admin)                             │
│                                                                          │
└──────────────────────────────────────────────────────────────────────────┘
```

### Importante: por qué hay **plugins + observer**

El observer `checkout_submit_all_after` (commit `4fafc5f`) es el **mecanismo canónico** porque:
- Se dispara después de que la Order **ya tiene `entity_id`** y está persistida.
- Tiene acceso simultáneo al `quote` y a la `order` por el `Observer` event.
- Permite forzar `saveAttribute()` directamente, sin depender de que un siguiente `save()` se ejecute.

Los plugins `ToOrder` y `ToOrderAddress` actúan como **defensa en profundidad** (commit `84259f2`): si un módulo de terceros llama `convert()` directamente o si el evento del checkout no se dispara (caso pasarelas externas), el valor sigue propagándose.

---

## 9. Visualización (Frontend, Admin, Email, PDF)

| Contexto                            | Implementación                                                                 | Archivos clave                                                                                                                                                                  |
|-------------------------------------|--------------------------------------------------------------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| **Carrito** (Cart Summary)          | Bloque PHTML inyectado vía layout, lee `$totals['sedeco_redondeo']`            | [view/frontend/layout/checkout_cart_index.xml](view/frontend/layout/checkout_cart_index.xml), [view/frontend/templates/cart/sedeco-rounding.phtml](view/frontend/templates/cart/sedeco-rounding.phtml) |
| **Checkout** (Order Summary)        | Componente Knockout.js que extiende `abstract-total`, consume el segmento de totales | [view/frontend/web/js/view/checkout/summary/sedeco-rounding.js](view/frontend/web/js/view/checkout/summary/sedeco-rounding.js), [view/frontend/web/template/checkout/summary/sedeco-rounding.html](view/frontend/web/template/checkout/summary/sedeco-rounding.html) |
| **Mi cuenta** > Ver orden (storefront) | Block PHP `Block\Sales\Order\Totals\SedecoRounding` registrado en `order_totals` | [Block/Sales/Order/Totals/SedecoRounding.php](Block/Sales/Order/Totals/SedecoRounding.php), [view/frontend/layout/sales_order_view.xml](view/frontend/layout/sales_order_view.xml) |
| **Mi cuenta** > Ver invoice          | Block `Block\Sales\Invoice\Totals\SedecoRounding`                              | [Block/Sales/Invoice/Totals/SedecoRounding.php](Block/Sales/Invoice/Totals/SedecoRounding.php), [view/frontend/layout/sales_order_invoice.xml](view/frontend/layout/sales_order_invoice.xml) |
| **Email** > New Order                | Mismo bloque storefront, inyectado en layout de email                          | [view/frontend/layout/sales_email_order_items.xml](view/frontend/layout/sales_email_order_items.xml)                                                                            |
| **Email** > New Invoice              | Mismo bloque de invoice                                                        | [view/frontend/layout/sales_email_order_invoice_items.xml](view/frontend/layout/sales_email_order_invoice_items.xml)                                                            |
| **Admin** > Orders > View            | Block `Block\Adminhtml\Sales\Order\Totals\SedecoRounding`                      | [Block/Adminhtml/Sales/Order/Totals/SedecoRounding.php](Block/Adminhtml/Sales/Order/Totals/SedecoRounding.php), [view/adminhtml/layout/sales_order_view.xml](view/adminhtml/layout/sales_order_view.xml) |
| **Admin** > Invoices > View          | Block `Block\Adminhtml\Sales\Invoice\Totals\SedecoRounding`                    | [Block/Adminhtml/Sales/Invoice/Totals/SedecoRounding.php](Block/Adminhtml/Sales/Invoice/Totals/SedecoRounding.php), [view/adminhtml/layout/sales_invoice_view.xml](view/adminhtml/layout/sales_invoice_view.xml) |
| **Admin** > Orders > New Invoice     | Mismo bloque admin de invoice                                                  | [view/adminhtml/layout/sales_order_invoice_new.xml](view/adminhtml/layout/sales_order_invoice_new.xml)                                                                          |
| **PDF** de Invoice                   | Ajustado vía plugin `afterCollectTotals` — afecta `grand_total` mostrado en PDF | [Plugin/Sales/Model/Order/Invoice.php](Plugin/Sales/Model/Order/Invoice.php), [etc/di.xml](etc/di.xml)                                                                          |

### Patrón común de los Block PHP

Todos los bloques (frontend y admin) heredan de `\Magento\Framework\View\Element\Template` e implementan `initTotals(): static`:

```php
public function initTotals(): static
{
    $parent      = $this->getParentBlock();              // bloque padre 'order_totals' o 'invoice_totals'
    $entity      = $parent->getOrder() /* o getInvoice() */;
    $roundAmount = (float) $entity->getData('sedeco_round_amount');

    if ($roundAmount === 0.0) {
        return $this;                                    // no agregar línea si es 0
    }

    $parent->addTotal(
        new DataObject([
            'code'       => 'sedeco_redondeo',
            'strong'     => false,
            'value'      => $roundAmount,
            'base_value' => $roundAmount,
            'label'      => __('Redondeo Resolución SEDECO 1670/22'),
        ]),
        'grand_total'                                    // posicionar antes del grand_total
    );

    return $this;
}
```

### Formato de moneda (PYG)

PYG no usa decimales. El módulo elimina explícitamente los `,00` o `.00` colados por el formato locale:

- **Componente KO** ([sedeco-rounding.js:53-66](view/frontend/web/js/view/checkout/summary/sedeco-rounding.js#L53-L66)): clona el `priceFormat` del Quote, fuerza `precision=0` y `requiredPrecision=0`, luego `replace(/\.00|,00/g, '')` por si el locale fuerza decimales.
- **PHTML del carrito** ([sedeco-rounding.phtml:31-32](view/frontend/templates/cart/sedeco-rounding.phtml#L31-L32)): `PriceCurrencyInterface->format($amount, false, 0)` y luego `str_replace([',00', '.00'], '', ...)`.

---

## 10. API REST y extension attributes

[etc/extension_attributes.xml](etc/extension_attributes.xml) expone `sedeco_round_amount` como atributo de:

- `Magento\Sales\Api\Data\OrderInterface`
- `Magento\Sales\Api\Data\InvoiceInterface`

Esto significa que en respuestas REST el campo aparece dentro de `extension_attributes`:

```bash
curl -H "Authorization: Bearer ${TOKEN}" \
     https://store.example/rest/V1/orders/12345
```

```jsonc
{
  "entity_id": 12345,
  "grand_total": 9950,
  "extension_attributes": {
    "sedeco_round_amount": -40
  }
}
```

Para el carrito durante checkout, el segmento aparece en:

```
GET /rest/V1/carts/mine/totals
{
  "grand_total": 9950,
  "total_segments": [
    { "code": "sedeco_redondeo", "title": "Redondeo Resolución SEDECO 1670/22", "value": -40 },
    ...
  ]
}
```

---

## 11. Integración con ERP

El redondeo se almacena en `sales_order.sedeco_round_amount` (siempre **≤ 0**). Para sincronizar con un ERP externo:

### Opción A — Línea de ajuste explícita (recomendada)

Leer `sedeco_round_amount` del pedido (vía SQL directo o API REST) y emitirlo en el ERP como un **ítem de descuento** o **línea de ajuste negativa** con descripción `"Redondeo Resolución SEDECO 1670/22"`. Esto preserva la trazabilidad legal y contable del ajuste.

### Opción B — Confiar en el `grand_total`

`grand_total` del pedido **ya incluye** el redondeo. Si el ERP solo necesita el monto final cobrado, basta con sincronizar el `grand_total` ya redondeado.

> ⚠️ **El campo `discount_amount` nativo de Magento NO se modifica** para evitar conflictos con reglas de precio (Cart Rules), reportes y módulos de cupones. El redondeo es un campo independiente. **No mezclar** estos dos valores en la integración con el ERP.

---

## 12. Verificación manual / QA

### Smoke test mínimo

1. Configurar la moneda de la tienda en **PYG** (Stores > Configuration > General > Currency Setup).
2. Asegurarse de que existan productos con precios que **no** sean múltiplos de 50 (ej. Gs. 9.990).
3. Agregar al carrito → ir al **Checkout**.
4. Verificar en el Order Summary que aparezca:
   > *Redondeo Resolución SEDECO 1670/22 ……… − Gs. 40*
5. Confirmar el pedido (con cualquier método de pago activo).
6. **Admin > Sales > Orders > View** → confirmar:
   - La línea de redondeo aparece antes de `Grand Total`.
   - `Grand Total` está redondeado a múltiplo de 50.
   - En BD: `SELECT entity_id, grand_total, sedeco_round_amount FROM sales_order ORDER BY entity_id DESC LIMIT 1;` debe mostrar el valor.
7. **Admin > Sales > Orders > Crear Invoice** → confirmar la misma línea.
8. Generar PDF de la Invoice → la línea aparece en el PDF.
9. Confirmar el email de "New Order" y "New Invoice" → ambos muestran la línea.

### Casos a validar

- Carrito vacío → no debe aparecer la línea (filtra por `grandTotal <= 0`).
- Total múltiplo exacto de 50 → no aparece la línea (filtra por `roundAmount === 0`).
- Pasarela de pago externa con iframe (PagoPar, Bancard, etc.) → ver commit `07db1a5`. El `sort_order=990` evita el bug donde se "deshacía" el redondeo.
- Pedido invitado (guest) y pedido logueado → ambos deben funcionar.
- Multi-shipping → cada `quote_address` recibe su `sedeco_round_amount`.

---

## 13. Decisiones de diseño y *gotchas*

### 13.1. Por qué un Total Collector y no solo un plugin sobre `grand_total`

Un Total Collector se integra naturalmente con:
- El recálculo de totales del Quote (cada cambio en cantidad/cupón/envío).
- La API REST `/rest/V1/.../totals` (vía `fetch()`).
- El sistema de "segmentos" del checkout Knockout.

Un plugin puro sobre `Order::getGrandTotal()` no participaría en estos flujos.

### 13.2. Por qué `setGrandTotal()` además de `addTotalAmount()`

`addTotalAmount()` solo registra el monto en el array interno del `Total` object; el Grand Total se recalcula sumando estos valores. Pero ciertos collectors intermedios y módulos de terceros leen `$total->getGrandTotal()` directamente — por eso también lo sobrescribimos explícitamente. Ver [Model/Quote/Address/Total/SedecoRounding.php:96-97](Model/Quote/Address/Total/SedecoRounding.php#L96-L97).

### 13.3. Por qué Observer **+** Plugins (no solo plugins)

Histórico:
- v1: solo plugins `ToOrder` / `ToOrderAddress` (commit `4d2c509`).
- v2: se descubrió que en algunos flujos (pagos externos, módulos custom de checkout) los plugins se ejecutaban en momentos donde la Order aún no tenía `entity_id`, y el `save()` posterior los sobrescribía.
- v3: se introdujo el Observer `checkout_submit_all_after` con `saveAttribute()` directo (commit `4fafc5f`). Los plugins se mantienen como red de seguridad.

### 13.4. Tipos float y null safety

Magento puede devolver `null` desde `getGrandTotal()` en carritos recién creados. Todas las lecturas hacen cast explícito con `?? 0` antes del cast a `float`. Ver commit `91639be`.

### 13.5. Persistencia en `quote` (no solo `quote_address`)

El campo se duplica en `quote.sedeco_round_amount` además de `quote_address.sedeco_round_amount` por dos razones:
- `fetch()` necesita acceso al valor sin tener que cargar el address.
- El Observer lee directamente del Quote, evitando depender del orden de carga del address.

### 13.6. No tocar `discount_amount`

Tentador pero peligroso: Magento usa `discount_amount` para reglas de precio, reportes y descuentos de cupones. Mezclar el redondeo legal con descuentos comerciales causa:
- Reportes incorrectos de "total descontado".
- Conflictos con extensiones de cupones que validan `discount_amount`.
- Confusión del ERP al interpretar la naturaleza del descuento.

---

## 14. Troubleshooting

| Síntoma                                                        | Causa probable / Solución                                                                                                                                                                                                |
|----------------------------------------------------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| La línea no aparece en el checkout                             | (1) Cache de full_page; ejecutar `cache:flush`. (2) Static content sin desplegar en modo producción; ejecutar `setup:static-content:deploy`. (3) Verificar consola del navegador por errores de RequireJS.               |
| La línea aparece en checkout pero no en la Order               | El Observer no se ejecutó. Habilitar log en `Observer/SaveSedecoAmountToOrder.php`. Verificar que el módulo esté `enabled` con `module:status`.                                                                          |
| El redondeo se "deshace" después del pago                      | La pasarela está recalculando totales después del Total Collector. **No bajar `sort_order` por debajo de 990.** Si es necesario, mover incluso más alto (ej. 9999).                                                       |
| Aparece `,00` o `.00` al final del monto                        | El locale fuerza decimales. Confirmar el `replace` regex de `sedeco-rounding.js` y de `sedeco-rounding.phtml`. Verificar que `setup:static-content:deploy` se ejecutó después del cambio.                                  |
| `TypeError: must be of type float, null given`                  | Versión vieja sin null-safety. Confirmar `?? 0` en `(float) ($total->getGrandTotal() ?? 0)`. Ver commit `91639be`.                                                                                                       |
| El PDF de la invoice no muestra el monto redondeado             | El plugin `afterCollectTotals` no está registrado. Verificar [etc/di.xml](etc/di.xml) y limpiar caché DI: `cache:clean config`.                                                                                          |
| `setup:upgrade` falla con error de columna duplicada            | Versión previa del módulo creó la columna manualmente. Verificar el estado real con `DESCRIBE sales_order;` y editar `db_schema_whitelist.json` si fuera necesario.                                                       |

### Logs útiles

```bash
tail -f var/log/system.log var/log/exception.log
```

Para depurar el cálculo, agregar temporalmente en `SedecoRounding::collect()`:

```php
$this->_logger->info('SEDECO collect', [
    'grandTotal' => $grandTotal,
    'roundingAmount' => $roundingAmount,
]);
```

(Inyectar `\Psr\Log\LoggerInterface` en el constructor para usarlo).

---

## 15. Extender el módulo

### 15.1. Cambiar el múltiplo de redondeo

Editar la constante en [Model/Quote/Address/Total/SedecoRounding.php:34](Model/Quote/Address/Total/SedecoRounding.php#L34):

```php
private const ROUNDING_UNIT = 50;
```

> Para hacerlo configurable vía Stores > Configuration, convertir la constante en una lectura de `ScopeConfigInterface` con un nodo en `etc/config.xml` y `etc/adminhtml/system.xml`.

### 15.2. Cambiar la dirección del redondeo

Por norma legal **siempre debe ser hacia abajo**. Si la regla cambiara (ej. redondeo al múltiplo más cercano), modificar `calculateRoundingAmount()` en [Model/Quote/Address/Total/SedecoRounding.php:145](Model/Quote/Address/Total/SedecoRounding.php#L145).

### 15.3. Agregar el campo a un Credit Memo (UI)

El esquema ya incluye `sales_creditmemo.sedeco_round_amount`. Para mostrarlo:
1. Crear `Block/Sales/Creditmemo/Totals/SedecoRounding.php` siguiendo el patrón de los otros bloques.
2. Crear `Block/Adminhtml/Sales/Creditmemo/Totals/SedecoRounding.php` análogo.
3. Crear los layouts `view/frontend/layout/sales_order_creditmemo.xml` y `view/adminhtml/layout/sales_creditmemo_view.xml`.
4. Agregar plugin `afterPrepareCreditmemo` en `Magento\Sales\Model\Service\CreditmemoService` para propagar el valor desde la Invoice/Order.

### 15.4. Exponer el segmento en GraphQL

Agregar un `Plugin` sobre `Magento\QuoteGraphQl\Model\CartTotalsResolver` que añada el segmento al output, o un campo nuevo en el schema GraphQL siguiendo el patrón de `extension_attributes`.

---

## 16. Historial de cambios relevantes

Resumen del log Git (más reciente arriba):

| Commit  | Resumen                                                                                              |
|---------|------------------------------------------------------------------------------------------------------|
| `888956b` | feat: extension attributes para `sedeco_round_amount` en Order e Invoice (API REST)                  |
| `4fafc5f` | refactor: reemplaza la propagación basada en plugins por un Observer en `checkout_submit_all_after`  |
| `3096c2e` | refactor: formato de monto sin decimales en todos los puntos visibles                                |
| `84259f2` | fix: fallback Quote → QuoteAddress en plugins de conversión, para no perder el monto                 |
| `70774a8` | feat: bloques storefront/email + remoción de templates redundantes en admin layouts                  |
| `13a410a` | style: forzar 0 decimales en frontend (Checkout y Cart)                                              |
| `7a0f6a2` | fix: cambia `sort_order` del Total Collector de 150 a 990                                            |
| `07db1a5` | fix: 3 bugs en `collect()` que causaban monto sin redondear con iframe de pago                       |
| `91639be` | fix: cast de `getGrandTotal`/`getBaseGrandTotal` a `float` para evitar `TypeError` con `null`        |
| `4d2c509` | feat: módulo `Standard_SedecoRedondeo` v1.0.0 — versión inicial                                      |

---

## Licencia

OSL-3.0 — Open Software License 3.0

Copyright © 2024 Standard SA · https://standard.com.py · dev@standard.com.py
