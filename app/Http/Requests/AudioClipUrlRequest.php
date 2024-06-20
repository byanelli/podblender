<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AudioClipUrlRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'url' => 'required|string|url:http,https|max:255',
        ];
    }

    public function getUrl(): string
    {
        return $this->post('url');
    }
}
