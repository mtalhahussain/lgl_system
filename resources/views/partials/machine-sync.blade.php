@php
    $biometricReady = \Illuminate\Support\Facades\Schema::hasTable('biometric_devices') && \Illuminate\Support\Facades\Schema::hasTable('biometric_device_users');
    $hasNumber = $biometricReady && $person && $person->device_employee_no;
    $eligibleRoles = ['student', 'teacher', 'staff'];
    $devices = $hasNumber ? \App\Models\BiometricDevice::where('is_active', true)->orderBy('name')->get() : collect();
    $rows = $hasNumber
        ? \App\Models\BiometricDeviceUser::where('user_id', $person->id)->get()->keyBy('biometric_device_id')
        : collect();
    $canSync = $hasNumber && $person->is_active && in_array($person->role, $eligibleRoles, true) && $devices->isNotEmpty();
@endphp
@if($hasNumber)
<div class="form-section">
    <h2 class="form-section-title">Attendance machine</h2>
    <p class="mb-2">Machine Employee No.: <strong>{{ $person->device_employee_no }}</strong> <span class="text-muted">(automatic)</span></p>
    @if($devices->isEmpty())
        <p class="form-text">No active machine configured.</p>
    @else
        <ul class="mb-2">
            @foreach($devices as $device)
                @php($row = $rows->get($device->id))
                <li>
                    {{ $device->name }} —
                    @if(! $row)
                        <span class="text-muted">Not queued</span>
                    @elseif($row->status === 'synced')
                        <span class="text-success">Synced</span>
                    @elseif($row->status === 'failed')
                        <span class="text-danger">Failed</span> <span class="text-muted">{{ \Illuminate\Support\Str::limit($row->last_error, 120) }}</span>
                    @else
                        <span class="text-warning">Waiting</span>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
    @if($canSync)
        <form method="post" action="{{ route('biometric.resync-user', $person) }}">
            @csrf
            <button class="btn btn-outline-secondary btn-sm" type="submit">Sync now</button>
        </form>
        <div class="form-text">Re-sends this person to the machines on the bridge PC's next run.</div>
    @endif
</div>
@endif
