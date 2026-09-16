<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\Admin\PengurusanPenggunaController;
use App\Http\Controllers\Admin\AsetController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\Tiket;
use App\Models\Pengguna;

// Redirect root to login
Route::get('/', function () {
    return redirect()->route('login');
});

// Dashboard route
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Protected routes
Route::middleware('auth')->group(function () {

    // Notification API
    Route::get('/api/notifications', function () {
        $user = \App\Models\Pengguna::find(Auth::id());
        return response()->json($user ? $user->unreadNotifications : []);
    })->name('api.notifications');

    Route::post('/api/notifications/{id}/read', function ($id) {
        $user = \App\Models\Pengguna::find(Auth::id());
        if ($user) {
            $notification = $user->notifications()->find($id);
            if ($notification) {
                $notification->markAsRead();
            }
        }
        return response()->json(['success' => true]);
    })->name('api.notifications.read');


    // Profile Management
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');


    // Ticket Management
    Route::get('/tickets/create', function () {
        return Inertia::render('Tickets/DaftarPermohonan');
    })->name('tickets.create');

    Route::post('/tickets', [TicketController::class, 'store'])->name('tickets.store');
    Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::get('/senarai-tiket', [TicketController::class, 'index'])->name('tickets.senarai');
    Route::get('/tickets/{id_tiket}', [TicketController::class, 'show'])->name('tickets.show');
    Route::post('/tickets/{ticket}/processAction', [TicketController::class, 'processAction'])->name('tickets.processAction');
    Route::post('/tickets/{ticket}/pic-update', [TicketController::class, 'picUpdate'])->name('tickets.picUpdate');
    Route::get('/tickets/{id_tiket}/verify-action', [TicketController::class, 'handleEmailRedirect'])->name('tickets.email.redirect');

    // Site Visit & Asset Loan Details
    Route::get('/aset/kuantiti', [TicketController::class, 'getKuantitiAset'])->name('aset.getKuantitiAset');
    Route::post('/tickets/{id_tiket}/laporan-tapak', [TicketController::class, 'storeLaporanTapak'])->name('tickets.storeLaporanTapak');
    Route::post('/tickets/{id_tiket}/peminjaman', [TicketController::class, 'storePeminjaman'])->name('tickets.storePeminjaman');
    Route::post('/tickets/{id_tiket}/jana-aset', [TicketController::class, 'janaSenaraiAset'])->name('tickets.janaSenaraiAset');
    Route::get('/tickets/{id_tiket}/cetak-peminjaman/{serial_no}', [TicketController::class, 'cetakPeminjaman'])->name('tickets.cetakPeminjaman');
    Route::post('/tickets/{id_tiket}/simpan-borang-peminjaman', [TicketController::class, 'simpanBorangPeminjaman'])->name('tickets.simpanBorangPeminjaman');
    Route::post('/tickets/{id_tiket}/hantar-ke-kutd', [TicketController::class, 'hantarKeKUTD'])->name('tickets.hantarKeKUTD');
    Route::post('/tickets/{id_tiket}/proses-pengesahan-kutd', [TicketController::class, 'prosesPengesahanKutd'])->name('tickets.prosesPengesahanKutd');

    // Helpdesk Work Closing & Approvals
    Route::post('/tickets/{ticket}/hantar-laporan-mb', [TicketController::class, 'hantarLaporanMB'])->name('tickets.hantarLaporanMB');
    Route::post('/tickets/{id_tiket}/sahkan-tutup-peminjaman', [TicketController::class, 'sahkanTutupPeminjaman'])->name('tickets.sahkanTutupPeminjaman');
    Route::post('/tickets/{ticket}/sahkan-tutup-mb', [TicketController::class, 'sahkanTutupMB'])->name('tickets.sahkanTutupMB');

    // LKK Reports & Print Endpoints
    Route::post('/tickets/{id_tiket}/lkk-rangkaian', [TicketController::class, 'storeLKKRangkaian'])->name('tickets.storeLKKRangkaian');
    Route::get('/tickets/{id_tiket}/cetak-lkk', [TicketController::class, 'cetakLKK'])->name('tickets.cetakLKK');
    Route::post('/tickets/{ticket}/verifikasi-lkk', [TicketController::class, 'verifikasiLKK'])->name('tickets.verifikasiLKK');
    Route::post('/tickets/{id_tiket}/lkk-td', [TicketController::class, 'storeLKKTransformasiDigital'])->name('tickets.lkk.storeTD');
    Route::post('/admin/tickets/{id_tiket}/proses-aliran', [TicketController::class, 'storeLKKTransformasiDigital'])->name('tickets.lkk.prosesAliranPemodenan');
    Route::get('/tickets/{id_tiket}/cetak-tapak', [TicketController::class, 'cetakMaklumatTapak'])->name('tickets.cetakMaklumatTapak');

    // User Management
    Route::get('/pengurusan-pengguna', [PengurusanPenggunaController::class, 'index'])->name('users.index');
    Route::post('/pengurusan-pengguna', [PengurusanPenggunaController::class, 'store'])->name('users.store');
    Route::patch('/pengurusan-pengguna/{no_ic}', [PengurusanPenggunaController::class, 'update'])->name('users.update');
    Route::delete('/pengurusan-pengguna/{no_ic}', [PengurusanPenggunaController::class, 'destroy'])->name('users.destroy');

    // Asset Management
    Route::get('/pengurusan-aset', [AsetController::class, 'index'])->name('assets.index');
    Route::post('/pengurusan-aset', [AsetController::class, 'store'])->name('assets.store');
    Route::patch('/pengurusan-aset/{id_aset}', [AsetController::class, 'update'])->name('assets.update');
    Route::delete('/pengurusan-aset/{id_aset}', [AsetController::class, 'destroy'])->name('assets.destroy');
    Route::get('/admin/assets/category/{category}', [AsetController::class, 'showCategory'])->name('assets.category.show');

});

require __DIR__.'/auth.php';
