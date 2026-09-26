<?php

namespace App\Domains\Template\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class UploadTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // guarded by `can:templates.manage` on the route
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'package' => ['required', 'file', 'mimes:zip', 'max:20480'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'package.mimes' => 'Upload the template as a .zip file.',
        ];
    }

    public function package(): UploadedFile
    {
        /** @var UploadedFile */
        return $this->file('package');
    }
}
