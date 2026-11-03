@extends('layouts.admin')
@section('title', 'Attendance Machine')
@section('content')
<x-list.header title="Attendance Machine" eyebrow="Attendance" subtitle="Hikvision face and fingerprint punches mark students present or late in their running class" />
<x-list.kpis :items="$kpis" />
@if($errors->any())
    <div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
<div class="tw:grid tw:grid-cols-1 tw:gap-5 tw:lg:grid-cols-2 tw:mb-6">
    @foreach($devices as $device)
        <section class="panel" aria-labelledby="device-{{ $device->id }}">
            <div class="panel-heading">
                <h2 id="device-{{ $device->id }}">{{ $device->name }}</h2>
                <x-list.status :value="$device->is_active ? 'active' : 'inactive'" />
            </div>
            <div style="padding:18px 22px">
                <p class="mb-2">Last contact: <strong>{{ $device->last_seen_at ? $device->last_seen_at->format('d M Y H:i').' ('.$device->last_seen_at->diffForHumans().')' : 'Never' }}</strong></p>
                @php $counts = $sync[$device->id] ?? collect(); @endphp
                <p class="mb-2">User sync: <strong>{{ $counts['synced'] ?? 0 }}</strong> on machine · <strong>{{ $counts['pending'] ?? 0 }}</strong> waiting · <strong class="{{ ($counts['failed'] ?? 0) ? 'text-danger' : '' }}">{{ $counts['failed'] ?? 0 }}</strong> failed
                    <span class="d-block form-text">Bridge PC last contact: {{ $device->bridge_seen_at ? $device->bridge_seen_at->format('d M Y H:i').' ('.$device->bridge_seen_at->diffForHumans().')' : 'Never' }}</span></p>
                @if(($failed[$device->id] ?? collect())->isNotEmpty())
                    <details class="mb-3">
                        <summary class="fw-semibold text-danger">Failed users</summary>
                        <ul class="mt-2 mb-0">@foreach($failed[$device->id] as $row)<li>{{ $row->employee_no }} · {{ $row->user?->name ?? $row->name }} ({{ $row->action }}, {{ $row->attempts }} tries): <span class="text-muted">{{ \Illuminate\Support\Str::limit($row->last_error, 160) }}</span></li>@endforeach</ul>
                    </details>
                @endif
                <details class="mb-3">
                    <summary class="fw-semibold">Bridge PC setup (sends students/teachers to the machine)</summary>
                    <ol class="mt-2">
                        <li>Use an always-on Windows PC on the same network as the machine. Create the folder <code>C:\LGL</code>.</li>
                        <li>Download <a href="{{ route('biometric.bridge-script') }}">hikvision-bridge.ps1</a> and <a href="{{ route('biometric.bridge-config', $device) }}">this machine's config file</a> into that folder.</li>
                        <li>Open the config file in Notepad and set <code>DeviceUrl</code> (for example <code>http://192.168.1.64</code>), <code>DeviceUser</code> and <code>DevicePassword</code> of the machine's admin account. The password stays on that PC only.</li>
                        <li>Test once in PowerShell: <code class="text-wrap" style="white-space:normal">powershell -NoProfile -ExecutionPolicy Bypass -File C:\LGL\hikvision-bridge.ps1 -Config C:\LGL\hikvision-bridge-{{ $device->id }}.json</code> and check the .log file next to it.</li>
                        <li>Run it every minute (Command Prompt as administrator): <code class="text-wrap" style="white-space:normal">schtasks /Create /SC MINUTE /MO 1 /TN "LGL Hikvision Bridge" /RU SYSTEM /TR "powershell -NoProfile -ExecutionPolicy Bypass -File C:\LGL\hikvision-bridge.ps1 -Config C:\LGL\hikvision-bridge-{{ $device->id }}.json"</code></li>
                        <li>On the machine, open each synced user and enroll their face and fingerprint.</li>
                    </ol>
                    <div class="d-flex flex-wrap gap-2">
                        <form method="post" action="{{ route('biometric.resync', $device) }}" data-confirm="Send all active students and teachers to this machine again?">@csrf<button class="btn btn-outline-primary btn-sm" @disabled(! $device->is_active)>Send all users again</button></form>
                        <form method="post" action="{{ route('biometric.bridge-token', $device) }}" data-confirm="Create a new bridge key? The bridge PC stops until you copy the new config file to it.">@csrf<button class="btn btn-outline-danger btn-sm">New bridge key</button></form>
                    </div>
                </details>
                <details class="mb-3">
                    <summary class="fw-semibold">Setup steps on the machine</summary>
                    <ol class="mt-2 mb-0">@foreach($steps[$device->id] as $step)<li><code class="text-wrap" style="white-space:normal">{{ $step }}</code></li>@endforeach</ol>
                    <p class="form-text mb-0">Keep this URL private: anyone with it can send punches.</p>
                </details>
                <details>
                    <summary class="fw-semibold">Edit machine</summary>
                    <form method="post" action="{{ route('biometric.update', $device) }}" class="row g-2 mt-1">
                        @csrf @method('PUT')
                        <div class="col-sm-6"><label class="form-label" for="name-{{ $device->id }}">Name</label><input class="form-control" id="name-{{ $device->id }}" name="name" value="{{ $device->name }}" maxlength="100" required></div>
                        <div class="col-sm-6"><label class="form-label" for="tz-{{ $device->id }}">Machine time zone</label><input class="form-control" id="tz-{{ $device->id }}" name="timezone" value="{{ $device->timezone }}" required></div>
                        <div class="col-12 form-check ms-1"><input class="form-check-input" type="checkbox" id="active-{{ $device->id }}" name="is_active" value="1" @checked($device->is_active)><label class="form-check-label" for="active-{{ $device->id }}">Active (inactive machines are seen but their punches are not recorded)</label></div>
                        <div class="col-12"><button class="btn btn-primary">Save</button></div>
                    </form>
                    <form method="post" action="{{ route('biometric.regenerate', $device) }}" class="mt-3" data-confirm="Create a new push URL? The machine stops working until you enter the new URL on it.">
                        @csrf
                        <button class="btn btn-outline-danger btn-sm">New push URL</button>
                    </form>
                </details>
            </div>
        </section>
    @endforeach
    <section class="panel" aria-labelledby="add-device">
        <div class="panel-heading"><h2 id="add-device">Add machine</h2></div>
        <form method="post" action="{{ route('biometric.store') }}" class="row g-2 align-items-end" style="padding:18px 22px">
            @csrf
            <div class="col-sm-6"><label class="form-label" for="name">Name</label><input class="form-control" id="name" name="name" placeholder="Main gate" maxlength="100" value="{{ old('name') }}" required></div>
            <div class="col-sm-6"><label class="form-label" for="timezone">Machine time zone</label><input class="form-control" id="timezone" name="timezone" value="{{ old('timezone', config('app.timezone')) }}" required></div>
            <div class="col-12"><button class="btn btn-primary">Add machine</button></div>
            <p class="form-text mb-0">Every active student and teacher gets a Machine Employee No. automatically and is queued for each machine. A student punch from 30 minutes before a class until it ends marks Present (Late after the session's late minutes). Absent is still marked by the teacher; a manual mark is never overwritten.</p>
        </form>
    </section>
</div>
<div class="panel list-panel">
    <x-list.toolbar :filters="$filters" search="Search employee no. or name…" :csv="true" />
    <div class="table-responsive">
        <table class="table list-table">
            <thead><tr>
                <x-list.th sort="time" default="-time">Time</x-list.th>
                <th scope="col">Employee no.</th>
                <th scope="col">Person</th>
                <th scope="col">Class</th>
                <th scope="col">Result</th>
                <th scope="col">Machine</th>
            </tr></thead>
            <tbody>
            @forelse($punches as $punch)
                <tr>
                    <td>{{ $punch->punched_at->format('d M Y H:i:s') }}</td>
                    <td><code>{{ $punch->employee_no }}</code></td>
                    <td>@if($punch->user)<span class="cell-main">{{ $punch->user->name }}</span><span class="cell-sub">{{ ucfirst($punch->user->role) }}</span>@else<span class="text-muted">Not linked</span>@endif</td>
                    <td>@if($punch->classSession)<a href="{{ route('class-sessions.show', $punch->classSession) }}">{{ $punch->classSession->batch?->name }}</a>@else — @endif</td>
                    <td><x-list.status :value="$punch->result" :label="$punch->resultLabel()" /></td>
                    <td>{{ $punch->device?->name }}</td>
                </tr>
            @empty
                <x-list.empty :colspan="6" message="No punches yet. Add a machine, set it up and make a test punch." />
            @endforelse
            </tbody>
        </table>
    </div>
    <x-list.footer :paginator="$punches" label="punches" />
</div>
@endsection
