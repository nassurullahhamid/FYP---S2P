<?php

use App\Http\Controllers\Admin\AsetController;
use App\Http\Controllers\Admin\PengurusanPenggunaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TicketWorkflowController;
use App\Models\Pengguna;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

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
        $user = Pengguna::find(Auth::id());

        return response()->json($user ? $user->unreadNotifications : []);
    })->name('api.notifications');

    Route::post('/api/notifications/{id}/read', function ($id) {
        $user = Pengguna::find(Auth::id());
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
    })->middleware('role:admin')->name('tickets.create');

    Route::post('/tickets', [TicketController::class, 'store'])->middleware('role:admin')->name('tickets.store');
    Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::get('/senarai-tiket', [TicketController::class, 'index'])->name('tickets.senarai');
    Route::post(
        '/tickets/{id_tiket}/workflow/classify',
        [TicketWorkflowController::class, 'classify']
    )->name('tickets.workflow.classify');
    Route::post(
        '/tickets/{id_tiket}/workflow/generate-loan-assets',
        [TicketWorkflowController::class, 'generateLoanAssets']
    )->name('tickets.workflow.generateLoanAssets');
    Route::post(
        '/tickets/{id_tiket}/workflow/review-network',
        [TicketWorkflowController::class, 'reviewNetwork']
    )->name('tickets.workflow.reviewNetwork');
    Route::post(
        '/tickets/{id_tiket}/workflow/validate-network-lkk',
        [TicketWorkflowController::class, 'validateNetworkLkk']
    )->name('tickets.workflow.validateNetworkLkk');
    Route::post(
        '/tickets/{id_tiket}/workflow/save-network-lkk',
        [TicketWorkflowController::class, 'saveNetworkLkk']
    )->name('tickets.workflow.saveNetworkLkk');
    Route::post(
        '/tickets/{id_tiket}/workflow/review-network-site-report',
        [TicketWorkflowController::class, 'reviewNetworkSiteReport']
    )->name('tickets.workflow.reviewNetworkSiteReport');
    Route::post(
        '/tickets/{id_tiket}/workflow/submit-network-site-report',
        [TicketWorkflowController::class, 'submitNetworkSiteReport']
    )->name('tickets.workflow.submitNetworkSiteReport');
    Route::post(
        '/tickets/{id_tiket}/workflow/review-helpdesk',
        [TicketWorkflowController::class, 'reviewHelpdesk']
    )->name('tickets.workflow.reviewHelpdesk');
    Route::post(
        '/tickets/{id_tiket}/workflow/review-loan',
        [TicketWorkflowController::class, 'reviewLoan']
    )->name('tickets.workflow.reviewLoan');
    Route::post(
        '/tickets/{id_tiket}/workflow/confirm-helpdesk',
        [TicketWorkflowController::class, 'confirmHelpdesk']
    )->name('tickets.workflow.confirmHelpdesk');
    Route::post(
        '/tickets/{id_tiket}/workflow/confirm-loan',
        [TicketWorkflowController::class, 'confirmLoan']
    )->name('tickets.workflow.confirmLoan');
    Route::post(
        '/tickets/{id_tiket}/workflow/save-loan-form',
        [TicketWorkflowController::class, 'saveLoanForm']
    )->name('tickets.workflow.saveLoanForm');

    Route::post(
        '/tickets/{id_tiket}/workflow/submit-helpdesk',
        [TicketWorkflowController::class, 'submitHelpdeskByPic']
    )->name('tickets.workflow.submitHelpdesk');
    Route::post(
        '/tickets/{id_tiket}/workflow/submit-loan',
        [TicketWorkflowController::class, 'submitLoanByPic']
    )->name('tickets.workflow.submitLoan');
    Route::post(
        '/tickets/{id_tiket}/workflow/submit-procurement-report',
        [
            TicketWorkflowController::class,
            'submitProcurementReport',
        ]
    )->name('tickets.workflow.submitProcurementReport');

    Route::post(
        '/tickets/{id_tiket}/workflow/save-procurement-lkk',
        [
            TicketWorkflowController::class,
            'saveProcurementLkk',
        ]
    )->name('tickets.workflow.saveProcurementLkk');
    Route::post(
        '/tickets/{id_tiket}/workflow/submit-modernization-report',
        [TicketWorkflowController::class, 'submitModernizationReport']
    )->name('tickets.workflow.submitModernizationReport');
    Route::post(
        '/tickets/{id_tiket}/workflow/save-modernization-lkk',
        [TicketWorkflowController::class, 'saveModernizationLkk']
    )->name('tickets.workflow.saveModernizationLkk');
    Route::post(
        '/tickets/{id_tiket}/workflow/review-procurement-lkk',
        [
            TicketWorkflowController::class,
            'reviewProcurementLkk',
        ]
    )->name('tickets.workflow.reviewProcurementLkk');
    Route::post(
        '/tickets/{id_tiket}/workflow/review-modernization-lkk',
        [TicketWorkflowController::class, 'reviewModernizationLkk']
    )->name('tickets.workflow.reviewModernizationLkk');
    Route::post(
        '/tickets/{id_tiket}/workflow/validate-procurement-lkk',
        [
            TicketWorkflowController::class,
            'validateProcurementLkk',
        ]
    )->name('tickets.workflow.validateProcurementLkk');
    Route::post(
        '/tickets/{id_tiket}/workflow/validate-modernization-lkk',
        [TicketWorkflowController::class, 'validateModernizationLkk']
    )->name('tickets.workflow.validateModernizationLkk');
    Route::post(
        '/tickets/{id_tiket}/workflow/review-procurement',
        [
            TicketWorkflowController::class,
            'reviewProcurementTicket',
        ]
    )->name('tickets.workflow.reviewProcurement');

    Route::post(
        '/tickets/{id_tiket}/workflow/assign-procurement',
        [
            TicketWorkflowController::class,
            'assignProcurementTicket',
        ]
    )->name('tickets.workflow.assignProcurement');
    Route::post(
        '/tickets/{id_tiket}/workflow/review-modernization',
        [TicketWorkflowController::class, 'reviewModernizationTicket']
    )->name('tickets.workflow.reviewModernization');
    Route::post(
        '/tickets/{id_tiket}/workflow/assign-modernization',
        [TicketWorkflowController::class, 'assignModernizationTicket']
    )->name('tickets.workflow.assignModernization');
    Route::get('/tickets/{id_tiket}', [TicketController::class, 'show'])->name('tickets.show');
    Route::get('/tickets/{id_tiket}/verify-action', [TicketController::class, 'handleEmailRedirect'])->name('tickets.email.redirect');

    // Site Visit & Asset Loan Details
    Route::get('/aset/kuantiti', [TicketController::class, 'getKuantitiAset'])->name('aset.getKuantitiAset');
    Route::get('/tickets/{id_tiket}/cetak-peminjaman/{serial_no}', [TicketController::class, 'cetakPeminjaman'])->name('tickets.cetakPeminjaman');

    // Helpdesk Work Closing & Approvals

    // LKK Reports & Print Endpoints
    Route::get('/tickets/{id_tiket}/cetak-lkk', [TicketController::class, 'cetakLKK'])->name('tickets.cetakLKK');
    Route::get('/tickets/{id_tiket}/cetak-tapak', [TicketController::class, 'cetakMaklumatTapak'])->name('tickets.cetakMaklumatTapak');

    // User Management
    Route::get('/pengurusan-pengguna', [PengurusanPenggunaController::class, 'index'])->middleware('role:admin')->name('users.index');
    Route::post('/pengurusan-pengguna', [PengurusanPenggunaController::class, 'store'])->middleware('role:admin')->name('users.store');
    Route::patch('/pengurusan-pengguna/{no_ic}', [PengurusanPenggunaController::class, 'update'])->middleware('role:admin')->name('users.update');
    Route::delete('/pengurusan-pengguna/{no_ic}', [PengurusanPenggunaController::class, 'destroy'])->middleware('role:admin')->name('users.destroy');

    // Asset Management
    Route::get('/pengurusan-aset', [AsetController::class, 'index'])->name('assets.index');
    Route::post('/pengurusan-aset', [AsetController::class, 'store'])->name('assets.store');
    Route::patch('/pengurusan-aset/{serial_no}', [AsetController::class, 'update'])->name('assets.update');
    Route::post('/pengurusan-aset/{serial_no}/pemulangan', [AsetController::class, 'storePemulangan'])->name('assets.pemulangan.store');
    Route::delete('/pengurusan-aset/{serial_no}', [AsetController::class, 'destroy'])->name('assets.destroy');

});

require __DIR__.'/auth.php';
