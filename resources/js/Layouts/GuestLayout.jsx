import { Link } from '@inertiajs/react';

export default function GuestLayout({ children }) {
    return (
        <div className="relative min-h-screen w-full flex flex-col items-center justify-center bg-gradient-to-br from-[#003ba1] via-[#001c54] to-[#000a1f] overflow-x-hidden selection:bg-blue-600 selection:text-white">

            {/* Background grid and glow effects */}
            <div className="absolute inset-0 z-0 pointer-events-none select-none opacity-80">
                <div className="absolute inset-0 bg-[linear-gradient(to_right,#ffffff03_1px,transparent_1px),linear-gradient(to_bottom,#ffffff03_1px,transparent_1px)] bg-[size:40px_40px] [mask-image:radial-gradient(ellipse_at_center,black_40%,transparent_100%)]"></div>
                <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 h-[650px] w-[650px] rounded-full border border-blue-500/10 [mask-image:radial-gradient(ellipse_at_center,transparent_20%,black)]"></div>
                <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 h-[450px] w-[450px] rounded-full border border-dashed border-blue-400/5"></div>

                <div className="absolute top-1/4 left-1/3 h-96 w-96 rounded-full bg-blue-600/10 blur-3xl animate-pulse duration-[8s]"></div>
                <div className="absolute bottom-1/4 right-1/4 h-80 w-80 rounded-full bg-blue-500/5 blur-3xl animate-pulse duration-[6s]"></div>
            </div>

            {/* Main container */}
            <div className="relative z-10 w-full max-w-4xl px-4 py-10 sm:px-6 flex flex-col items-center">

                {/* Department logo and header */}
                <div className="flex flex-col items-center text-center space-y-2 animate-in fade-in slide-in-from-top-4 duration-500">
                    <div className="shrink-0 mb-1">
                        <img
                            src="/images/logo_jtdi.png"
                            alt="JTDI Logo"
                            className="w-[100px] sm:w-[110px] h-auto object-contain drop-shadow-[0_4px_20px_rgba(37,99,235,0.25)]"
                            onError={(e) => { e.target.style.display = 'none'; }}
                        />
                    </div>
                    <div className="space-y-0.5">
                        <h2 className="text-[11px] sm:text-xs font-bold uppercase tracking-widest text-white/90">
                            Jabatan Teknologi Digital
                        </h2>
                        <h3 className="text-[10px] sm:text-[11px] font-semibold tracking-wider uppercase text-blue-300/70">
                            Dan Inovasi Negeri Sabah
                        </h3>
                    </div>
                </div>

                {/* System title */}
                <div className="text-center mt-5 space-y-2 animate-in fade-in duration-700">
                    <h1 className="text-xl sm:text-2xl font-black text-white uppercase tracking-wide leading-tight drop-shadow-md">
                        Sistem Pengurusan <br className="sm:hidden" /> Perkhidmatan (S2P)
                    </h1>
                    <div className="w-12 h-[2px] bg-amber-500 mx-auto mt-2 rounded-full shadow-[0_0_8px_#f59e0b]"></div>
                </div>

                {/* Form content slot */}
                <div className="w-full mt-8 animate-in fade-in zoom-in-95 duration-400">
                    {children}
                </div>

            </div>
        </div>
    );
}
