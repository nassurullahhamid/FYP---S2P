<?php

namespace App\Http\Requests\Workflow;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClassifyTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->status_pengguna === 'Aktif'
            && in_array(
                $user->peranan,
                config('s2p_workflow.classification_roles', []),
                true
            );
    }

    public function rules(): array
    {
        $categories = config('s2p_workflow.categories', []);

        $validSubcategories = $categories[
            $this->input('kategori')
        ] ?? [];

        return [
            'kategori' => [
                'required',
                'string',
                Rule::in(array_keys($categories)),
            ],
            'sub_kategori' => [
                'required',
                'string',
                Rule::in($validSubcategories),
            ],
            'tahap_keutamaan' => [
                'required',
                'string',
                Rule::in(
                    array_keys(
                        config('s2p_workflow.sla_days', [])
                    )
                ),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'kategori.required' => 'Kategori tiket mesti dipilih.',
            'kategori.in' => 'Kategori tiket yang dipilih tidak sah.',
            'sub_kategori.required' => 'Subkategori tiket mesti dipilih.',
            'sub_kategori.in' => 'Subkategori tidak sepadan dengan kategori.',
            'tahap_keutamaan.required' => 'Tahap keutamaan mesti dipilih.',
            'tahap_keutamaan.in' => 'Tahap keutamaan tidak sah.',
        ];
    }
}
