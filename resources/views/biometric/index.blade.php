@extends('layouts.admin')
@section('title', 'Attendance Machine')
@section('content')
@push('styles')
<style>
.conn-grid{display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));margin-bottom:14px}
.conn-card{border:1px solid var(--line);border-radius:10px;padding:12px 14px;background:#fff}
.conn-step{font-size:10px;font-weight:650;letter-spacing:.05em;text-transform:uppercase;color:var(--muted)}
.conn-card h3{font-size:12.5px;font-weight:600;margin:5px 0 0;display:flex;align-items:center}
.conn-dot{width:9px;height:9px;border-radius:50%;display:inline-block;margin-right:7px;flex:none}
.dot-ok{background:#127a46}.dot-warn{background:#d98a00}.dot-bad{background:#dc2626}.dot-muted{background:#9aa3b2}
.conn-label{font-size:13px;font-weight:600;margin:6px 0 0;color:var(--ink)}
.conn-fix{font-size:11.5px;line-height:1.45;color:var(--muted);margin:4px 0 0}
.conn-alert{border:1px solid #f9cfcb;background:#fdecec;color:#7a271a;border-radius:9px;padding:10px 13px;font-size:12.5px;margin-bottom:14px}
.conn-alert code{display:block;margin-top:5px;white-space:normal;color:#991b1b;background:none;padding:0}
.conn-log{width:100%;font-size:12px;border-collapse:collapse;margin-top:8px}
.conn-log th,.conn-log td{text-align:left;padding:6px 8px;border-bottom:1px solid var(--line);vertical-align:top}
.conn-log th{font-size:10px;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);font-weight:650}
.conn-log time{font-variant-numeric:tabular-nums;color:var(--muted)}
.conn-actions{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px}
</style>
@endpush
<x-list.header title="Attendance Machine" eyebrow="Attendance" subtitle="Hikvision face and fingerprint punches mark students present or late in their running class" />
<x-list.kpis :items="$kpis" />
<div class="tw:grid tw:grid-cols-1 tw:gap-5 tw:lg:grid-cols-2 tw:mb-6">
    @foreach($devices as $device)
        @php
            $h = $health[$device->id];
            $overall = ['ok' => ['tone-ok', 'Connected'], 'warn' => ['tone-warn', 'Check connection'],
                'bad' => ['tone-bad', 'Needs attention'], 'muted' => ['tone-muted', 'Not set up yet']][$h['overall']];
            $counts = $sync[$device->id] ?? collect();
            $lastFail = ($failed[$device->id] ?? collect())->first();
            $log = $activity[$device->id] ?? collect();
        @endphp
        <section class="panel" aria-labelledby="device-{{ $device->id }}">
            <div class="panel-heading">
                <h2 id="device-{{ $device->id }}">{{ $device->name }} @unless($device->is_active)<span class="status-pill tone-muted">Inactive</span>@endunless</h2>
                <span class="status-pill {{ $overall[0] }}">{{ $overall[1] }}</span>
            </div>
            <div style="padding:18px 22px">
                {{-- Connection check: three traffic-lights the Admin can read without opening the bridge PC's log file. --}}
                <div class="conn-grid">
                    @foreach($h['checks'] as $c)
                        <div class="conn-card">
                            <div class="conn-step">{{ $c['title'] }}</div>
                            <h3><span class="conn-dot dot-{{ $c['tone'] }}"></span>{{ $c['desc'] }}</h3>
                            <p class="conn-label">{{ $c['label'] }}</p>
                            @if($c['fix'])<p class="conn-fix">{{ $c['fix'] }}</p>@endif
                        </div>
                    @endforeach
                </div>
                @if($lastFail)
                    <div class="conn-alert">
                        <strong>Latest error</strong> — {{ $lastFail->employee_no }} · {{ $lastFail->user?->name ?? $lastFail->name }} ({{ $lastFail->action }}, {{ $lastFail->attempts }} {{ \Illuminate\Support\Str::plural('try', $lastFail->attempts) }})
                        <code>{{ \Illuminate\Support\Str::limit($lastFail->last_error, 240) }}</code>
                    </div>
                @endif
                <details class="mb-3" @if($h['overall'] !== 'ok') open @endif>
                    <summary class="fw-semibold">Connection log &mdash; last {{ $log->count() }} sync results</summary>
                    @if($log->isEmpty())
                        <p class="form-text mt-2 mb-0">No sync attempts yet. Once the bridge PC runs, each user it sends to the machine shows here with the result &mdash; no need to open the .log file on the bridge PC.</p>
                    @else
                        <div class="table-responsive">
                            <table class="conn-log">
                                <thead><tr><th>Time</th><th>Person</th><th>Action</th><th>Result</th></tr></thead>
                                <tbody>
                                @foreach($log as $row)
                                    <tr>
                                        <td><time>{{ $row->updated_at?->format('d M h:i A') }}</time></td>
                                        <td>{{ $row->user?->name ?? $row->name }} <span class="cell-sub">{{ $row->employee_no }}</span></td>
                                        <td>{{ ucfirst($row->action) }}</td>
                                        <td>
                                            @if($row->status === 'synced')
                                                <span class="status-pill tone-ok">OK</span>
                                            @else
                                                <span class="status-pill tone-bad">Failed</span>
                                                <span class="cell-sub">{{ \Illuminate\Support\Str::limit($row->last_error, 120) }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </details>
                <div class="conn-actions">
                    <form method="post" action="{{ route('biometric.resync', $device) }}" data-confirm="Send all active students and teachers to this machine again?">@csrf<button class="btn btn-outline-primary btn-sm" @disabled(! $device->is_active)>Send all users again</button></form>
                    <a class="btn btn-outline-secondary btn-sm" href="{{ route('biometric.index') }}">Refresh check</a>
                </div>
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
    <x-list.toolbar :filters="$filters" search="Search employee no. or name…" :csv="true" :per-page-default="25" />
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
                    <td>{{ $punch->punched_at->format('d M Y h:i:s A') }}</td>
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
