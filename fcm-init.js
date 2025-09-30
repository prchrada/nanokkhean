// js/fcm-init.js

// Your Firebase configuration
const firebaseConfig = {
    apiKey: "AIzaSyA_Ahn7_7KMXppfJ2wNUWSMxlkcswR17es",
    authDomain: "product-cfe8a.firebaseapp.com",
    projectId: "product-cfe8a",
    storageBucket: "product-cfe8a.firebasestorage.app",
    messagingSenderId: "491590872427",
    appId: "1:491590872427:web:a11d40da5192c28e26b02e",
    measurementId: "G-L57YBWEWCQ"
};

// Initialize Firebase
firebase.initializeApp(firebaseConfig);
const messaging = firebase.messaging();

/**
 * Registers the FCM token with the server for the given user.
 * @param {string|number} userId The ID of the current user.
 */
async function registerFCMToken(userId) {
    try {
        const swRegistration = await navigator.serviceWorker.register('/marketplace/firebase-messaging-sw.js');

        const permission = await Notification.requestPermission();
        if (permission !== 'granted') {
            console.log('Notification permission not granted.');
            return;
        }

        const token = await messaging.getToken({
            serviceWorkerRegistration: swRegistration,
            vapidKey: 'BM1vbxj29QhJ4tLoJ838JDBVN7smoonBwW0x8m1rYhhGgVwaiOGqHlucdQ7O1as9Sq79spMe0nFiDbJ6ltb9gJE'
        });

        if (token) {
            await fetch('/marketplace/firebase_noti_register.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user_id: userId, token }),
            });
            console.log('FCM token registered with server.');
        }
    } catch (err) {
        console.error('FCM registration error:', err);
    }
}
