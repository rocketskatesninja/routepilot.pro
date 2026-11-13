<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\EmailHtml;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class SendCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['super_admin', 'tenant_admin'], true);
    }

    /** Files an email can carry: up to 5, 8 MB each (the servers' upload limit), 10 MB together. */
    public const MAX_FILES = 5;

    public const MAX_FILE_KB = 8192;

    public const MAX_TOTAL_KB = 10240;

    public const FILE_TYPES = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt'];

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'body.required' => 'Write a message.',
            'attachments.max' => 'Attach up to 5 files.',
            'attachments.*.mimes' => 'Attach a PDF, a picture (JPG, PNG, GIF, WebP), a Word or Excel file, a CSV or a text file.',
            'attachments.*.max' => 'Each file can be up to 8 MB.',
        ];
    }

    /** The message is rich text: only the allowed tags and links get past here. */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('body'))) {
            $this->merge(['body' => EmailHtml::clean($this->input('body'))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        // Only a super-admin may target other tenants; a tenant admin is
        // limited to their own customers + agents.
        $allowed = $this->user()?->isSuperAdmin() === true
            ? ['selected', 'tenants', 'agents', 'customers']
            : ['selected', 'customers', 'agents'];

        return [
            'audience' => ['required', Rule::in($allowed)],
            'recipients' => ['required_if:audience,selected', 'array', 'max:2000'],
            'recipients.*' => ['string', 'regex:/^(customer|agent|tenant):\d+$/'],
            'subject' => ['required', 'string', 'max:255'],
            // Sign off with the company's signature (tenant senders; RoutePilot's own broadcasts have none).
            'signature' => ['boolean'],
            // Files sent with it: checked by their content, not just their name.
            'attachments' => ['nullable', 'array', 'max:'.self::MAX_FILES, function (string $attribute, mixed $value, Closure $fail): void {
                $total = array_sum(array_map(fn ($f) => $f instanceof UploadedFile ? (int) $f->getSize() : 0, is_array($value) ? $value : []));
                if ($total > self::MAX_TOTAL_KB * 1024) {
                    $fail('The files come to more than 10 MB together. Leave one out, or send it in a second email.');
                }
            }],
            'attachments.*' => ['file', 'mimes:'.implode(',', self::FILE_TYPES), 'max:'.self::MAX_FILE_KB],
            'body' => ['required', 'string', 'max:'.EmailHtml::MAX_LENGTH, function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && ! EmailHtml::hasText($value)) {
                    $fail('Write a message.');
                }
            }],
        ];
    }
}
