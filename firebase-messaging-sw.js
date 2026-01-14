importScripts('https://www.gstatic.com/firebasejs/8.3.2/firebase-app.js');
importScripts('https://www.gstatic.com/firebasejs/8.3.2/firebase-messaging.js');

firebase.initializeApp({
    apiKey: "AIzaSyBKoU2yhOjMDL5IquINM1W5Fb1MH12UO1Y",
    authDomain: "isent-2801a.firebaseapp.com",
    projectId: "isent-2801a",
    storageBucket: "isent-2801a.firebasestorage.app",
    messagingSenderId: "491952602772",
    appId: "1:154149946829:web:e0e51716aa66d744053084",
    measurementId: "G-RFDJK7LW24"
});

const messaging = firebase.messaging();
messaging.setBackgroundMessageHandler(function (payload) {
    return self.registration.showNotification(payload.data.title, {
        body: payload.data.body ? payload.data.body : '',
        icon: payload.data.icon ? payload.data.icon : ''
    });
});