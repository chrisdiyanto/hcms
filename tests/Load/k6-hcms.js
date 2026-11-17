// Uji beban HCMS (Fase 4): 1.000 pengguna bersamaan, target p95 < 1 detik.
//
// Jalankan di STAGING (Octane + OPcache + Redis), bukan di mesin dev:
//   k6 run -e BASE_URL=https://hcms-staging.example -e PASSWORD=... tests/Load/k6-hcms.js
//
// Data: tenant hasil `php artisan hcms:load-seed --employees=10000 --users=1000`
// (akun load.u00001 … load.u01000; 249 pertama berperan Line Manager, sisanya Employee).
// Catatan: login dibatasi 5×/menit per IP (throttle:5,1,login). Jalankan dari beberapa IP
// (k6 cloud / beberapa generator) atau kecualikan IP generator di staging selama uji.
import http from 'k6/http';
import { check, group, sleep } from 'k6';

const BASE = __ENV.BASE_URL || 'http://hcms.test';
const PASSWORD = __ENV.PASSWORD;

export const options = {
    scenarios: {
        users: {
            executor: 'ramping-vus',
            startVUs: 0,
            stages: [
                { duration: '3m', target: 1000 }, // naik bertahap (login tersebar)
                { duration: '10m', target: 1000 }, // beban puncak
                { duration: '2m', target: 0 },
            ],
            gracefulRampDown: '30s',
        },
    },
    thresholds: {
        'http_req_duration{kind:page}': ['p(95)<1000'],
        'http_req_failed{kind:page}': ['rate<0.01'],
        checks: ['rate>0.99'],
    },
};

function login(username) {
    const page = http.get(`${BASE}/user/login`, { tags: { kind: 'auth' } });
    const token = (page.body.match(/name="_token" value="([^"]+)"/) || [])[1];
    const res = http.post(`${BASE}/user/signin`, { _token: token, email: username, password: PASSWORD },
        { headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }, tags: { kind: 'auth' } });
    check(res, { 'login berhasil': (r) => r.status === 200 && !/error|gagal/i.test(r.body) });
}

const page = (path, name) => {
    const res = http.get(`${BASE}${path}`, { tags: { kind: 'page', name } });
    check(res, { [`${name} 200`]: (r) => r.status === 200 });
    sleep(Math.random() * 3 + 2); // waktu baca 2–5 detik
};

export default function () {
    // Satu akun per VU; sesi (cookie) dipertahankan k6 per VU
    if (__ITER === 0) {
        if (!PASSWORD) {
            throw new Error('Set -e PASSWORD=<sandi akun uji beban>');
        }
        login(`load.u${String(__VU).padStart(5, '0')}`);
    }
    const isManager = __VU <= 249;

    group('pegawai', () => {
        page('/ess/profile', 'profil');
        page('/performance/my-plans', 'rencana-saya');
        page('/performance/my-ratings', 'rating-saya');
        page('/competency/my-assessments', 'asesmen-saya');
    });
    if (isManager) {
        group('atasan', () => {
            page('/performance/team', 'tim-saya');
            page('/employees', 'pegawai-scoped');
            page('/performance/dashboard', 'dasbor-scoped');
        });
    }
}
