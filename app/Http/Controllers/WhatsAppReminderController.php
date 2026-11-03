<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Enrollment;
use App\Models\Setting;
use App\Models\WhatsappMessage;
use App\Services\FeeReminderService;
use App\Services\WhatsAppService;
use App\Support\ListQuery;
use Illuminate\Http\Request;

/** Admin: WhatsApp reminder settings, message log, test message and manual reminders. */
class WhatsAppReminderController extends Controller
{
    public function index(Request $request, FeeReminderService $reminders, WhatsAppService $whatsApp)
    {
        $statuses = ['sent' => 'Sent', 'failed' => 'Failed', 'sending' => 'Sending'];
        $list = ListQuery::for(WhatsappMessage::with(['student:id,name', 'enrollment.batch:id,name', 'sender:id,name']), $request)
            ->search(['phone', 'student.name'])
            ->filter('status', fn ($query, $status) => $query->where('status', $status), array_keys($statuses))
            ->filter('kind', fn ($query, $kind) => $query->where('kind', $kind), array_keys(WhatsappMessage::KINDS))
            ->dateRange('created_at')
            ->sorts(['time' => 'created_at'], '-time');
        if ($list->wantsCsv()) {
            return $list->csv('whatsapp-reminders', ['Time' => fn ($row) => $row->created_at->format('Y-m-d H:i'),
                'Student' => fn ($row) => $row->student?->name, 'Phone' => fn ($row) => $row->phone, 'Type' => fn ($row) => $row->kindLabel(),
                'Amount' => fn ($row) => $row->params[1] ?? null, 'Due date' => fn ($row) => $row->params[2] ?? null,
                'Status' => fn ($row) => $row->status, 'Error' => fn ($row) => $row->error]);
        }
        $today = WhatsappMessage::whereDate('created_at', today())->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $kpis = [['label' => 'Sent today', 'value' => $today['sent'] ?? 0, 'icon' => 'fa-paper-plane'],
            ['label' => 'Failed today', 'value' => $today['failed'] ?? 0, 'icon' => 'fa-triangle-exclamation'],
            ['label' => 'Automatic', 'value' => $reminders->settings()['enabled'] ? 'On' : 'Off', 'icon' => 'fa-clock', 'note' => 'Daily at 10:00'],
            ['label' => 'API', 'value' => $whatsApp->configured() ? 'Connected' : 'Not set', 'icon' => 'fa-plug', 'note' => 'WHATSAPP_* in .env']];
        $filters = [['name' => 'status', 'label' => 'Status', 'options' => $statuses], ['name' => 'kind', 'label' => 'Type', 'options' => WhatsappMessage::KINDS],
            ['name' => 'from', 'label' => 'From', 'type' => 'date'], ['name' => 'to', 'label' => 'To', 'type' => 'date']];

        return view('whatsapp.index', ['messages' => $list->paginate(25), 'kpis' => $kpis, 'filters' => $filters,
            'settings' => $reminders->settings(), 'configured' => $whatsApp->configured()]);
    }

    public function updateSettings(Request $request, FeeReminderService $reminders)
    {
        $data = $request->validate(['enabled' => 'sometimes|boolean', 'days_before' => 'required|integer|min:0|max:30',
            'on_due_date' => 'sometimes|boolean', 'template' => ['required', 'regex:/^[a-z0-9_]{1,100}$/D'],
            'language' => ['required', 'regex:/^[a-z]{2,3}(_[A-Z]{2})?$/D']], ['template.regex' => 'Use the exact template name from Meta (lowercase letters, digits, underscores).']);
        $before = $reminders->settings();
        $values = ['whatsapp_reminders_enabled' => $request->boolean('enabled') ? '1' : '0', 'whatsapp_days_before' => (string) $data['days_before'],
            'whatsapp_send_on_due_date' => $request->boolean('on_due_date') ? '1' : '0', 'whatsapp_template_name' => $data['template'],
            'whatsapp_template_language' => $data['language']];
        foreach ($values as $key => $value) {
            Setting::where('key', $key)->update(['value' => $value]);
        }
        AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'whatsapp.settings_updated', 'subject_type' => 'setting',
            'subject_id' => Setting::where('key', 'whatsapp_reminders_enabled')->value('id') ?? 0, 'changes' => ['before' => $before, 'after' => $reminders->settings()], 'created_at' => now()]);

        return redirect()->route('whatsapp.index')->with('success', 'WhatsApp reminder settings saved.');
    }

    public function test(Request $request, FeeReminderService $reminders)
    {
        $data = $request->validate(['phone' => 'required|string|max:20']);
        $message = $reminders->sendTest($data['phone'], $request->user());

        return redirect()->route('whatsapp.index')->with($message->status === 'sent' ? 'success' : 'error',
            $message->status === 'sent' ? 'Test message sent. Check the phone.' : 'Test failed: '.$message->error);
    }

    public function sendForEnrollment(Request $request, Enrollment $enrollment, FeeReminderService $reminders)
    {
        $message = $reminders->sendManual($enrollment, $request->user());

        return back()->with($message->status === 'sent' ? 'success' : 'error', $message->status === 'sent'
            ? 'WhatsApp reminder sent to '.$message->student?->name.'.'
            : 'WhatsApp reminder failed: '.$message->error);
    }
}
