<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportClientsRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'csv_file' => 'required|file|mimes:csv,txt|max:10240', // 10MB max
        ];
    }

    public function messages()
    {
        return [
            'csv_file.required' => 'Please select a CSV file to upload.',
            'csv_file.file' => 'The uploaded file is not valid.',
            'csv_file.mimes' => 'The file must be a CSV file.',
            'csv_file.max' => 'The file may not be greater than 10MB.',
        ];
    }
}