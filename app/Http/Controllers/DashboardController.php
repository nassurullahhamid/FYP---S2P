<?php

namespace App\Http\Controllers;

use App\Models\Tiket;
use App\Models\Pengguna;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class DashboardController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $user = Auth::user();

        if ($user->peranan === 'juruteknik') {
            $userIC = $user->no_ic;

            $statusBelumTindakan = ['Tugasan UTD', 'Tugasan UPP'];
            $statusDalamTindakan = ['Dalam Tindakan Pegawai', 'LKK Perlu Pembetulan'];

            $statsJuruteknik = [
                'total_tickets' => DB::table('tugasan_tiket')->where('no_ic', $userIC)->count(),

                'proses' => DB::table('tiket')
                    ->join('tugasan_tiket', 'tiket.id_tiket', '=', 'tugasan_tiket.id_tiket')
                    ->where('tugasan_tiket.no_ic', $userIC)
                    ->whereIn('tiket.status_tiket', $statusDalamTindakan)
                    ->count(),

                'selesai' => DB::table('tiket')
                    ->join('tugasan_tiket', 'tiket.id_tiket', '=', 'tugasan_tiket.id_tiket')
                    ->where('tugasan_tiket.no_ic', $userIC)
                    ->where('tiket.status_tiket', 'Selesai')
                    ->count(),

                'kpiModul' => [
                    'Meja Bantuan' => [
                        'belum_tindakan' => DB::table('tiket')->join('tugasan_tiket', 'tiket.id_tiket', '=', 'tugasan_tiket.id_tiket')->where('tugasan_tiket.no_ic', $userIC)->where('tiket.kategori', 'Meja Bantuan')->whereIn('tiket.status_tiket', $statusBelumTindakan)->count(),
                        'dalam_tindakan' => DB::table('tiket')->join('tugasan_tiket', 'tiket.id_tiket', '=', 'tugasan_tiket.id_tiket')->where('tugasan_tiket.no_ic', $userIC)->where('tiket.kategori', 'Meja Bantuan')->whereIn('tiket.status_tiket', $statusDalamTindakan)->count(),
                    ],
                    'Konsultasi Rangkaian' => [
                        'belum_tindakan' => DB::table('tiket')->join('tugasan_tiket', 'tiket.id_tiket', '=', 'tugasan_tiket.id_tiket')->where('tugasan_tiket.no_ic', $userIC)->where('tiket.kategori', 'Konsultasi Rangkaian')->whereIn('tiket.status_tiket', $statusBelumTindakan)->count(),
                        'dalam_tindakan' => DB::table('tiket')->join('tugasan_tiket', 'tiket.id_tiket', '=', 'tugasan_tiket.id_tiket')->where('tugasan_tiket.no_ic', $userIC)->where('tiket.kategori', 'Konsultasi Rangkaian')->whereIn('tiket.status_tiket', $statusDalamTindakan)->count(),
                    ],
                    'Transformasi Digital' => [
                        'belum_tindakan' => DB::table('tiket')->join('tugasan_tiket', 'tiket.id_tiket', '=', 'tugasan_tiket.id_tiket')->where('tugasan_tiket.no_ic', $userIC)->where('tiket.kategori', 'Transformasi Digital')->whereIn('tiket.status_tiket', $statusBelumTindakan)->count(),
                        'dalam_tindakan' => DB::table('tiket')->join('tugasan_tiket', 'tiket.id_tiket', '=', 'tugasan_tiket.id_tiket')->where('tugasan_tiket.no_ic', $userIC)->where('tiket.kategori', 'Transformasi Digital')->whereIn('tiket.status_tiket', $statusDalamTindakan)->count(),
                    ],
                ]
            ];

            $recentTicketsJuruteknik = class_exists(Tiket::class)
                ? Tiket::whereHas('petugas', function($query) use ($userIC) {
                    $query->where('tugasan_tiket.no_ic', $userIC);
                })->whereIn('status_tiket', $statusDalamTindakan)->latest()->take(5)->get()
                : [];

            return Inertia::render('Dashboard/JuruteknikDashboard', [
                'stats'         => $statsJuruteknik,
                'recentTickets' => $recentTicketsJuruteknik
            ]);
        }

        if (in_array($user->peranan, ['ketua_upp', 'ketua_utd'])) {
            $statusBelumTindakan = ['Menunggu Klasifikasi', 'Menunggu Semakan Dokumen', 'Tugasan UTD', 'Tugasan UPP', 'Menunggu Pengesahan',  'Menunggu Semakan','Menunggu Kelulusan'];
            $statusDalamTindakan = ['Dalam Tindakan Pegawai', 'LKK Perlu Pembetulan', 'Menunggu Validasi'];

            $statsWorkflow = [
                'kpiUtama' => [
                    'belum_tindakan' => Tiket::whereIn('status_tiket', $statusBelumTindakan)->count(),
                    'dalam_tindakan' => Tiket::whereIn('status_tiket', $statusDalamTindakan)->count(),
                    'selesai'        => Tiket::where('status_tiket', 'Selesai')->count(),
                ],
                'kpiModul' => [
                    'Meja Bantuan' => [
                        'belum_tindakan' => Tiket::where('kategori', 'Meja Bantuan')->whereIn('status_tiket', $statusBelumTindakan)->count(),
                        'dalam_tindakan' => Tiket::where('kategori', 'Meja Bantuan')->whereIn('status_tiket', $statusDalamTindakan)->count(),
                    ],
                    'Konsultasi Rangkaian' => [
                        'belum_tindakan' => Tiket::where('kategori', 'Konsultasi Rangkaian')->whereIn('status_tiket', $statusBelumTindakan)->count(),
                        'dalam_tindakan' => Tiket::where('kategori', 'Konsultasi Rangkaian')->whereIn('status_tiket', $statusDalamTindakan)->count(),
                    ],
                    'Transformasi Digital' => [
                        'belum_tindakan' => Tiket::where('kategori', 'Transformasi Digital')->whereIn('status_tiket', $statusBelumTindakan)->count(),
                        'dalam_tindakan' => Tiket::where('kategori', 'Transformasi Digital')->whereIn('status_tiket', $statusDalamTindakan)->count(),
                    ],
                ]
            ];

            $view = ($user->peranan === 'ketua_upp') ? 'Dashboard/KetuaUPPDashboard' : 'Dashboard/KetuaUTDDashboard';

            return Inertia::render($view, [
                'stats' => $statsWorkflow,
                'recentTickets' => Tiket::latest()->take(5)->get()
            ]);
        }

        if ($user->peranan === 'ketua_wilayah') {
            $statusBelumTindakanKW = ['Menunggu Klasifikasi', 'Tugasan UTD', 'Menunggu Semakan Dokumen', 'Tugasan UPP', 'Menunggu Pengesahan',  'Menunggu Semakan', 'Menunggu Kelulusan'];
            $statusDalamTindakanKW = ['Dalam Tindakan Pegawai', 'LKK Perlu Pembetulan'];

            $kpiUmum = [
                'menunggu_validasi' => Tiket::where('status_tiket', 'Menunggu Validasi')->count(),
                'jumlah_tiket'      => Tiket::count(),
                'belum_tindakan'    => Tiket::whereIn('status_tiket', $statusBelumTindakanKW)->count(),
                'dalam_tindakan'    => Tiket::whereIn('status_tiket', $statusDalamTindakanKW)->count(),
                'tiket_selesai'     => Tiket::where('status_tiket', 'Selesai')->count(),
            ];

            $kategoriList = ['Meja Bantuan', 'Konsultasi Rangkaian', 'Transformasi Digital'];
            $statsKategori = [];

            foreach ($kategoriList as $kat) {
                $statsKategori[$kat] = [
                    'total'          => Tiket::where('kategori', $kat)->count(),
                    'belum_tindakan' => Tiket::where('kategori', $kat)->whereIn('status_tiket', $statusBelumTindakanKW)->count(),
                    'dalam_tindakan' => Tiket::where('kategori', $kat)->whereIn('status_tiket', $statusDalamTindakanKW)->count(),
                ];
            }

            return Inertia::render('Dashboard/KetuaWilayahDashboard', [
                'kpiUmum'       => $kpiUmum,
                'statsKategori' => $statsKategori,
                'recentTickets' => Tiket::latest()->take(5)->get()
            ]);
        }

        if ($user->peranan === 'admin') {
            $statusBaruAdmin = ['Menunggu Klasifikasi', 'Tugasan UTD', 'Tugasan UPP', 'Menunggu Semakan Dokumen'];
            $statusProsesAdmin = [
                'Dalam Tindakan Pegawai',
                'Menunggu Pengesahan',
                'Menunggu Semakan',
                'Menunggu Validasi',
                'Menunggu Kelulusan',
                'LKK Perlu Pembetulan'
            ];

            $statsSistem = [
                'total_tickets' => class_exists(Tiket::class) ? Tiket::count() : 0,
                'proses'        => class_exists(Tiket::class) ? Tiket::whereNot('status_tiket', 'Selesai')->count() : 0,
                'selesai'       => class_exists(Tiket::class) ? Tiket::where('status_tiket', 'Selesai')->count() : 0,
                'total_users'   => class_exists(Pengguna::class) ? Pengguna::count() : 0,

                'mb_total'   => class_exists(Tiket::class) ? Tiket::where('kategori', 'Meja Bantuan')->count() : 0,
                'mb_baru'    => class_exists(Tiket::class) ? Tiket::where('kategori', 'Meja Bantuan')->whereIn('status_tiket', $statusBaruAdmin)->count() : 0,
                'mb_proses'  => class_exists(Tiket::class) ? Tiket::where('kategori', 'Meja Bantuan')->whereIn('status_tiket', $statusProsesAdmin)->count() : 0,
                'mb_selesai' => class_exists(Tiket::class) ? Tiket::where('kategori', 'Meja Bantuan')->where('status_tiket', 'Selesai')->count() : 0,

                'kr_total'   => class_exists(Tiket::class) ? Tiket::where('kategori', 'Konsultasi Rangkaian')->count() : 0,
                'kr_baru'    => class_exists(Tiket::class) ? Tiket::where('kategori', 'Konsultasi Rangkaian')->whereIn('status_tiket', $statusBaruAdmin)->count() : 0,
                'kr_proses'  => class_exists(Tiket::class) ? Tiket::where('kategori', 'Konsultasi Rangkaian')->whereIn('status_tiket', $statusProsesAdmin)->count() : 0,
                'kr_selesai' => class_exists(Tiket::class) ? Tiket::where('kategori', 'Konsultasi Rangkaian')->where('status_tiket', 'Selesai')->count() : 0,

                'td_total'   => class_exists(Tiket::class) ? Tiket::where('kategori', 'Transformasi Digital')->count() : 0,
                'td_baru'    => class_exists(Tiket::class) ? Tiket::where('kategori', 'Transformasi Digital')->whereIn('status_tiket', $statusBaruAdmin)->count() : 0,
                'td_proses'  => class_exists(Tiket::class) ? Tiket::where('kategori', 'Transformasi Digital')->whereIn('status_tiket', $statusProsesAdmin)->count() : 0,
                'td_selesai' => class_exists(Tiket::class) ? Tiket::where('kategori', 'Transformasi Digital')->where('status_tiket', 'Selesai')->count() : 0,
            ];

            return Inertia::render('Dashboard/AdminDashboard', [
                'stats'         => $statsSistem,
                'recentTickets' => Tiket::latest()->take(5)->get()
            ]);
        }

        abort(403, 'Peranan sistem tidak sah.');
    }
}
