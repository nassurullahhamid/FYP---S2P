import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';
import Sidebar from '@/Components/Sidebar';
import Topbar from '@/Components/Topbar';

export default function Edit({ auth, mustVerifyEmail, status }) {
    const user = auth.user;

    return (
        <div className="min-h-screen bg-gray-100 flex">
            <Head title="Profil Saya" />

            {/* 1. RENDER SIDEBAR HERE */}
            <Sidebar auth={auth} />

            {/*  MAIN CONTENT AREA */}
            <div className="flex-1 flex flex-col min-w-0">

                {/* 2. USE TOPBAR COMPONENT INSTEAD OF HARDCODED HEADER */}
                <Topbar auth={auth} title="Profil Saya" subtitle="Kemaskini maklumat akaun dan keselamatan" />

                {/* CONTENT BODY */}
                <main className="flex-1 overflow-y-auto bg-[#f8fafc] p-6 md:p-8 animate-in fade-in duration-500">
                    <div className="mx-auto max-w-7xl space-y-6">

                        <div className="bg-white p-6 shadow-sm border border-gray-100 sm:rounded-[2.5rem] sm:p-10">
                            <UpdateProfileInformationForm
                                mustVerifyEmail={mustVerifyEmail}
                                status={status}
                                className="max-w-xl"
                            />
                        </div>

                        <div className="bg-white p-6 shadow-sm border border-gray-100 sm:rounded-[2.5rem] sm:p-10">
                            <UpdatePasswordForm className="max-w-xl" />
                        </div>

                        <div className="bg-white p-6 shadow-sm border border-gray-100 sm:rounded-[2.5rem] sm:p-10">
                            <p className="text-sm text-gray-600">
                                Untuk penutupan akaun, sila hubungi Admin.
                            </p>
                        </div>

                    </div>
                </main>
            </div>
        </div>
    );
}
