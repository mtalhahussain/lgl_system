{{-- Printable receipt/slip card. rows: [label => value]; a reversal shows a stamp and notice. --}}
@props(['kind', 'number', 'institute' => null, 'amount', 'currency' => 'PKR', 'date' => null, 'rows' => [], 'reversal' => null])
<article class="doc-card panel" aria-labelledby="doc-title">
    <header class="doc-head">
        <div>
            <div class="doc-institute"><span class="brand-mark" aria-hidden="true"><i class="fas fa-graduation-cap"></i></span>{{ $institute ?? 'Institute details unavailable' }}</div>
            <h1 id="doc-title">{{ $kind }}</h1>
            <div class="doc-number">{{ $number }}</div>
        </div>
        <div class="doc-amount">
            <small>Amount</small>
            <strong>{{ $currency }} {{ number_format((float) $amount, 2) }}</strong>
            @if($date)<span>{{ $date }}</span>@endif
        </div>
    </header>
    @if($reversal)
        <div class="doc-stamp" aria-hidden="true">Reversed</div>
        <div class="alert alert-warning mx-4 mt-3 mb-0" role="status"><strong>Reversed{{ $reversal->created_at ? ' '.\Illuminate\Support\Carbon::parse($reversal->created_at)->format('d M Y') : '' }}:</strong> {{ $reversal->reason }}. Original record retained.</div>
    @endif
    <dl class="doc-rows">
        @foreach($rows as $label => $value)
            <div><dt>{{ $label }}</dt><dd>{{ filled($value) ? $value : '—' }}</dd></div>
        @endforeach
    </dl>
    {{ $slot }}
    <footer class="doc-foot">System-generated record · Printed {{ now()->format('d M Y h:i A') }}</footer>
</article>
