<?php

namespace App\Http\Requests;

use App\Models\CronJob;
use App\Rules\AllowedCronCommand;
use App\Rules\ValidCronExpression;
use Illuminate\Foundation\Http\FormRequest;

class StoreCronJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'schedule' => ['required', 'string', 'max:100', new ValidCronExpression],
            'command' => ['required', 'string', 'max:500', new AllowedCronCommand($this->user())],
            'label' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $user = $this->user();
            if (! $user) {
                return;
            }

            $count = CronJob::where('user_id', $user->id)->count();
            if ($count >= 50) {
                $validator->errors()->add('command', 'You have reached the maximum of 50 cron jobs.');

                return;
            }

            // Surface a meaningful error for the UNIQUE(user_id, schedule, command)
            // constraint instead of letting it surface as a 500 from the DB layer.
            $schedule = $this->input('schedule');
            $command = $this->input('command');
            if ($schedule && $command && CronJob::where('user_id', $user->id)
                ->where('schedule', $schedule)
                ->where('command', $command)
                ->exists()) {
                $validator->errors()->add('command', 'You already have a cron job with this schedule and command.');
            }
        });
    }
}
