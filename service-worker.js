/**
 * service-worker.js — AayuVault Service Worker for Web Push & Background Notifications
 *
 * Handles push events dispatched from Web Push servers and notification click actions.
 * NOTE: For notifications to be delivered when the browser is completely closed,
 * Web Push via VAPID keys and a push service (e.g. FCM or Web Push API) is required.
 */

self.addEventListener('install', (event) => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim());
});

// Push event from server
self.addEventListener('push', (event) => {
  let payload = {
    title: 'AayuVault Reminder',
    body: 'You have a healthcare reminder scheduled.',
    icon: '/assets/logo.png',
    badge: '/assets/logo.png',
    tag: 'aayuvault-alert',
    data: { url: '/templates/dashboard.html' }
  };

  if (event.data) {
    try {
      payload = { ...payload, ...event.data.json() };
    } catch (e) {
      payload.body = event.data.text();
    }
  }

  const options = {
    body: payload.body,
    icon: payload.icon,
    badge: payload.badge,
    tag: payload.tag,
    data: payload.data,
    requireInteraction: true,
    vibrate: [200, 100, 200],
    actions: [
      { action: 'view', title: 'Open Dashboard' },
      { action: 'dismiss', title: 'Dismiss' }
    ]
  };

  event.waitUntil(
    self.registration.showNotification(payload.title, options)
  );
});

// Click notification
self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  if (event.action === 'dismiss') {
    return;
  }

  const targetUrl = event.notification.data?.url || '/templates/dashboard.html';

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
      for (let client of windowClients) {
        if (client.url.includes('AayuVault') && 'focus' in client) {
          return client.focus();
        }
      }
      if (clients.openWindow) {
        return clients.openWindow(targetUrl);
      }
    })
  );
});
