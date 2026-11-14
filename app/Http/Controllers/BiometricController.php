<?php

namespace App\Http\Controllers;

use App\Biometric\Drivers\HikvisionDriver;
use App\Biometric\MachineUserSync;
use App\Models\AuditLog;
use App\Models\BiometricDevice;
use App\Models\BiometricDeviceUser;
use App\Models\BiometricPunch;
use App\Models\User;
use App\Support\ListQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Admin: attendance machines, punch log and teacher daily attendance. */
class BiometricController extends Controller
{
    public function index(Request $request, HikvisionDriver $driver)
    {
        $devices = BiometricDevice::orderBy('name')->get()->makeVisible(['webhook_token', 'bridge_token']);
        $sync = BiometricDeviceUser::selectRaw('biometric_device_id, status, COUNT(*) as total')->groupBy('biometric_device_id', 'status')
            ->get()->groupBy('biometric_device_id')->map(fn ($rows) => $rows->pluck('total', 'status'));
        $failed = BiometricDeviceUser::with('user:id,name')->where('status', 'failed')->latest('updated_at')->limit(20)->get()->groupBy('biometric_device_id');
        $list = ListQuery::for(BiometricPunch::with(['device:id,name', 'user:id,name,role', 'classSession.batch:id,name']), $request)
            ->search(['employee_no', 'user.name'])
            ->filter('result', fn ($query, $result) => $query->where('result', $result), array_keys(BiometricPunch::RESULTS))
            ->filter('device', fn ($query, $id) => $query->where('biometric_device_id', $id), $devices->pluck('id')->all())
            ->dateRange('punched_at')
            ->sorts(['time' => 'punched_at'], '-time');
        if ($list->wantsCsv()) {
            return $list->csv('machine-punches', ['Time' => fn ($punch) => $punch->punched_at->format('Y-m-d H:i:s'),
                'Device' => fn ($punch) => $punch->device?->name, 'Employee no.' => fn ($punch) => $punch->employee_no,
                'Person' => fn ($punch) => $punch->user?->name, 'Role' => fn ($punch) => $punch->user?->role,
                'Session' => fn ($punch) => $punch->classSession?->batch?->name, 'Result' => fn ($punch) => $punch->resultLabel()]);
        }
        $today = BiometricPunch::whereDate('punched_at', today())->selectRaw('result, COUNT(*) as total')->groupBy('result')->pluck('total', 'result');
        $kpis = [['label' => 'Punches today', 'value' => $today->sum(), 'icon' => 'fa-fingerprint'],
            ['label' => 'Marked today', 'value' => ($today['present'] ?? 0) + ($today['late'] ?? 0), 'icon' => 'fa-user-check', 'note' => 'Present or late'],
            ['label' => 'Unknown today', 'value' => $today['unknown_code'] ?? 0, 'icon' => 'fa-user-slash', 'note' => 'Employee no. not on any profile'],
            ['label' => 'Machines', 'value' => $devices->where('is_active', true)->count(), 'icon' => 'fa-server']];
        $filters = [['name' => 'result', 'label' => 'Result', 'options' => BiometricPunch::RESULTS],
            ['name' => 'device', 'label' => 'Machine', 'options' => $devices->pluck('name', 'id')],
            ['name' => 'from', 'label' => 'From', 'type' => 'date'], ['name' => 'to', 'label' => 'To', 'type' => 'date']];
        $steps = $devices->mapWithKeys(fn ($device) => [$device->id => $driver->setupSteps($device, $request->root())]);
        // Connection check + recent sync log, built from data the server already has (no bridge-script change needed).
        $activity = $devices->mapWithKeys(fn ($device) => [$device->id => BiometricDeviceUser::with('user:id,name')
            ->where('biometric_device_id', $device->id)->whereIn('status', ['synced', 'failed'])
            ->latest('updated_at')->limit(12)->get()]);
        $health = $devices->mapWithKeys(fn ($device) => [$device->id => $this->health($device, $sync[$device->id] ?? collect())]);

        return view('biometric.index', ['devices' => $devices, 'punches' => $list->paginate(25), 'kpis' => $kpis,
            'filters' => $filters, 'steps' => $steps, 'sync' => $sync, 'failed' => $failed, 'activity' => $activity, 'health' => $health]);
    }

    /**
     * Traffic-light connection check for a machine, read from signals the server already records:
     * last_seen_at (machine pushes punches), bridge_seen_at (bridge polls each minute) and the user-sync counts.
     *
     * @param  \Illuminate\Support\Collection<string,int>  $counts
     * @return array{overall:string,checks:array<int,array<string,mixed>>}
     */
    private function health(BiometricDevice $device, $counts): array
    {
        $synced = $counts['synced'] ?? 0;
        $pending = $counts['pending'] ?? 0;
        $failed = $counts['failed'] ?? 0;

        // 1) Machine -> server. Only updates on a punch, so an old time is normal; "never" means not configured.
        $machine = $device->last_seen_at
            ? ['tone' => 'ok', 'label' => 'Last punch '.$device->last_seen_at->diffForHumans(),
                'fix' => 'Updates only when someone punches, so quiet periods are normal.']
            : ['tone' => 'bad', 'label' => 'Never reached the server',
                'fix' => "Enter this machine's push URL on the device (Setup steps on the machine, below)."];

        // 2) Bridge PC -> server. The bridge polls every minute, so this is a reliable heartbeat.
        if (! $device->bridge_seen_at) {
            $bridge = ['tone' => 'bad', 'label' => 'Bridge PC has never contacted the server',
                'fix' => 'Set up the bridge PC (Bridge PC setup, below) and run it once.'];
        } elseif ($device->bridge_seen_at->lt(now()->subMinutes(10))) {
            $bridge = ['tone' => 'warn', 'label' => 'Last ran '.$device->bridge_seen_at->diffForHumans(),
                'fix' => 'The scheduled task may be stopped. Check the bridge PC is on and its task runs every minute.'];
        } else {
            $bridge = ['tone' => 'ok', 'label' => 'Active, last ran '.$device->bridge_seen_at->diffForHumans(), 'fix' => null];
        }

        // 3) Bridge -> machine, proven by the ISAPI user-sync result.
        if ($failed > 0) {
            $isapi = ['tone' => 'bad', 'label' => $failed.' user(s) could not be sent to the machine',
                'fix' => 'The bridge reached the server but not the machine, or the machine rejected them. See the log below.'];
        } elseif ($pending > 0 && $synced === 0) {
            $isapi = ['tone' => 'warn', 'label' => $pending.' user(s) waiting to be sent',
                'fix' => 'The bridge sends them on its next run (every minute).'];
        } elseif ($synced > 0) {
            $isapi = ['tone' => 'ok', 'label' => 'All '.$synced.' user(s) on the machine'.($pending ? ', '.$pending.' queued' : ''), 'fix' => null];
        } else {
            $isapi = ['tone' => 'muted', 'label' => 'No users queued yet', 'fix' => null];
        }

        $rank = ['bad' => 3, 'warn' => 2, 'muted' => 1, 'ok' => 0];
        $overall = collect([$machine, $bridge, $isapi])->sortByDesc(fn ($c) => $rank[$c['tone']])->first()['tone'];

        return ['overall' => $overall, 'checks' => [
            ['title' => 'Machine → Server', 'desc' => 'Machine sends punches'] + $machine,
            ['title' => 'Bridge PC → Server', 'desc' => 'Bridge fetches updates'] + $bridge,
            ['title' => 'Bridge → Machine', 'desc' => 'Users pushed to device'] + $isapi,
        ]];
    }

    public function store(Request $request, MachineUserSync $sync)
    {
        $data = $request->validate(['name' => 'required|string|max:100', 'timezone' => 'required|timezone:all']);
        $device = BiometricDevice::create($data + ['is_active' => true]);
        $queued = $sync->queueAll($device);
        $this->audit($request, 'biometric_device.created', $device, ['name' => $device->name, 'queued_users' => $queued]);

        return redirect()->route('biometric.index')->with('success', "Machine added and {$queued} active students/teachers queued for it. Follow its setup steps below.");
    }

    public function resync(Request $request, BiometricDevice $device, MachineUserSync $sync)
    {
        abort_unless($device->is_active, 422, 'Activate the machine first.');
        // Re-send everyone (for a reset or replaced machine): mark existing rows pending, then add any missing.
        BiometricDeviceUser::where('biometric_device_id', $device->id)->get()->each(function (BiometricDeviceUser $row) {
            $row->forceFill(['status' => 'pending', 'attempts' => 0, 'last_error' => null, 'version' => $row->version + 1])->save();
        });
        $queued = $sync->queueAll($device);
        $this->audit($request, 'biometric_device.resync', $device, ['queued_users' => $queued]);

        return redirect()->route('biometric.index')->with('success', "All users queued again for {$device->name}. The bridge PC sends them on its next run.");
    }

    public function regenerateBridge(Request $request, BiometricDevice $device)
    {
        $device->forceFill(['bridge_token' => BiometricDevice::newToken()])->save();
        $this->audit($request, 'biometric_device.bridge_token_regenerated', $device, []);

        return redirect()->route('biometric.index')->with('success', 'New bridge key created. Download the new config file to the bridge PC; the old one stops working.');
    }

    public function bridgeScript()
    {
        return response()->download(base_path('scripts/hikvision-bridge.ps1'), 'hikvision-bridge.ps1', ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    public function bridgeConfig(Request $request, BiometricDevice $device)
    {
        $config = ['ServerUrl' => $request->root(), 'BridgeToken' => $device->bridge_token, 'DeviceName' => $device->name,
            'DeviceUrl' => 'http://192.168.1.64', 'DeviceUser' => 'admin', 'DevicePassword' => 'CHANGE-ME'];
        $this->audit($request, 'biometric_device.bridge_config_downloaded', $device, []);

        return response()->streamDownload(fn () => print json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'hikvision-bridge-'.$device->id.'.json', ['Content-Type' => 'application/json']);
    }

    public function update(Request $request, BiometricDevice $device)
    {
        $data = $request->validate(['name' => 'required|string|max:100', 'timezone' => 'required|timezone:all', 'is_active' => 'sometimes|boolean']);
        $before = $device->only(['name', 'timezone', 'is_active']);
        $device->update($data + ['is_active' => $request->boolean('is_active')]);
        $this->audit($request, 'biometric_device.updated', $device, ['before' => $before, 'after' => $device->only(['name', 'timezone', 'is_active'])]);

        return redirect()->route('biometric.index')->with('success', 'Machine saved.');
    }

    public function regenerate(Request $request, BiometricDevice $device)
    {
        $device->forceFill(['webhook_token' => BiometricDevice::newToken()])->save();
        $this->audit($request, 'biometric_device.token_regenerated', $device, []);

        return redirect()->route('biometric.index')->with('success', 'New URL created. Update it on the machine; the old URL no longer works.');
    }

    public function teachers(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);
        $tab = in_array($request->query('tab'), ['teachers', 'staff'], true) ? $request->query('tab') : 'team';
        if ($tab !== 'team' && ! $request->query->has('from') && ! $request->query->has('to') && ! $request->query->has('month')) {
            $request->query->set('from', today()->toDateString());
            $request->query->set('to', today()->toDateString());
        }
        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', (string) $request->query('month')) ? $request->query('month') : now()->format('Y-m');
        $dates = $request->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d']);
        $start = Carbon::createFromFormat('Y-m-d', $dates['from'] ?? $month.'-01')->startOfDay();
        $end = isset($dates['to']) ? Carbon::createFromFormat('Y-m-d', $dates['to'])->endOfDay() : $start->copy()->endOfMonth();
        if ($end->lt($start)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['to' => 'To date must be on or after From date.']);
        }
        $from = $start->toDateString();
        $to = $end->toDateString();
        $people = User::whereIn('role', $tab === 'team' ? User::PAID_ROLES : [$tab === 'teachers' ? 'teacher' : 'staff'])
            ->orderBy('name')->get(['id', 'name', 'role', 'designation']);
        $teachers = $people->pluck('name', 'id');
        $roleLabels = $people->mapWithKeys(fn ($person) => [$person->id => ucfirst($person->role)]);
        $roles = $people->mapWithKeys(fn ($person) => [$person->id => $person->isStaff() ? ($person->designation ?: 'Staff') : 'Teacher']);
        $teacherId = $teachers->has((int) $request->query('teacher_id')) ? (int) $request->query('teacher_id') : null;
        $punchQuery = BiometricPunch::whereIn('result', ['staff', 'teacher'])->whereIn('user_id', $people->pluck('id'))
            ->whereBetween('punched_at', [$start, $end])
            ->when($teacherId, fn ($query) => $query->where('user_id', $teacherId));
        $days = (clone $punchQuery)
            ->selectRaw('user_id, DATE(punched_at) as day, MIN(punched_at) as first_in, MAX(punched_at) as last_out, COUNT(*) as punches')
            ->groupBy('user_id', 'day')->orderByDesc('day')->get();
        $withPunches = $days->pluck('user_id')->unique()->count();
        $totalPeople = $teacherId ? 1 : $people->count();
        $kpis = [['label' => 'People', 'value' => $totalPeople, 'icon' => 'fa-users'],
            ['label' => 'With punches', 'value' => $withPunches, 'icon' => 'fa-user-check'],
            ['label' => 'No punches recorded', 'value' => $totalPeople - $withPunches, 'icon' => 'fa-clock', 'note' => 'Not an absence status'],
            ['label' => 'Machine punches', 'value' => $days->sum('punches'), 'icon' => 'fa-fingerprint']];
        $punchDetails = (clone $punchQuery)->with('device:id,name')->orderBy('punched_at')->get()
            ->groupBy(fn ($punch) => $punch->user_id.'|'.$punch->punched_at->toDateString());

        return view('biometric.teachers', compact('days', 'teachers', 'roles', 'roleLabels', 'month', 'teacherId', 'tab', 'from', 'to', 'kpis', 'punchDetails'));
    }

    public function resyncUser(Request $request, User $user, MachineUserSync $sync)
    {
        abort_unless($user->is_active && in_array($user->role, ['student', ...User::PAID_ROLES], true), 422, 'Only active students, teachers and staff can be sent to attendance machines.');
        $sync->resyncUser($user);
        AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'biometric_device_user.resync',
            'subject_type' => 'user', 'subject_id' => $user->id, 'changes' => [], 'created_at' => now()]);

        return back()->with('success', "{$user->name} queued again for the attendance machines. The bridge PC sends them on its next run.");
    }

    private function audit(Request $request, string $action, BiometricDevice $device, array $changes): void
    {
        AuditLog::create(['actor_id' => $request->user()->id, 'action' => $action, 'subject_type' => 'biometric_device',
            'subject_id' => $device->id, 'changes' => $changes, 'created_at' => now()]);
    }
}
