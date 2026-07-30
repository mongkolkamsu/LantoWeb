// Service Worker สำหรับ Lanto Web PWA
self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(clients.claim());
});

self.addEventListener('fetch', (event) => {
    // ให้ดึงข้อมูลสดจาก Server ตามปกติ
    event.respondWith(fetch(event.request));
});