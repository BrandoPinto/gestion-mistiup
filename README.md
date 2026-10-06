# Gestión Mistiup

Sistema interno para llevar los clientes, los servicios contratados, los cobros, los pagos y las cotizaciones de Mistiup.

## Qué hace

- Clientes con sus datos de contacto, servicios, cobros y cotizaciones en una sola ficha.
- Catálogo de servicios con precios sugeridos y modalidad (pago único o recurrente).
- Servicios contratados con renovación mensual, anual o cada cierto número de meses. Los cobros de cada periodo se generan solos unos días antes del vencimiento.
- Cobros y pagos: pagos parciales, comprobante adjunto, anulación de pagos y cancelación de cobros sin borrar el historial.
- Vencimientos y recordatorios internos (antes y después de la fecha de pago, y fin de contrato).
- Cotizaciones con descuentos por línea y global, IGV incluido, PDF y enlace para enviar al cliente. Una cotización aprobada se convierte en servicios contratados.
- Resumen con lo cobrado, lo pendiente y los ingresos de los últimos 12 meses, separados por moneda (soles y dólares).
- Usuarios, métodos de pago, datos de la empresa y parámetros de cobro configurables.

## Tecnología

- Laravel 13 (PHP 8.3+), MySQL
- React 19 + TypeScript con Inertia, Tailwind CSS
- PDF con DomPDF

Pensado para un hosting con cPanel: el frontend se compila antes de subirlo y las tareas automáticas corren con el cron del servidor.

## Instalación local

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Configurar la base de datos y el usuario administrador (`ADMIN_EMAIL`, `ADMIN_PASSWORD`) en `.env`, y luego:

```bash
php artisan migrate
php artisan db:seed
npm run build
php artisan serve
```

Para ver las tareas programadas en local: `php artisan schedule:work`.

## Pruebas

```bash
php artisan test
npm run typecheck
npm run test:js
```

## Publicar en el servidor

Los pasos para cPanel (estructura de carpetas, `.env` de producción, cron, actualizaciones y respaldos) están en [docs/DESPLIEGUE.md](docs/DESPLIEGUE.md). El paquete se arma con `scripts/empaquetar.ps1`.
