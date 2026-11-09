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

        return view('biometric.index', ['devices' => $devices, 'punches' => $list->paginate(25), 'kpis' => $kpis,
            'filters' => $filters, 'steps' => $steps, 'sync' => $sync, 'failed' => $failed]);
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
        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', (string) $request->query('month')) ? $request->query('month') : now()->format('Y-m');
        $start = Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfDay();
        $people = User::where('role', 'staff')->orderBy('name')->get(['id', 'name', 'role', 'designation']);
        $teachers = $people->pluck('name', 'id');
        $roles = $people->mapWithKeys(fn ($person) => [$person->id => $person->isStaff() ? ($person->designation ?: 'Staff') : 'Teacher']);
        $teacherId = $teachers->has((int) $request->query('teacher_id')) ? (int) $request->query('teacher_id') : null;
        $days = BiometricPunch::where('result', 'staff')->whereIn('user_id', $people->pluck('id'))
            ->whereBetween('punched_at', [$start, $start->copy()->endOfMonth()])
            ->when($teacherId, fn ($query) => $query->where('user_id', $teacherId))
            ->selectRaw('user_id, DATE(punched_at) as day, MIN(punched_at) as first_in, MAX(punched_at) as last_out, COUNT(*) as punches')
            ->groupBy('user_id', 'day')->orderByDesc('day')->get();

        return view('biometric.teachers', ['days' => $days, 'teachers' => $teachers, 'roles' => $roles, 'month' => $month, 'teacherId' => $teacherId]);
    }

    public function resyncUser(Request $request, User $user, MachineUserSync $sync)
    {
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
