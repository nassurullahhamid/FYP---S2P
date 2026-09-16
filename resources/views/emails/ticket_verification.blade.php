<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Notifikasi Pengesahan Tiket S2P</title>
</head>
<body style="font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f4f6f9; padding: 40px 20px; margin: 0; -webkit-font-smoothing: antialiased;">
    <div style="max-width: 600px; background-color: #ffffff; margin: 0 auto; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 15px rgba(12, 24, 59, 0.08); border: 1px solid #eef2f5;">

        <div style="background-color: #0c183b; padding: 35px 30px; text-align: center; color: #ffffff;">
            <h2 style="margin: 0; text-transform: uppercase; letter-spacing: 1.5px; font-size: 18px; font-weight: 800;">Sistem Pengurusan Perkhidmatan (S2P)</h2>
            <p style="margin: 5px 0 0 0; color: #38bdf8; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">Notifikasi Tindakan Segera</p>
        </div>

        <div style="padding: 35px 30px; color: #334155;">
            <p style="font-size: 15px; font-weight: 700; color: #0c183b; margin-top: 0;">Salam Sejahtera Tuan/Puan,</p>
            <p style="font-size: 14px; line-height: 1.6; color: #475569;">Sebuah permohonan tiket baharu telah berjaya didaftarkan oleh pihak Pentadbir Sistem (Admin) dan memerlukan klasifikasi segera daripada pihak pengurusan.</p>

            <div style="background-color: #f8fafc; border-left: 4px solid #2563eb; padding: 22px; margin: 28px 0; border-radius: 8px;">
                <table style="width: 100%; font-size: 13px; border-collapse: collapse;">
                    <tr>
                        <td style="color: #94a3b8; width: 130px; padding-bottom: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;">No. Tiket:</td>
                        <td style="padding-bottom: 10px; font-weight: 700; color: #0c183b;">{{ $ticket->id_tiket }}</td>
                    </tr>
                    <tr>
                        <td style="color: #94a3b8; width: 130px; padding-bottom: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;">Agensi:</td>
                        <td style="padding-bottom: 10px; font-weight: 700; color: #0c183b; text-transform: uppercase;">{{ $ticket->agensi }}</td>
                    </tr>
                    <tr>
                        <td style="color: #94a3b8; width: 130px; padding-bottom: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;">Kategori:</td>
                        <td style="padding-bottom: 10px; font-weight: 700; color: #2563eb;">{{ $ticket->kategori }}</td>
                    </tr>
                     <tr>
                        <td style="color: #94a3b8; width: 130px; padding-bottom: 0px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;">Sub Kategori:</td>
                        <td style="padding-bottom: 0px; font-weight: 700; color: #2563eb; text-transform: uppercase;">{{ $subKategori }}</td>
                    </tr>
                </table>
            </div>

            <div style="text-align: center; margin-top: 35px; margin-bottom: 15px;">
                <a href="{{ url('/tickets/' . $ticket->id_tiket . '/verify-action') }}"
                   style="background-color: #2563eb; color: #ffffff; padding: 14px 32px; font-weight: 800; font-size: 12px; text-decoration: none; border-radius: 12px; text-transform: uppercase; letter-spacing: 0.8px; display: inline-block; box-shadow: 0 4px 14px rgba(37,99,235,0.25); transition: background-color 0.2s;">
                    Sahkan Sekarang
                </a>
            </div>
        </div>

        <div style="background-color: #fafafa; padding: 22px; text-align: center; font-size: 11px; color: #94a3b8; border-top: 1px solid #f1f5f9; line-height: 1.4;">
            E-mel ini dijanakan secara automatik. <br/>
            Sila abaikan makluman ini jika tindakan pengesahan telah diambil oleh ahli jawatankuasa lain.
        </div>
    </div>
</body>
</html>
