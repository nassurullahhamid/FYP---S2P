import PrimaryButton from '@/Components/PrimaryButton';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function VerifyEmail({ status }) {
    const { post, processing } = useForm({});

    const submit = (e) => {
        e.preventDefault();

        post(route('verification.send'));
    };

    return (
        <GuestLayout>
            <Head title="Pengesahan E-mel" />

            <div className="mb-4 text-sm text-gray-600">
                Terima kasih kerana mendaftar! Sebelum bermula, sila sahkan
                alamat e-mel anda dengan mengklik pautan yang baru sahaja kami hantarkan ke
                e-mel anda. Jika anda tidak menerima e-mel tersebut, kami akan menghantar
                semula pautan yang baharu.
            </div>

            {status === 'verification-link-sent' && (
                <div className="mb-4 text-sm font-medium text-green-600">
                    Pautan pengesahan baharu telah dihantar ke alamat e-mel
                    yang anda berikan semasa pendaftaran.
                </div>
            )}

            <form onSubmit={submit}>
                <div className="mt-4 flex items-center justify-between">
                    <PrimaryButton disabled={processing}>
                    Hantar Semula E-mel Pengesahan
                    </PrimaryButton>

                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        className="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        Log Keluar
                    </Link>
                </div>
            </form>
        </GuestLayout>
    );
}
