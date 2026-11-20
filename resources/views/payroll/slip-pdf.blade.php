@php
    $d = $payout->slip_details ?? [];
    $currency = $d['currency_code'] ?? 'PKR';
    $logo = \App\Support\PdfImage::instituteLogo();
    $institute = $d['institute_name'] ?? institute_name();
    $contact = array_filter([setting('institute_address'), setting('institute_phone'), setting('institute_email')]);
    $teacher = ($d['teacher_name'] ?? 'Historical details unavailable').(! empty($d['teacher_reference']) ? ' ('.$d['teacher_reference'].')' : '');
    $rows = array_filter([
        'Paid to' => $teacher,
        'For' => $d['description'] ?? null,
        'Due amount' => isset($d['due_amount']) ? $currency.' '.number_format((float) $d['due_amount'], 2) : null,
        'Method' => \App\Services\PayrollService::METHODS[$payout->method] ?? $payout->method,
        'Reference' => $payout->reference,
    ], fn ($v) => $v !== null && $v !== '');
@endphp
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Pay slip {{ $payout->voucher_number }}</title>
<style>
    @page { size: A5 portrait; margin: 12mm; }
    body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 9.5pt; color: #1f2937; margin: 0; }
    table { width: 100%; border-collapse: collapse; }
    .head td { vertical-align: middle; }
    .logo { width: 22mm; padding-right: 4mm; }
    .logo img { max-width: 22mm; max-height: 22mm; }
    .inst { font-size: 13pt; font-weight: bold; color: #0f2a4a; }
    .contact { font-size: 8pt; color: #6b7280; margin-top: 1mm; }
    .rule { border-bottom: 2px solid #0f2a4a; margin: 4mm 0 5mm; }
    .title { font-size: 12pt; font-weight: bold; letter-spacing: .5pt; text-transform: uppercase; }
    .title-cell { width:45mm; }
    .meta { font-size: 8.5pt; color: #6b7280; }
    .amount { border: 1px solid #0f2a4a; padding: 2mm 3mm; text-align: right; }
    .amount small { display: block; font-size: 7.5pt; color: #6b7280; }
    .amount strong { font-size: 12pt; }
    .details { margin-top: 6mm; }
    .details td { padding: 2mm 0; border-bottom: 1px solid #e5e7eb; }
    .details td.label { width: 32mm; color: #6b7280; }
    .stamp { margin-top: 6mm; border: 3px solid #c0202a; color: #c0202a; text-align: center; font-size: 20pt; font-weight: bold; letter-spacing: 4pt; padding: 2mm; }
    .stamp-reason { color: #c0202a; font-size: 8.5pt; margin-top: 1mm; }
    .signs { margin-top: 18mm; }
    .signs td { width: 50%; padding-top: 1mm; font-size: 8.5pt; color: #6b7280; }
    .signs .line { border-top: 1px solid #1f2937; width: 45mm; padding-top: 1mm; }
</style>
</head>
<body>
    <table class="head"><tr>
        @if($logo)<td class="logo"><img src="{{ $logo }}" alt="Logo"></td>@endif
        <td>
            <div class="inst">{{ $institute }}</div>
            @if($contact)<div class="contact">{{ implode(' · ', $contact) }}</div>@endif
        </td>
    </tr></table>
    <div class="rule"></div>

    <table><tr>
        <td>
            <div class="title">Salary Payment Slip</div>
            <div class="meta">Voucher {{ $payout->voucher_number }}</div>
            <div class="meta">{{ $payout->paid_at->format('d M Y, h:i A') }}</div>
        </td>
        <td class="title-cell">
            <div class="amount"><small>Amount</small><strong>{{ $currency }} {{ number_format((float) $payout->amount, 2) }}</strong></div>
        </td>
    </tr></table>

    <table class="details">
        @foreach($rows as $label => $value)
            <tr><td class="label">{{ $label }}</td><td>{{ $value }}</td></tr>
        @endforeach
    </table>

    @if($payout->reversal)
        <div class="stamp">REVERSED</div>
        <div class="stamp-reason">Reason: {{ $payout->reversal->reason }}</div>
    @endif

    <table class="signs"><tr>
        <td><div class="line">Received by</div></td>
        <td><div class="line">Authorised by</div></td>
    </tr></table>
</body>
</html>
