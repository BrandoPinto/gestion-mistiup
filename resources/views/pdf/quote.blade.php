@php
    use App\Domain\Quotes\Pdf\PdfFormat as F;

    $currency = $doc['currency'];
    $hasLineDiscounts = collect($doc['items'])->contains(fn ($item) => $item['line_discount'] !== '0.00');
    $accounts = array_values(array_filter($company['bank_accounts'], fn ($account) => empty($account['currency']) || $account['currency'] === $currency));
    $client = $doc['client'];
    $fontUrl = fn (string $path) => str_replace('\\', '/', $path);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Cotización {{ $doc['number'] }}</title>
    <style>
        /* Plantilla para dompdf: maquetación con tablas (no admite flex ni grid). Colores del Design System. */
        @font-face { font-family: 'Inter'; font-weight: 400; src: url('{{ $fontUrl($fonts['regular']) }}') format('truetype'); }
        @font-face { font-family: 'Inter'; font-weight: 500; src: url('{{ $fontUrl($fonts['medium']) }}') format('truetype'); }
        @font-face { font-family: 'Inter'; font-weight: 600; src: url('{{ $fontUrl($fonts['semibold']) }}') format('truetype'); }
        @font-face { font-family: 'Inter'; font-weight: 700; src: url('{{ $fontUrl($fonts['semibold']) }}') format('truetype'); }

        @page { margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 0; font-family: 'Inter', DejaVu Sans, sans-serif; font-size: 9.5pt; line-height: 1.45; color: #101727; }
        .page { padding: 34pt 40pt 90pt 40pt; }
        table { border-collapse: collapse; width: 100%; }
        td, th { vertical-align: top; padding: 0; }
        .muted { color: #5b6278; }
        .faint { color: #8a90a2; }
        .label { font-size: 6.8pt; font-weight: 600; letter-spacing: 1.4pt; text-transform: uppercase; color: #5b6278; }
        .right { text-align: right; }
        .num { white-space: nowrap; }

        .brand-name { font-size: 15pt; font-weight: 600; color: #101727; }
        .doc-kind { font-size: 7pt; font-weight: 600; letter-spacing: 2pt; text-transform: uppercase; color: #2664eb; }
        .doc-number { font-size: 17pt; font-weight: 600; color: #101727; white-space: nowrap; }
        .rule { height: 3pt; background: #101727; margin: 14pt 0 16pt; }

        .client-name { font-size: 11.5pt; font-weight: 600; margin-top: 3pt; }
        .meta td { padding: 1.5pt 0; font-size: 8.5pt; }
        .meta td.k { color: #5b6278; padding-right: 16pt; }
        .meta td.v { font-weight: 500; text-align: right; }

        .intro { margin: 14pt 0 0; white-space: pre-line; color: #2e3650; }

        .items { margin-top: 16pt; }
        .items th { font-size: 6.8pt; font-weight: 600; letter-spacing: 1pt; text-transform: uppercase; color: #5b6278; padding: 6pt 4pt; border-top: 1.2pt solid #101727; border-bottom: 1.2pt solid #101727; text-align: left; }
        .items th.right { text-align: right; }
        .items td { padding: 8pt 4pt; border-bottom: 0.6pt solid #e6e1d8; }
        .items .item-name { font-weight: 500; }
        .items .item-desc { font-size: 8pt; color: #5b6278; white-space: pre-line; }

        .totals { width: 210pt; margin-left: auto; margin-top: 10pt; }
        .totals td { padding: 2.5pt 0; font-size: 8.5pt; }
        .totals .grand td { background: #f8f1e7; padding: 7pt 8pt; vertical-align: middle; }
        .totals .grand .k { font-size: 7.5pt; font-weight: 600; letter-spacing: 1.2pt; text-transform: uppercase; }
        .totals .grand .v { font-size: 13pt; font-weight: 600; }
        .note { font-size: 7.5pt; color: #5b6278; text-align: right; margin-top: 3pt; }

        .section { margin-top: 18pt; padding-top: 12pt; border-top: 0.6pt solid #e6e1d8; }
        .section p { margin: 3pt 0 0; white-space: pre-line; font-size: 8.5pt; color: #2e3650; }
        .payments { margin-top: 18pt; background: #fcf9f4; padding: 12pt 14pt; border-top: 0.6pt solid #e6e1d8; }
        .payments td { padding: 6pt 14pt 0 0; font-size: 8.3pt; width: 50%; }

        .footer { position: fixed; left: 40pt; right: 40pt; bottom: 26pt; border-top: 3pt solid #101727; padding-top: 7pt; font-size: 7.5pt; color: #5b6278; }
        .footer strong { color: #101727; font-weight: 600; }
    </style>
</head>
<body>
<div class="footer">
    <table>
        <tr>
            <td><strong>{{ $company['name'] }}</strong></td>
            <td class="right">{{ collect([$company['website'], $company['email'], $company['phone']])->filter()->implode(' · ') }}</td>
        </tr>
    </table>
</div>

<div class="page">
    <table>
        <tr>
            <td style="width: 60%;">
                @if ($logo)
                    <img src="{{ $logo }}" alt="{{ $company['name'] }}" style="max-height: 46pt; max-width: 170pt; margin-bottom: 6pt;">
                @else
                    <div class="brand-name">{{ $company['name'] }}</div>
                @endif
                <div class="muted" style="font-size: 8pt;">
                    @if ($company['legal_name'])<div style="color: #2e3650; font-weight: 500;">{{ $company['legal_name'] }}</div>@endif
                    @if ($company['ruc'])<div>RUC {{ $company['ruc'] }}</div>@endif
                    @if ($company['address'])<div>{{ $company['address'] }}</div>@endif
                </div>
            </td>
            <td class="right">
                <div class="doc-kind">Cotización</div>
                <div class="doc-number">{{ $doc['number'] }}</div>
            </td>
        </tr>
    </table>

    <div class="rule"></div>

    <table>
        <tr>
            <td style="width: 58%; padding-right: 18pt;">
                <div class="label">Para</div>
                @if ($client)
                    <div class="client-name">{{ $client['name'] }}</div>
                    <div class="muted" style="font-size: 8.3pt;">
                        @if ($client['document'])<div>{{ $client['document'] }}</div>@endif
                        @if ($client['address'])<div>{{ $client['address'] }}</div>@endif
                        @if ($client['contact_name'])<div>Atención: {{ $client['contact_name'] }}</div>@endif
                        @if ($client['email'] || $client['phone'])<div>{{ collect([$client['email'], $client['phone']])->filter()->implode(' · ') }}</div>@endif
                    </div>
                @endif
            </td>
            <td>
                <table class="meta">
                    <tr><td class="k">Fecha</td><td class="v">{{ F::date($doc['issue_date']) }}</td></tr>
                    <tr><td class="k">Válida hasta</td><td class="v">{{ F::date($doc['valid_until']) }}</td></tr>
                    @if ($doc['delivery_date'])
                        <tr><td class="k">Entrega estimada</td><td class="v">{{ F::date($doc['delivery_date']) }}</td></tr>
                    @endif
                    <tr><td class="k">Moneda</td><td class="v">{{ $currency === 'PEN' ? 'Soles (S/)' : 'Dólares ($)' }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    @if ($doc['intro'])
        <p class="intro">{{ $doc['intro'] }}</p>
    @endif

    <table class="items">
        <thead>
            <tr>
                <th style="width: 18pt;">#</th>
                <th>Descripción</th>
                <th class="right" style="width: 40pt;">Cant.</th>
                <th class="right" style="width: 72pt;">P. unit.</th>
                @if ($hasLineDiscounts)<th class="right" style="width: 54pt;">Desc.</th>@endif
                <th class="right" style="width: 80pt;">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($doc['items'] as $index => $item)
                <tr>
                    <td class="faint">{{ $index + 1 }}</td>
                    <td>
                        <div class="item-name">{{ $item['name'] }}</div>
                        @if ($item['description'])<div class="item-desc">{{ $item['description'] }}</div>@endif
                    </td>
                    <td class="right num">{{ F::number($item['quantity']) }}</td>
                    <td class="right num">{{ F::money($item['unit_price'], $currency) }}</td>
                    @if ($hasLineDiscounts)<td class="right num muted">{{ F::discount($item['discount_type'], $item['discount_value'], $currency) }}</td>@endif
                    <td class="right num" style="font-weight: 500;">{{ F::money($item['line_total'], $currency) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Subtotal</td><td class="right num">{{ F::money($doc['subtotal'], $currency) }}</td></tr>
        @if ($doc['global_discount'] !== '0.00')
            <tr>
                <td>Descuento{{ $doc['global_discount_type'] === 'percent' ? ' ('.F::number($doc['global_discount_value']).'%)' : '' }}</td>
                <td class="right num">-{{ F::money($doc['global_discount'], $currency) }}</td>
            </tr>
        @endif
        <tr class="muted"><td>Op. gravada</td><td class="right num">{{ F::money($doc['tax_base'], $currency) }}</td></tr>
        <tr class="muted"><td>IGV {{ F::number($doc['tax_rate']) }}%</td><td class="right num">{{ F::money($doc['tax_amount'], $currency) }}</td></tr>
        <tr><td colspan="2" style="height: 5pt;"></td></tr>
        <tr class="grand"><td class="k">Total</td><td class="v right num">{{ F::money($doc['total'], $currency) }}</td></tr>
    </table>
    @if ($doc['discount_total'] !== '0.00')
        <div class="note">Incluye descuentos por {{ F::money($doc['discount_total'], $currency) }}</div>
    @endif
    <div class="note">Precios con IGV incluido</div>

    @if ($doc['observations'] || $doc['terms'])
        <table class="section">
            <tr>
                @if ($doc['observations'])
                    <td style="width: 50%; padding-right: 14pt;"><div class="label">Observaciones</div><p>{{ $doc['observations'] }}</p></td>
                @endif
                @if ($doc['terms'])
                    <td><div class="label">Condiciones</div><p>{{ $doc['terms'] }}</p></td>
                @endif
            </tr>
        </table>
    @endif

    @if (count($accounts) > 0 || $company['yape_phone'] || $company['plin_phone'])
        <div class="payments">
            <div class="label">Datos de pago</div>
            <table>
                @foreach (array_chunk([...$accounts, ...(($company['yape_phone'] || $company['plin_phone']) ? [['wallet' => true]] : [])], 2) as $row)
                    <tr>
                        @foreach ($row as $account)
                            <td>
                                @if (isset($account['wallet']))
                                    @if ($company['yape_phone'])<div>Yape: {{ $company['yape_phone'] }}</div>@endif
                                    @if ($company['plin_phone'])<div>Plin: {{ $company['plin_phone'] }}</div>@endif
                                    @if ($company['wallet_holder'])<div class="muted">{{ $company['wallet_holder'] }}</div>@endif
                                @else
                                    <div style="font-weight: 500;">{{ $account['bank'] }}@if (! empty($account['currency'])) · {{ $account['currency'] === 'PEN' ? 'Soles' : 'Dólares' }}@endif</div>
                                    <div>Cuenta {{ $account['number'] }}</div>
                                    @if (! empty($account['cci']))<div class="muted">CCI {{ $account['cci'] }}</div>@endif
                                    @if (! empty($account['holder']))<div class="muted">{{ $account['holder'] }}</div>@endif
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </table>
        </div>
    @endif
</div>
</body>
</html>
