{{-- SPDX-FileCopyrightText: 2026 LibreCode coop and contributors --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>Capital Social</title>
<style>body{font-family:DejaVu Sans,sans-serif;font-size:11px}table{border-collapse:collapse;width:100%}td,th{border:1px solid #ccc;padding:4px;text-align:left}</style>
</head><body>
<h1>Capital Social</h1><p>Data de corte: {{ $class->asOf ?? 'Todas as datas' }}</p>
@if ($class->configurationError)
<p>{{ $class->configurationError }}</p>
@else
<p>Saldo confirmado (BRL): {{ \Modules\AkauntingReports\Support\Money::format($class->capitalSummary['total_cents']) }} — Pendências: {{ $class->capitalSummary['pending'] }}</p>
<table><thead><tr><th>Cooperado</th><th>Integralizado</th><th>Devolvido</th><th>Saldo</th><th>Pendências</th></tr></thead><tbody>
@foreach ($class->capitalSummary['members'] as $member)
<tr><td>{{ $member['name'] }}</td><td>{{ \Modules\AkauntingReports\Support\Money::format($member['integralizado_cents']) }}</td><td>{{ \Modules\AkauntingReports\Support\Money::format($member['devolvido_cents']) }}</td><td>{{ \Modules\AkauntingReports\Support\Money::format($member['saldo_cents']) }}</td><td>{{ $member['pending'] }}</td></tr>
@endforeach
</tbody></table>
<h2>Movimentações</h2>
<table><thead><tr><th>Data</th><th>Cooperado</th><th>Tipo</th><th>Valor</th><th>Status</th><th>Documento</th><th>Conferência</th></tr></thead><tbody>
@foreach ($class->movements as $row)
<tr><td>{{ $row['date'] }}</td><td>{{ $row['name'] }}</td><td>{{ $row['kind'] }}</td><td>{{ \Modules\AkauntingReports\Support\Money::format($row['amount_cents']) }}</td><td>{{ $row['status'] }}</td><td>{{ $row['source'] }} #{{ $row['id'] }}</td><td>{{ $row['reason'] }}</td></tr>
@endforeach
</tbody></table>
@endif
</body></html>
