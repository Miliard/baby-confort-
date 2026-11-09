/*
 * El trabajador en segundo plano de Baby-Confort.
 *
 * Queda instalado en el teléfono y sigue vivo con la aplicación cerrada. Su
 * único trabajo es escuchar los avisos y mostrar la notificación.
 *
 * Los avisos llegan vacíos a propósito: el contenido no viaja por los
 * servidores de Google. Cuando llega uno, este archivo le pregunta al panel
 * cuántos mensajes hay sin leer y quién escribió, y recién ahí arma el texto.
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
                var u = d && d.ultimo;

                // Arriba quién escribió (el nombre que le pusiste o el de su
                // WhatsApp, y el número); abajo lo que escribió. Si hay más
                // chats esperando, se dice cuántos.
                var titulo = u && u.nombre ? u.nombre : 'Baby-Confort · mensajes';
                var cuerpo = u && u.texto
                    ? u.texto + (n > 1 ? '\n(+' + (n - 1) + ' chat' + (n - 1 === 1 ? '' : 's') + ' más sin leer)' : '')
                    : (n === 1 ? 'Tenés 1 conversación sin leer'
                        : (n > 1 ? 'Tenés ' + n + ' conversaciones sin leer' : 'Entró un mensaje nuevo'));

                return self.registration.showNotification(titulo, {
                    body: cuerpo,
                    icon: '/favicon-192.png',
                    badge: '/favicon-32.png',
                    tag: 'baby-confort-wa',   // uno solo, no veinte apilados
                    renotify: true,
                    vibrate: [180, 80, 180],
                    // Al tocarlo se abre ESE chat.
                    data: { url: u && u.id ? '/chat/whatsapp?chat=' + u.id : '/chat/whatsapp' }
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
                        // Ya abierto: se trae al frente y se lleva a ese chat.
                        var w = lista[i];
                        return w.focus().then(function (c) {
                            var cl = c || w;
                            if (destino.indexOf('chat=') !== -1 && 'navigate' in cl) return cl.navigate(destino);
                            return cl;
                        });
                    }
                }

                if (self.clients.openWindow) return self.clients.openWindow(destino);
            })
    );
});
