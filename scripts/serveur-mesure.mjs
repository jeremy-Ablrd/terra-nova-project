// Mandataire de mesure : se place devant le serveur PHP et applique (ou non) les règles de public/.htaccess
// qu'un serveur PHP intégré ne sait pas appliquer (compression, cache long de /build).
//
//   regles = 'aucune'   : réponses telles que PHP les envoie (état d'origine du .htaccess : ni compression ni cache long)
//   regles = 'htaccess' : reproduit les règles de public/.htaccess (mod_deflate pour les textes, cache immuable sur /build)
//
// C'est une SIMULATION : le comportement réel dépend de l'hébergeur (voir la page /eco-conception).
import http from 'node:http';
import zlib from 'node:zlib';

const COMPRESSIBLES = /^(text\/(html|plain|css|javascript)|application\/(javascript|json|xml)|image\/svg\+xml)/i;

export function demarrerMandataire({ port, cible, regles }) {
    const serveur = http.createServer((req, res) => {
        const amont = http.request(
            // Pas de connexion persistante : le serveur PHP intégré traite une connexion à la fois et resterait bloqué.
            { host: '127.0.0.1', port: cible, method: req.method, path: req.url, headers: { ...req.headers, connection: 'close' }, agent: false },
            (reponse) => {
                const morceaux = [];
                reponse.on('data', (m) => morceaux.push(m));
                reponse.on('end', () => {
                    let corps = Buffer.concat(morceaux);
                    const entetes = { ...reponse.headers };

                    if (regles === 'htaccess') {
                        if (req.url.startsWith('/build/')) {
                            entetes['cache-control'] = 'public, max-age=31536000, immutable';
                        }
                        const type = String(entetes['content-type'] ?? '');
                        const accepte = String(req.headers['accept-encoding'] ?? '').includes('gzip');
                        if (accepte && COMPRESSIBLES.test(type) && !entetes['content-encoding'] && corps.length > 0) {
                            corps = zlib.gzipSync(corps, { level: 6 });
                            entetes['content-encoding'] = 'gzip';
                            entetes['vary'] = entetes['vary'] ? `${entetes['vary']}, Accept-Encoding` : 'Accept-Encoding';
                        }
                    }

                    delete entetes['transfer-encoding'];
                    entetes['content-length'] = corps.length;
                    res.writeHead(reponse.statusCode, entetes);
                    res.end(corps);
                });
            },
        );
        amont.on('error', () => {
            res.writeHead(502);
            res.end();
        });
        req.pipe(amont);
    });

    return new Promise((resoudre) => serveur.listen(port, '127.0.0.1', () => resoudre(serveur)));
}
