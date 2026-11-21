<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Support\CertificateParagraphs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CertificateParagraphController extends Controller
{
    public function edit(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        return view('certificates.paragraphs', ['paragraphs' => CertificateParagraphs::all()]);
    }

    public function update(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);
        $levels = array_keys(config('certificates.paragraphs'));
        $data = $request->validate([
            'paragraphs' => ['required', 'array:'.implode(',', $levels)],
            ...array_fill_keys(array_map(fn ($level) => 'paragraphs.'.$level, $levels),
                ['required', 'string', 'max:350', 'not_regex:/[<>]/u']),
        ]);
        foreach ($data['paragraphs'] as $level => &$text) {
            $text = preg_replace('/\s+/u', ' ', trim($text));
            if (! CertificateParagraphs::fits($level, $text)) {
                throw ValidationException::withMessages(['paragraphs.'.$level => 'Please shorten this paragraph so it fits above the certificate signature.']);
            }
        }
        unset($text);
        DB::transaction(function () use ($data, $request) {
            $before = CertificateParagraphs::all();
            foreach ($data['paragraphs'] as $level => $text) {
                Setting::updateOrCreate(['key' => CertificateParagraphs::key($level)], [
                    'value' => $text, 'type' => 'textarea', 'group' => 'certificates',
                    'label' => $level.' certificate paragraph', 'is_public' => false,
                ]);
            }
            AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'certificate.paragraphs_updated',
                'subject_type' => 'settings', 'subject_id' => 0,
                'changes' => ['before' => $before, 'after' => $data['paragraphs']]]);
        });

        return back()->with('success', 'Level paragraphs saved. New certificates will use this wording.');
    }
}
