<?php

namespace App\Http\Requests\Workflow;

use Illuminate\Foundation\Http\FormRequest;

class ReviewModernizationTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->status_pengguna === 'Aktif'
            && $user->peranan === 'ketua_upp';
    }

    public function rules(): array
    {
        return [
            'pendahuluan' => [
                'required',
                'string',
                'max:10000',
            ],
            'objektif' => [
                'required',
                'array',
                'min:1',
                'max:20',
            ],
            'objektif.*' => [
                'required',
                'array',
            ],
            'objektif.*.teks' => [
                'required',
                'string',
                'max:2000',
            ],
            'skop_kajian' => [
                'required',
                'array',
                'min:1',
                'max:20',
            ],
            'skop_kajian.*' => [
                'required',
                'array',
            ],
            'skop_kajian.*.teks' => [
                'required',
                'string',
                'max:2000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'pendahuluan.required' => 'Pendahuluan mesti dilengkapkan.',
            'pendahuluan.string' => 'Pendahuluan mesti berupa teks.',
            'pendahuluan.max' => 'Pendahuluan tidak boleh melebihi 10000 aksara.',

            'objektif.required' => 'Sekurang-kurangnya satu objektif diperlukan.',
            'objektif.array' => 'Format objektif tidak sah.',
            'objektif.min' => 'Sekurang-kurangnya satu objektif diperlukan.',
            'objektif.max' => 'Objektif tidak boleh melebihi 20 catatan.',
            'objektif.*.teks.required' => 'Setiap objektif mesti mempunyai catatan.',
            'objektif.*.teks.string' => 'Catatan objektif mesti berupa teks.',
            'objektif.*.teks.max' => 'Setiap objektif tidak boleh melebihi 2000 aksara.',

            'skop_kajian.required' => 'Sekurang-kurangnya satu skop kajian diperlukan.',
            'skop_kajian.array' => 'Format skop kajian tidak sah.',
            'skop_kajian.min' => 'Sekurang-kurangnya satu skop kajian diperlukan.',
            'skop_kajian.max' => 'Skop kajian tidak boleh melebihi 20 catatan.',
            'skop_kajian.*.teks.required' => 'Setiap skop kajian mesti mempunyai catatan.',
            'skop_kajian.*.teks.string' => 'Catatan skop kajian mesti berupa teks.',
            'skop_kajian.*.teks.max' => 'Setiap skop kajian tidak boleh melebihi 2000 aksara.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'pendahuluan' => trim((string) $this->input('pendahuluan')),
            'objektif' => $this->cleanTextRows(
                $this->input('objektif', [])
            ),
            'skop_kajian' => $this->cleanTextRows(
                $this->input('skop_kajian', [])
            ),
        ]);
    }

    private function cleanTextRows(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        return array_values(
            array_filter(
                array_map(
                    fn (mixed $row): array => [
                        'teks' => trim(
                            (string) (
                                is_array($row)
                                    ? ($row['teks'] ?? '')
                                    : ''
                            )
                        ),
                    ],
                    $rows
                ),
                fn (array $row): bool => $row['teks'] !== ''
            )
        );
    }
}
