<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\Lead;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StudentRegistrationService
{
    public const BASIC_FIELDS = ['name', 'email', 'phone', 'address', 'father_name', 'cnic', 'date_of_birth', 'emergency_contact'];

    public static function rules(bool $existing = false, mixed $studentId = null): array
    {
        $rules = [
            'student_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', 'student')->where('is_active', true)],
            'name' => ($existing ? 'exclude' : 'required').'|string|max:255',
            'email' => ($existing ? 'exclude' : 'required').'|string|email|max:255|unique:users,email',
            'father_name' => 'nullable|string|max:255',
            'cnic' => ['nullable', 'string', 'max:20', 'regex:/^[0-9\-]{13,20}$/'],
            'phone' => ($existing ? 'exclude' : 'required').'|string|max:20|regex:/^\+?[0-9]+$/D',
            'address' => ($existing ? 'exclude' : 'required').'|string|max:500',
            'date_of_birth' => 'nullable|date|before:today',
            'emergency_contact' => 'nullable|string|max:20|regex:/^\+?[0-9]+$/D',
            'password' => 'nullable|required_with:password_confirmation|string|min:8|confirmed',
            'is_active' => 'sometimes|boolean',
            'batch_id' => ($existing ? 'required' : 'nullable').'|exists:batches,id',
            'discount_amount' => 'nullable|decimal:0,2|min:0|max:99999999.99',
            'discount_percentage' => 'prohibited',
            'installments' => 'nullable|integer|min:1|max:12',
            'schedule' => 'nullable|array|min:1|max:12',
            'schedule.*.amount' => 'required|decimal:0,2|min:0|max:99999999.99',
            'schedule.*.due_date' => 'required|date_format:Y-m-d',
        ];
        if ($existing) {
            $student = is_scalar($studentId) ? User::students()->where('is_active', true)->whereKey($studentId)->first() : null;
            foreach (self::BASIC_FIELDS as $field) {
                $rules[$field] = $student && blank($student->$field)
                    ? (is_string($rules[$field]) ? str_replace('exclude|', 'nullable|', $rules[$field]) : $rules[$field])
                    : 'exclude';
            }
            $rules['password'] = $rules['is_active'] = 'exclude';
        }

        return $rules;
    }

    public static function availableBatches()
    {
        return Batch::with('course')->whereIn('status', ['upcoming', 'ongoing'])
            ->whereHas('course', fn ($query) => $query->where('is_active', true))->orderBy('name')->get();
    }

    public static function canConvert(Lead $lead): bool
    {
        return ! $lead->student_id && in_array($lead->status, [Lead::STATUS_QUALIFIED, Lead::STATUS_SUBMITTED], true);
    }

    public function register(array $data, ?Lead $lead = null): User
    {
        return DB::transaction(function () use ($data, $lead) {
            if (! empty($data['batch_id']) && isset($data['is_active']) && ! $data['is_active']) {
                throw ValidationException::withMessages(['is_active' => 'Activate the student account before admitting them to a batch.']);
            }
            if ($lead) {
                $lead = Lead::whereKey($lead->id)->lockForUpdate()->firstOrFail();
                if (! self::canConvert($lead)) {
                    throw ValidationException::withMessages(['lead' => 'This lead is already converted or closed.']);
                }
            }
            $student = ! empty($data['student_id'])
                ? User::students()->where('is_active', true)->lockForUpdate()->findOrFail($data['student_id'])
                : User::create([
                    'name' => $data['name'], 'email' => $data['email'], 'role' => 'student',
                    'father_name' => $data['father_name'] ?? null, 'cnic' => $data['cnic'] ?? null,
                    'phone' => $data['phone'], 'address' => $data['address'],
                    'date_of_birth' => $data['date_of_birth'] ?? null,
                    'emergency_contact' => $data['emergency_contact'] ?? null,
                    'is_active' => $data['is_active'] ?? true,
                    'password' => Hash::make(filled($data['password'] ?? null) ? $data['password'] : Str::random(64)),
                ]);
            if (! empty($data['student_id'])) {
                foreach (self::BASIC_FIELDS as $field) {
                    if (blank($student->$field) && filled($data[$field] ?? null)) {
                        $student->$field = $data[$field];
                    }
                }
                if ($student->isDirty()) {
                    $student->save();
                }
            }
            if (! empty($data['batch_id'])) {
                try {
                    $enrollment = app(EnrollmentService::class)->enrollStudent($student->id, $data['batch_id'], 0, $data['installments'] ?? 1,
                        $data['schedule'] ?? null, Money::minor((string) ($data['discount_amount'] ?? '0')));
                    app(EnrollmentService::class)->notifyEnrollment($enrollment);
                } catch (\InvalidArgumentException $exception) {
                    $field = str_contains($exception->getMessage(), 'Discount amount') ? 'discount_amount' : 'batch_id';
                    throw ValidationException::withMessages([$field => $exception->getMessage()]);
                }
            }
            if ($lead) {
                $lead->update(['status' => Lead::STATUS_CONVERTED, 'student_id' => $student->id, 'converted_at' => now()]);
            }

            return $student;
        });
    }
}
