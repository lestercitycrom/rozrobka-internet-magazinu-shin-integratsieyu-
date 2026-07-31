<?php

namespace App\Http\Requests\Admin;

use App\Modules\Catalog\Enums\PublicationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductPublicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string|object>> */
    public function rules(): array
    {
        return [
            'publication_status' => ['required', Rule::enum(PublicationStatus::class)],
        ];
    }
}
