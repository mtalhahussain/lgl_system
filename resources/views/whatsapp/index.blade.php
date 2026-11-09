@extends('layouts.admin')
@section('title', 'WhatsApp Reminders')
@section('content')
<x-list.header title="WhatsApp Reminders" eyebrow="Finance" subtitle="Fee reminders to students through the WhatsApp Cloud API" />
<x-list.kpis :items="$kpis" />
@unless($configured)
    <div class="alert alert-warning">WhatsApp is not connected yet. Follow the setup steps below; nothing is sent until the server has <code>WHATSAPP_TOKEN</code> and <code>WHATSAPP_PHONE_NUMBER_ID</code>.</div>
@endunless
<div class="tw:grid tw:grid-cols-1 tw:gap-5 tw:lg:grid-cols-2 tw:mb-6">
    <section class="panel" aria-labelledby="wa-settings">
        <div class="panel-heading"><h2 id="wa-settings">Reminder settings</h2></div>
        <form method="post" action="{{ route('whatsapp.settings') }}" class="row g-3" style="padding:18px 22px">
            @csrf @method('PUT')
            <div class="col-12 form-check form-switch ms-2">
                <input class="form-check-input" type="checkbox" id="enabled" name="enabled" value="1" @checked(old('enabled', $settings['enabled']))>
                <label class="form-check-label" for="enabled"><strong>Send automatic reminders</strong> (daily at 10:00)</label>
            </div>
            <div class="col-sm-6">
                <label class="form-label" for="days_before">Days before due date</label>
                <input class="form-control" type="number" id="days_before" name="days_before" min="0" max="30" value="{{ old('days_before', $settings['days_before']) }}" required>
                <div class="form-text">0 = no early reminder.</div>
            </div>
            <div class="col-sm-6 d-flex align-items-center">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="on_due_date" name="on_due_date" value="1" @checked(old('on_due_date', $settings['on_due_date']))>
                    <label class="form-check-label" for="on_due_date">Also on the due date</label>
                </div>
            </div>
            <div class="col-sm-6">
                <label class="form-label" for="template">Template name</label>
                <input class="form-control" id="template" name="template" value="{{ old('template', $settings['template']) }}" required>
            </div>
            <div class="col-sm-6">
                <label class="form-label" for="language">Template language</label>
                <input class="form-control" id="language" name="language" value="{{ old('language', $settings['language']) }}" required>
                <div class="form-text">Exactly as approved in Meta, e.g. en or en_US.</div>
            </div>
            <div class="col-12"><button class="btn btn-primary">Save settings</button></div>
            <p class="form-text mb-0">Each unpaid installment gets each automatic reminder once (active or completed enrollments, active students with a phone). Failed messages are not retried automatically; use the button in Fee Management to resend.</p>
        </form>
    </section>
    <section class="panel" aria-labelledby="wa-setup">
        <div class="panel-heading"><h2 id="wa-setup">Setup</h2></div>
        <div style="padding:18px 22px">
            <ol>
                <li>In Meta Business Manager create a WhatsApp Business account and add the institute number (WhatsApp Manager → Phone numbers).</li>
                <li>Create a <strong>Utility</strong> message template named <code>{{ $settings['template'] }}</code> with five body variables, for example:
                    @verbatim<pre class="mt-2 p-2 bg-light border rounded" style="white-space:pre-wrap">Assalam o Alaikum {{1}}, this is a reminder from {{5}}: your fee installment of {{2}} for {{4}} is due on {{3}}. Please ignore this message if you have already paid.</pre>@endverbatim
                    1 student name · 2 amount · 3 due date · 4 course · batch · 5 institute name. Wait until Meta approves it.</li>
                <li>Create a System User with a permanent access token (whatsapp_business_messaging permission) and note the Phone number ID.</li>
                <li>On the server set <code>WHATSAPP_TOKEN</code> and <code>WHATSAPP_PHONE_NUMBER_ID</code> in <code>.env</code>, then run <code>php artisan config:cache</code>. The token is never stored in the database.</li>
                <li>Send a test message below, then switch automatic reminders on. The server cron <code>php artisan schedule:run</code> must be running.</li>
            </ol>
            <form method="post" action="{{ route('whatsapp.test') }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-sm-8"><label class="form-label" for="phone">Test number</label><input class="form-control" id="phone" name="phone" type="tel" inputmode="tel" pattern="\+?[0-9]+" title="Digits only, with an optional leading +" placeholder="03001234567" maxlength="20" required></div>
                <div class="col-sm-4"><button class="btn btn-outline-success w-100" @disabled(! $configured)>Send test</button></div>
            </form>
        </div>
    </section>
</div>
<div class="panel list-panel">
    <x-list.toolbar :filters="$filters" search="Search student or phone…" :csv="true" />
    <div class="table-responsive">
        <table class="table list-table">
            <thead><tr>
                <x-list.th sort="time" default="-time">Time</x-list.th>
                <th scope="col">Student</th>
                <th scope="col">Phone</th>
                <th scope="col">Type</th>
                <th scope="col" class="num">Amount</th>
                <th scope="col">Due date</th>
                <th scope="col">Status</th>
            </tr></thead>
            <tbody>
            @forelse($messages as $message)
                <tr>
                    <td>{{ $message->created_at->format('d M Y h:i A') }}</td>
                    <td>@if($message->student)<a href="{{ route('students.show', $message->student) }}">{{ $message->student->name }}</a><span class="cell-sub">{{ $message->enrollment?->batch?->name }}</span>@else — @endif</td>
                    <td>{{ $message->phone ?: '—' }}</td>
                    <td>{{ $message->kindLabel() }}@if($message->sender)<span class="cell-sub">by {{ $message->sender->name }}</span>@endif</td>
                    <td class="num">{{ $message->params[1] ?? '—' }}</td>
                    <td>{{ $message->params[2] ?? '—' }}</td>
                    <td><x-list.status :value="$message->status" />@if($message->error)<span class="cell-sub text-danger">{{ \Illuminate\Support\Str::limit($message->error, 140) }}</span>@endif</td>
                </tr>
            @empty
                <x-list.empty :colspan="7" message="No WhatsApp messages yet." />
            @endforelse
            </tbody>
        </table>
    </div>
    <x-list.footer :paginator="$messages" label="messages" />
</div>
@endsection
