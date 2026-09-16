import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Transition } from '@headlessui/react';
import { Link, useForm, usePage } from '@inertiajs/react';

export default function UpdateProfileInformation({
    mustVerifyEmail,
    status,
    className = '',
}) {
    const user = usePage().props.auth.user;

    const { data, setData, patch, errors, processing, recentlySuccessful } =
        useForm({
            nama: user.nama,
            emel: user.emel,
        });

    const submit = (e) => {
        e.preventDefault();

        patch(route('profile.update'));
    };

    return (
        <section className={className}>
            <header>
                <h2 className="text-lg font-medium text-gray-900">
                Maklumat Profil
                </h2>

                <p className="mt-1 text-sm text-gray-600">
                Kemaskini maklumat profil akaun dan alamat e-mel anda.
                </p>
            </header>

            <form onSubmit={submit} className="mt-6 space-y-6">
                <div>
                    <InputLabel htmlFor="nama" value="Nama" />

                    <TextInput
                        id="nama"
                        className="mt-1 block w-full"
                        value={data.nama}
                        onChange={(e) => setData('nama', e.target.value)}
                        required
                        isFocused
                        autoComplete="nama"
                    />

                    <InputError className="mt-2" message={errors.nama} />
                </div>

                <div>
                    <InputLabel htmlFor="emel" value="E-mel" />

                    <TextInput
                        id="emel"
                        type="emel"
                        className="mt-1 block w-full"
                        value={data.emel}
                        onChange={(e) => setData('emel', e.target.value)}
                        required
                        autoComplete="username"
                    />

                    <InputError className="mt-2" message={errors.emel} />
                </div>

                {mustVerifyEmail && user.emel_verified_at === null && (
                    <div>
                        <p className="mt-2 text-sm text-gray-800">
                        Alamat e-mel anda belum disahkan.
                            <Link
                                href={route('verification.send')}
                                method="post"
                                as="button"
                                className="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                            >
                                Klik di sini untuk menghantar semula e-mel pengesahan.
                            </Link>
                        </p>

                        {status === 'verification-link-sent' && (
                            <div className="mt-2 text-sm font-medium text-green-600">
                                 Pautan pengesahan baharu telah dihantar ke alamat e-mel anda.
                            </div>
                        )}
                    </div>
                )}

                <div className="flex items-center gap-4">
                    <PrimaryButton disabled={processing}>Simpan</PrimaryButton>

                    <Transition
                        show={recentlySuccessful}
                        enter="transition ease-in-out"
                        enterFrom="opacity-0"
                        leave="transition ease-in-out"
                        leaveTo="opacity-0"
                    >
                        <p className="text-sm text-gray-600">
                            Berjaya Disimpan.
                        </p>
                    </Transition>
                </div>
            </form>
        </section>
    );
}
