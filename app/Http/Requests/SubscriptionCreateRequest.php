<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubscriptionCreateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'url' => 'required|string|url:http,https|max:255',
            'name' => 'required|string|max:255',
        ];
    }

    public function getUrl(): string {
        return $this->string('url');
    }

    public function getFeedName(): string {
        return $this->string('name');

    }
}
