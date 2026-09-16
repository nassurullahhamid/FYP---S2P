import { Head, Link, usePage } from '@inertiajs/react';

export default function AuthenticatedLayout({ header, children }) {
    const { auth } = usePage().props;

    return (
        <div className="min-h-screen bg-gray-100">
            <Head title="S2P" />

            {/* Navigation */}
            <nav className="border-b border-gray-100 bg-white">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="flex h-16 items-center justify-between">

                        {/* Logo / System Name */}
                        <div className="flex items-center">
                            <Link href="/dashboard">
                                <div className="flex items-center gap-3">
                                    <img
                                        src="/images/logo_jtdi.png"
                                        alt="JTDI Logo"
                                        className="h-10 w-auto object-contain"
                                        onError={(e) => {
                                            e.target.style.display = 'none';
                                        }}
                                    />

                                    <div>
                                        <div className="text-sm font-bold text-gray-800">
                                            S2P
                                        </div>
                                        <div className="text-xs text-gray-500">
                                            Sistem Pengurusan Perkhidmatan
                                        </div>
                                    </div>
                                </div>
                            </Link>
                        </div>

                        {/* Navigation Links */}
                        <div className="hidden sm:flex sm:items-center sm:gap-6">
                            <Link
                                href="/dashboard"
                                className="text-sm font-medium text-gray-700 hover:text-blue-600"
                            >
                                Dashboard
                            </Link>
                        </div>

                        {/* User */}
                        <div className="flex items-center gap-4">
                            {auth?.user && (
                                <span className="hidden text-sm text-gray-600 sm:block">
                                    {auth.user.name}
                                </span>
                            )}

                            <Link
                                href="/logout"
                                method="post"
                                as="button"
                                className="text-sm font-medium text-red-600 hover:text-red-800"
                            >
                                Log Keluar
                            </Link>
                        </div>

                    </div>
                </div>
            </nav>

            {/* Page Header */}
            {header && (
                <header className="bg-white shadow">
                    <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                        {header}
                    </div>
                </header>
            )}

            {/* Page Content */}
            <main>
                {children}
            </main>
        </div>
    );
}

