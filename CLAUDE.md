# Gestión — sistema administrativo (clientes, servicios, cobros, pagos, cotizaciones)

Laravel 13 + Inertia v3 + React 19 + TypeScript + Tailwind v4. Despliegue en cPanel: sin Node en el servidor, sin SSR, sin WebSockets.
Se avanza por fases; no iniciar la siguiente sin aprobación del usuario.

## Comandos
- Tests: `php artisan test` · Formato PHP: `php vendor/bin/pint`
- Tipos: `npm run typecheck` · Build: `npm run build` · Pruebas TS: `npm run test:js` (node --test; en PowerShell usar `npm.cmd`)
- Admin inicial: `php artisan db:seed` (lee ADMIN_* de .env)
- Formato TS: `npx prettier --write <archivos>` (usa `.prettierrc.json`: comillas simples, 4 espacios, 160 col). No formatear sin esa config.
- Paquete para cPanel: `scripts\empaquetar.ps1` → `dist\*.zip`. Guía completa: `docs/DESPLIEGUE.md`.

## Producción
- Sin `env()` fuera de `config/` (se rompe con `config:cache`). Proxies vía `config('app.trusted_proxies')`.
- Ziggy: sin sesión solo se publica el grupo `guest` (`config/ziggy.php`); login/logout fuerzan recarga completa (`Inertia::location`).
- Errores en navegación Inertia (producción) → `back()` con flash `error`; páginas de error propias en `resources/views/errors`.

## Arquitectura
- Controladores delgados → FormRequest → Action (operación con efectos, transacción) / Service (cálculo puro) → Model.
- Lógica por dominio en `app/Domain/<Dominio>/{Actions,Services,Enums}`; modelos en `app/Models`; lecturas complejas en `app/Queries`.
- Páginas Inertia en `resources/js/pages/<modulo>/` (delgadas); componentes del módulo en `resources/js/features/<modulo>/`; UI compartida en `resources/js/components/`.
- Auditoría: `ActivityLogger::log()` para acciones relevantes.

## Reglas que no se rompen
- Dinero: DECIMAL(12,2) + `MoneyCast` (Brick\Money). Nunca float. Al frontend viaja como `{ amount: "350.00", currency: "PEN" }` (`MoneyPresenter`). En TS, `lib/money.ts` (céntimos bigint).
- Nunca sumar PEN con USD: agregar siempre por moneda.
- Precios con IGV incluido; desglose a nivel de documento: base = round(total / (1 + tasa), 2), IGV = total − base.
- Fechas de negocio son DATE ("YYYY-MM-DD") y "hoy" sale de `BusinessClock` (America/Lima). Timestamps en UTC. En TS jamás `new Date('YYYY-MM-DD')`: usar `lib/dates.ts`.
- Columnas DATE de negocio usan el cast `DateOnly` (guarda "Y-m-d" sin hora; nunca `immutable_date`, que en SQLite guarda hora y rompe comparaciones de límite).
- `BusinessClock::today()` devuelve la fecha de Lima representada como las columnas DATE (medianoche en la zona de la app): compararla directo con `due_date`, `start_date`… Nunca comparar contra `now()` de Lima. Para timestamps (`cancelled_at`…) usar `now()` (UTC).
- Vencimientos de ciclos solo vía `RecurrenceCalculator` (anclados al inicio, nunca encadenados). El frontend no calcula fechas: pide `/contratos/calendario`.
- "Vencido" (cobro) y "Vista"/"Vencida" (cotización) se calculan, no se guardan.
- Cobros se cancelan y pagos se anulan; no se borran.
- `amount_paid`/`status`/`paid_on` del cobro solo los escribe `ChargeSettlement::recalculate()` (fuente de verdad: pagos no anulados), dentro de la transacción y con el cobro bloqueado.
- Pagos: 1 pago = 1 cobro, misma moneda, nunca más que el saldo, sin fecha futura. Importe de un cobro editable solo sin pagos (con motivo).
- Cobros de contratos solo vía `GenerateContractCharges` (idempotente: lock + UNIQUE contrato+ciclo). Con cobros generados, el calendario del contrato queda fijo.
- Cotizaciones: `QuoteCalculator` (PHP) y `lib/quote-math.ts` son espejo exacto; ambos se prueban contra `tests/fixtures/quote-calculations.json`. Cambiar uno exige cambiar el otro y el fixture. El servidor siempre recalcula al guardar.
- Totales de dinero: agregación SQL `GROUP BY currency`; normalizar con `ChargeIndexQuery::decimal()`.
- Recurrencia normalizada: múltiplos de 12 meses se guardan como `year`; `month` nunca con count múltiplo de 12.
- Parámetros editables desde Configuración viven en `app_settings` vía `AppSettings` (p. ej. `chargeLeadDays()`, con respaldo en `config/billing.php`). No leer `config('billing.charge_lead_days')` directo.
- Usuarios y métodos de pago no se borran: se desactivan. Siempre queda ≥1 administrador activo y ≥1 método activo; nadie se desactiva a sí mismo. Cambiar/restablecer contraseña cierra las demás sesiones (`ManageUsers`). Contraseñas: `Password::defaults()` (10+, letras y números), nunca en logs.

## Design System
Tokens en `resources/css/app.css` (paletas de Tailwind deshabilitadas a propósito). Navy #101727 institucional, brand #2664EB acción, cream #F8F1E7 acento.
Inter con cifras tabulares (`numeric`). Radios 4–8 px. Sombra solo en overlays. Referencia viva en `/sistema/ui` (solo local).
