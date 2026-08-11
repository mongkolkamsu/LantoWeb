self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(clients.claim());
});

self.addEventListener('fetch', (event) => {
    // 1. ข้ามการดักจับหากไม่ใช่โปรโตคอล http หรือ https (เช่น chrome-extension://)
    if (!event.request.url.startsWith('http')) return;

    // 2. ดักจับ Promise Rejection เมื่อการดึงข้อมูลล้มเหลว
    event.respondWith(
        fetch(event.request).catch(() => {
            // ส่งค่า Response เปล่ากลับไปแทนการปล่อยให้เกิด Uncaught Error บน Console
            return new Response('', { status: 503, statusText: 'Service Unavailable' });
        })
    );
});