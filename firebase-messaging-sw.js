importScripts('https://www.gstatic.com/firebasejs/10.11.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.11.0/firebase-messaging-compat.js');

firebase.initializeApp({
  apiKey: "AIzaSyA_Ahn7_7KMXppfJ2wNUWSMxlkcswR17es",
  authDomain: "product-cfe8a.firebaseapp.com",
  projectId: "product-cfe8a",
  messagingSenderId: "491590872427",
  appId: "1:491590872427:web:a11d40da5192c28e26b02e"
});

const messaging = firebase.messaging();

messaging.onBackgroundMessage(async function(payload) {
  self.clients.matchAll().then(clients => {
    clients.forEach(client => {
      client.postMessage({ type: 'fcm-log', payload });
    });
  });

  await fetch('/log.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ message: 'Background message received', payload })
  });

  console.log('[firebase-messaging-sw.js] Received background message', payload);
  const notificationTitle = payload.notification?.title || 'Background Message';
  const notificationOptions = {
    body: payload.notification?.body || 'You have a new message.',
    icon: '/icon.png'
  };
  self.registration.showNotification(notificationTitle, notificationOptions);
});

self.addEventListener('notificationclick', function(event) {
  event.notification.close();
  event.waitUntil(clients.openWindow('/')); // open homepage on click
});
