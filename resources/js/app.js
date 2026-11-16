import './bootstrap';
import Alpine from 'alpinejs';

// Alpine.js untuk layar dengan state rumit (form KPI, asesmen 360°, kalibrasi).
// Komponen per halaman didaftarkan dari Blade lewat:
//   document.addEventListener('alpine:init', () => Alpine.data('nama', () => ({ ... })))
// Script modul Vite berjalan setelah HTML selesai di-parse, jadi listener di atas
// sudah terpasang sebelum Alpine.start(). Konten yang dimuat via AJAX Sximo
// (modal form, dll.) ikut diinisialisasi otomatis oleh Alpine.
window.Alpine = Alpine;
Alpine.start();
