<?php

namespace App\Http\Middleware;

use App\Models\Tiket;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    // The root template that is loaded on the first page visit
    protected $rootView = 'app';

    // Determine the current asset version
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    // Define the props that are shared by default
    public function share(Request $request): array
    {
        $user = $request->user();

        $notiCounts = [
            'penyelenggaraan_komputer' => 0,
            'penyelenggaraan_rangkaian' => 0,
            'sistem_aplikasi' => 0,
            'perkhidmatan_emel' => 0,
            'perkhidmatan_lintas_langsung' => 0,
            'peminjaman_ict' => 0,
            'pemasangan_baharu' => 0,
            'naiktaraf' => 0,
            'pemodenan_bilik_mesyuarat' => 0,
            'pembekalan_peralatan_ict' => 0,
        ];

        $petaSubKategori = [
            'Meja Bantuan' => [
                'Penyelenggaraan Komputer' => 'penyelenggaraan_komputer',
                'Penyelenggaraan Rangkaian' => 'penyelenggaraan_rangkaian',
                'Sistem Aplikasi' => 'sistem_aplikasi',
                'Perkhidmatan E-mel' => 'perkhidmatan_emel',
                'Perkhidmatan Lintas Langsung' => 'perkhidmatan_lintas_langsung',
                'Peminjaman Peralatan ICT' => 'peminjaman_ict',
            ],
            'Konsultasi Rangkaian' => [
                'Pemasangan Baharu' => 'pemasangan_baharu',
                'Naiktaraf' => 'naiktaraf',
            ],
            'Transformasi Digital' => [
                'Pemodenan Bilik Mesyuarat' => 'pemodenan_bilik_mesyuarat',
                'Pembekalan Peralatan ICT' => 'pembekalan_peralatan_ict',
            ],
        ];

        if ($user) {
            $perananAktif = strtolower(trim($user->peranan));

            $isKUPP = in_array($perananAktif, ['ketua_upp', 'kupp', 'ketua upp']);
            $isKUTD = in_array($perananAktif, ['ketua_utd', 'kutd', 'ketua utd']);
            $isKW = in_array($perananAktif, ['ketua_wilayah', 'kw', 'ketua wilayah']);
            $isJuruteknik = in_array($perananAktif, ['juruteknik', 'pic']);

            foreach ($petaSubKategori as $kategoriUtama => $senaraiSub) {

                $relasiJadualAnak = match ($kategoriUtama) {
                    'Meja Bantuan' => 'mejaBantuan',
                    'Konsultasi Rangkaian' => 'konsultasiRangkaian',
                    'Transformasi Digital' => 'transformasiDigital',
                };

                foreach ($senaraiSub as $namaSubDalamDb => $keySidebar) {
                    $notiCounts[$keySidebar] = Tiket::where('kategori', $kategoriUtama)
                        ->whereHas($relasiJadualAnak, function ($query) use ($namaSubDalamDb) {
                            $query->where('sub_kategori', $namaSubDalamDb);
                        })
                        ->when($isKUPP, function ($query) {
                            $query->whereIn('status_tiket', [
                                'Menunggu Klasifikasi',
                                'Menunggu Semakan Dokumen',
                                'Tugasan UPP',
                                'Menunggu Pengesahan',
                                'Menunggu Kelulusan',
                                'Menunggu Semakan Laporan',
                                'Pembetulan Laporan',
                                'Pembetulan Ketua',
                            ]);
                        })
                        ->when($isKUTD, function ($query) {
                            $query->whereIn('status_tiket', [
                                'Menunggu Klasifikasi',
                                'Menunggu Semakan Dokumen',
                                'Tugasan UTD',
                                'Menunggu Semakan',
                                'Menunggu Pengesahan',
                                'Menunggu Kelulusan',
                                'Menunggu Semakan Laporan',
                                'Pembetulan Laporan',
                                'Sedia Diverifikasi',
                                'Pembetulan Ketua',
                            ]);
                        })
                        ->when($isKW, function ($query) {
                            $query->whereIn('status_tiket', [
                                'Menunggu Klasifikasi',
                                'Menunggu Validasi',
                                'Menunggu Kelulusan',
                            ]);
                        })
                        ->when($isJuruteknik, function ($query) use ($user) {
                            $query->whereIn('status_tiket', [
                                'Dalam Tindakan Pegawai',
                                'LKK Perlu Pembetulan',
                                'Dalam Tindakan',
                                'Laporan Perlu Pembetulan',
                            ])
                                ->whereHas('petugas', function ($subQuery) use ($user) {
                                    $subQuery->where('tugasan_tiket.no_ic', $user->no_ic);
                                });
                        })
                        ->count();
                }
            }
        }

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user,
                'unreadNotificationsCount' => $user ? $user->unreadNotifications()->count() : 0,
                'notifications' => $user ? $user->unreadNotifications : [],
            ],
            'noti_counts' => $notiCounts,
        ]);
    }
}
