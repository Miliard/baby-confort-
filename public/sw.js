/*
 * El trabajador en segundo plano de Baby-Confort.
 *
 * Queda instalado en el teléfono y sigue vivo con la aplicación cerrada. Su
 * único trabajo es escuchar los avisos y mostrar la notificación.
 *
 * Los avisos llegan vacíos a propósito: el contenido no viaja por los
 * servidores de Google. Cuando llega uno, este archivo le pregunta al panel
 * cuántos mensajes hay sin leer y recién ahí arma el texto.
 */

self.addEventListener('install', function (e) {
    // Que el nuevo tome el control sin esperar a que cierren las pestañas.
    self.skipWaiting();
});

self.addEventListener('activate', function (e) {
    e.waitUntil(self.clients.claim());
});

self.addEventListener('push', function (e) {
    e.waitUntil(
        fetch('/chat/sin-leer', { credentials: 'include', cache: 'no-store' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (d) {
                var n = (d && d.sin_leer) ? d.sin_leer : 0;

                // Sin pendientes: alguien ya lo atendió entre el aviso y esto.
                // Igual hay que mostrar algo, porque el navegador obliga.
                var cuerpo = n === 1
                    ? 'Tenés 1 conversación sin leer'
                    : (n > 1
                        ? 'Tenés ' + n + ' conversaciones sin leer'
                        : 'Entró un mensaje nuevo');

                return self.registration.showNotification('Baby-Confort · mensajes', {
                    body: cuerpo,
                    icon: '/favicon-192.png',
                    badge: '/favicon-32.png',
                    tag: 'baby-confort-wa',   // uno solo, no veinte apilados
                    renotify: true,
                    vibrate: [180, 80, 180],
                    data: { url: '/chat/whatsapp' }
                });
            })
            .catch(function () {
                return self.registration.showNotification('Baby-Confort · mensajes', {
                    body: 'Entró un mensaje nuevo',
                    icon: '/favicon-192.png',
                    tag: 'baby-confort-wa',
                    data: { url: '/chat/whatsapp' }
                });
            })
    );
});

self.addEventListener('notificationclick', function (e) {
    e.notification.close();

    var destino = (e.notification.data && e.notification.data.url) || '/chat/whatsapp';

    e.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then(function (lista) {
                // Si el panel ya está abierto en alguna ventana, se trae esa
                // en lugar de abrir una nueva encima.
                for (var i = 0; i < lista.length; i++) {
                    if (lista[i].url.indexOf('/chat') !== -1 && 'focus' in lista[i]) {
                        return lista[i].focus();
                    }
                }

                if (self.clients.openWindow) return self.clients.openWindow(destino);
            })
    );
});
