@php
    $biometricReady = \Illuminate\Support\Facades\Schema::hasTable('biometric_devices') && \Illuminate\Support\Facades\Schema::hasTable('biometric_device_users');
    $hasNumber = $biometricReady && $person && $person->device_employee_no;
    $eligibleRoles = ['student', 'teacher', 'staff'];
    $devices = $biometricReady ? \App\Models\BiometricDevice::where('is_active', true)->orderBy('name')->get() : collect();
    $rows = $hasNumber
        ? \App\Models\BiometricDeviceUser::where('user_id', $person->id)->get()->keyBy('biometric_device_id')
        : collect();
    $confirmedNumber = $hasNumber ? $person->confirmed_machine_number : null;
    $canSync = $person && $person->is_active && in_array($person->role, $eligibleRoles, true) && $devices->isNotEmpty();
@endphp
@if($biometricReady && $person && in_array($person->role, $eligibleRoles, true))
<div class="panel form-section mb-4">
    <h2 class="form-section-title">Attendance machine</h2>
    <p class="mb-2">Machine Employee No.: <strong>{{ $confirmedNumber ?: 'Not confirmed' }}</strong></p>
    @if(! $confirmedNumber)<p class="form-text">The same Employee No. is sent to the machine and appears here after the bridge confirms success. A waiting sync does not mean the machine user has been created.</p>@endif
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
                    @elseif($row->action === 'delete')
                        <span class="text-muted">{{ $row->status === 'synced' ? 'Removed' : 'Removal pending' }}</span>
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
            <button class="btn btn-outline-secondary btn-sm" type="submit"><i class="fas fa-sync-alt me-1" aria-hidden="true"></i>Sync now</button>
        </form>
        <div class="form-text">Re-sends this person to the machines on the bridge PC's next run.</div>
    @endif
</div>
@endif
