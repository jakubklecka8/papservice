@php
    $v = $d['visit'];
    $fmt = fn ($iso) => $iso ? \Illuminate\Support\Carbon::parse($iso)->timezone('Europe/Prague')->format('d.m.Y H:i') : '—';
    $test = $v['test_result'] ? ($l['test_'.$v['test_result']] ?? $v['test_result']) : '—';
@endphp
<!DOCTYPE html>
<html lang="{{ $d['language'] }}">
<head>
<meta charset="utf-8">
<title>{{ $l['title'] }} {{ $d['ticket']['number'] }}</title>
<style>
    @page { size: A4; margin: 14mm; }
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11pt; color: #111; max-width: 190mm; margin: 0 auto; }
    h1 { font-size: 17pt; margin: 0 0 2mm; }
    .muted { color: #555; font-size: 9.5pt; }
    table { width: 100%; border-collapse: collapse; margin: 4mm 0; }
    th, td { border: 1px solid #bbb; padding: 2mm 2.5mm; text-align: left; vertical-align: top; }
    th { background: #f1f1f1; width: 34%; font-weight: 600; }
    .parts th { width: auto; }
    .block { white-space: pre-wrap; }
    .sigs td { width: 50%; height: 30mm; }
    .sigs img { max-height: 24mm; max-width: 80mm; display: block; margin-top: 2mm; }
    .approved { margin-top: 6mm; padding: 3mm; border: 1px solid #2a7; color: #174; }
    .draft { margin-top: 6mm; padding: 3mm; border: 1px dashed #c80; color: #840; }
</style>
</head>
<body>
<h1>{{ $l['title'] }}</h1>
<div class="muted">PAP Service · {{ $d['project']['name'] }}</div>

<table>
    <tr><th>{{ $l['number'] }}</th><td>{{ $d['ticket']['number'] }}</td></tr>
    <tr><th>{{ $l['project'] }}</th><td>{{ $d['project']['name'] }} ({{ $d['ticket']['service_type'] }})</td></tr>
    @if ($d['location'])
        <tr><th>{{ $l['location'] }}</th><td>{{ $d['location']['code'] }} – {{ $d['location']['name'] }}</td></tr>
        <tr><th>{{ $l['address'] }}</th><td>{{ $d['location']['address'] }}</td></tr>
    @endif
    <tr><th>{{ $l['technician'] }}</th><td>{{ $d['technician'] }}</td></tr>
    <tr><th>{{ $l['arrived'] }}</th><td>{{ $fmt($v['arrived_at']) }}</td></tr>
    <tr><th>{{ $l['departed'] }}</th><td>{{ $fmt($v['departed_at']) }}</td></tr>
    <tr><th>{{ $l['minutes'] }}</th><td>{{ $v['minutes_on_site'] ?? '—' }}</td></tr>
    <tr><th>{{ $l['km'] }}</th><td>{{ $v['km'] ?? '—' }}</td></tr>
    <tr><th>{{ $l['reported'] }}</th><td class="block">{{ $d['ticket']['title'] }}@if($d['ticket']['description'])

{{ $d['ticket']['description'] }}@endif</td></tr>
    <tr><th>{{ $l['work'] }}</th><td class="block">{{ $v['work_description'] ?? '—' }}</td></tr>
    <tr><th>{{ $l['repair'] }}</th><td class="block">{{ $v['repair_description'] ?? '—' }}</td></tr>
    <tr><th>{{ $l['test'] }}</th><td>{{ $test }}@if($v['test_note']) – {{ $v['test_note'] }}@endif</td></tr>
    @if ($v['instruction_ref'])
        <tr><th>{{ $l['instruction'] }}</th><td>{{ $v['instruction_ref'] }}</td></tr>
    @endif
    <tr><th>{{ $l['photos'] }}</th><td>{{ count($d['photos']) }}</td></tr>
</table>

@if (count($d['parts']))
    <strong>{{ $l['parts'] }}</strong>
    <table class="parts">
        <tr><th>{{ $l['part'] }}</th><th>{{ $l['qty'] }}</th><th>{{ $l['serial_removed'] }}</th><th>{{ $l['serial_installed'] }}</th></tr>
        @foreach ($d['parts'] as $p)
            <tr>
                <td>{{ $p['name'] }}@if($p['part_number']) ({{ $p['part_number'] }})@endif</td>
                <td>{{ rtrim(rtrim(number_format($p['quantity'], 2, ',', ''), '0'), ',') }}</td>
                @foreach (['serial_removed', 'serial_installed'] as $sn)
                    <td>@if ($p[$sn]){{ $p[$sn] }}@elseif (! empty($p['serialized']))<strong style="color:#c0392b">⚠ {{ $l['missing'] ?? '—' }}</strong>@else—@endif</td>
                @endforeach
            </tr>
        @endforeach
    </table>
@endif

@if (! empty($d['device']))
    <table><tr><th>{{ $l['device'] ?? 'Zařízení' }}</th><td>{{ $d['device']['type'] }}@if($d['device']['model']) · {{ $d['device']['model'] }}@endif @if($d['device']['serial_number']) · S/N {{ $d['device']['serial_number'] }}@endif</td></tr></table>
@endif
@if (! empty($d['checklist']))
    <h3 style="font-size:12pt;margin:5mm 0 1mm">{{ $l['checklist'] ?? 'Kontrolní seznam' }}</h3>
    <table class="parts">
        @foreach ($d['checklist'] as $c)
            <tr><td>{{ $c['text'] }}@if($c['note'])<div class="muted">{{ $c['note'] }}</div>@endif</td>
                <td style="width:22mm;font-weight:bold;{{ $c['result'] === 'nok' ? 'color:#c0392b' : '' }}">{{ $c['result'] ? ($l['res_'.$c['result']] ?? $c['result']) : '—' }}</td></tr>
        @endforeach
    </table>
@endif

@php $sig = $d['signatures'] ?? []; @endphp
@if (! empty($sig['technician']) || ! empty($sig['customer']))
    <table class="sigs">
        <tr>
            <td><div class="muted">{{ $l['sig_technician'] ?? 'Podpis technika' }} · {{ $d['technician'] }}</div>
                @if (! empty($sig['technician']))<img src="{{ $sig['technician'] }}" alt="">@endif</td>
            <td><div class="muted">{{ $l['sig_customer'] ?? 'Podpis zákazníka' }}@if (! empty($sig['signer_name'])) · {{ $sig['signer_name'] }}@endif</div>
                @if (! empty($sig['customer']))<img src="{{ $sig['customer'] }}" alt="">@endif</td>
        </tr>
    </table>
@endif

@if ($protocol->isApproved())
    <div class="approved">{{ $l['approved'] }}: {{ $protocol->approved_at->timezone('Europe/Prague')->format('d.m.Y H:i') }} · {{ $protocol->approver?->name }}</div>
@else
    <div class="draft">DRAFT</div>
@endif
</body>
</html>
