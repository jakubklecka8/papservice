@php
    $v = $d['visit'];
    $l = $d['m4b_labels'];
    $sig = $d['signatures'] ?? [];
    $fmt = fn ($iso) => $iso ? \Illuminate\Support\Carbon::parse($iso)->timezone('Europe/Prague')->format('d.m.Y H:i') : '—';
    $loc = $d['location'];
    $client = $loc ? trim(($loc['name'] ?? '').(! empty($loc['code']) ? ' ('.$loc['code'].')' : '')) : ($d['ticket']['title'] ?? '—');
    $notes = trim(implode("\n", array_filter([$v['repair_description'], $v['test_note']])));
    $end = $fmt($v['departed_at'] ?? $sig['signed_at'] ?? null);
@endphp
<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="utf-8">
<title>Protokol {{ $d['ticket']['number'] }} – {{ $client }}</title>
<style>
    @page { size: A4; margin: 10mm; }
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, Arial, sans-serif; margin: 0; color: #111827; }
    .page { max-width: 190mm; margin: 0 auto; }
    .header { background: #1a1f2e; color: #fff; text-align: center; padding: 6mm 8mm; }
    .header h1 { font-size: 20pt; font-weight: bold; letter-spacing: 3pt; margin: 0; }
    .header p { font-size: 8pt; color: #a5a9b8; letter-spacing: 2pt; text-transform: uppercase; margin: 1.5mm 0 0; }
    table { width: 100%; border-collapse: collapse; }
    td { border: 1px solid #ccc; padding: 3.5mm 4.5mm; vertical-align: top; }
    .lbl { font-size: 6.5pt; color: #888; font-weight: bold; text-transform: uppercase; letter-spacing: 0.6pt; margin-bottom: 1.5mm; }
    .val { font-size: 11pt; font-weight: bold; }
    .val-sm { font-size: 10pt; color: #374151; }
    .val-pre { font-size: 10pt; color: #374151; white-space: pre-wrap; min-height: 45mm; }
    .val-notes { font-size: 10pt; color: #374151; white-space: pre-wrap; min-height: 20mm; }
    .parts { margin-top: 3mm; font-size: 9pt; color: #374151; }
    .parts td { border: none; border-top: 1px dotted #ddd; padding: 1mm 0; }
    .sig-cell { height: 42mm; width: 50%; }
    .sig-img { height: 22mm; display: block; margin: 1mm 0; }
    .sig-space { height: 22mm; }
    .sig-line { border-bottom: 1px solid #999; margin: 1mm 0 1.5mm; }
    .sig-note { font-size: 7pt; color: #999; text-align: center; }
    .foot { font-size: 7pt; color: #aaa; margin-top: 2mm; text-align: right; }
    .draft { margin-top: 3mm; padding: 2mm; border: 1px dashed #c80; color: #840; font-size: 9pt; }
</style>
</head>
<body>
<div class="page">
    <div class="header"><h1>{{ $l['title'] }}</h1><p>{{ $l['subtitle'] }}</p></div>
    <table>
        <tr>
            <td style="width:50%"><div class="lbl">{{ $l['contractor'] }}</div><div class="val">{{ $d['technician'] }}</div></td>
            <td><div class="lbl">{{ $l['client'] }}</div><div class="val">{{ $client }}</div>
                @if ($loc && $loc['address'])<div class="val-sm">{{ $loc['address'] }}</div>@endif</td>
        </tr>
        <tr>
            <td><div class="lbl">{{ $l['start'] }}</div><div class="val-sm">{{ $fmt($v['arrived_at']) }}</div></td>
            <td><div class="lbl">{{ $l['end'] }}</div><div class="val-sm">{{ $fmt($v['departed_at']) }}</div></td>
        </tr>
        <tr><td colspan="2"><div class="lbl">{{ $l['work'] }}</div>
            <div class="val-pre">{{ $v['work_description'] ?: '—' }}</div>
            @if (count($d['parts']))
                <div class="lbl" style="margin-top:3mm">{{ $l['parts'] }}</div>
                <table class="parts">
                    @foreach ($d['parts'] as $p)
                        <tr><td>{{ rtrim(rtrim(number_format($p['quantity'], 2, ',', ''), '0'), ',') }}× {{ $p['name'] }}@if($p['part_number']) ({{ $p['part_number'] }})@endif
                            @if($p['serial_removed']) · S/N out: {{ $p['serial_removed'] }}@endif
                            @if($p['serial_installed']) · S/N in: {{ $p['serial_installed'] }}@endif</td></tr>
                    @endforeach
                </table>
            @endif
        </td></tr>
        <tr><td colspan="2"><div class="lbl">{{ $l['notes'] }}</div><div class="val-notes">{{ $notes }}</div></td></tr>
        <tr>
            <td class="sig-cell"><div class="lbl">{{ $l['sig_contractor'] }}</div>
                @if (! empty($sig['technician']))<img class="sig-img" src="{{ $sig['technician'] }}" alt="">@else<div class="sig-space"></div>@endif
                <div class="sig-line"></div>
                <div class="sig-note">{{ $l['sig_contractor_note'] }} &nbsp; {{ $end }}</div></td>
            <td class="sig-cell"><div class="lbl">{{ $l['sig_client'] }}@if (! empty($sig['signer_name'])) · {{ $sig['signer_name'] }}@endif</div>
                @if (! empty($sig['customer']))<img class="sig-img" src="{{ $sig['customer'] }}" alt="">@else<div class="sig-space"></div>@endif
                <div class="sig-line"></div>
                <div class="sig-note">{{ $l['sig_client_note'] }} &nbsp; {{ $end }}</div></td>
        </tr>
    </table>
    <div class="foot">{{ $l['number'] }}: {{ $d['ticket']['number'] }}@if(! empty($d['ticket']['external_ref'])) · {{ $d['ticket']['external_ref'] }}@endif · PAP Service</div>
    @unless ($protocol->isApproved())<div class="draft">DRAFT</div>@endunless
</div>
</body>
</html>
