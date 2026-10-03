// Mesure reproductible du poids et du temps de chargement des pages (F57 à F60).
//
//   npm run build
//   node scripts/mesure-poids.mjs --etape=avant            (ou apres, ou apres_sans_regles : le code d'après, sans
//                                                           compression ni cache long, comme chez un hébergeur sans ces modules)
//
// Options : --regles=aucune|htaccess  (défaut : htaccess pour « apres », aucune pour les autres étapes)
//           --pages=/,/services       (limiter les pages mesurées ; défaut : toutes)
//           --runs=3                  (nombre de chargements par page ; la valeur retenue pour le temps est la médiane)
//
// Ce que fait le script (aucune dépendance npm, Node 22+ et Edge installé) :
//   1. crée une base SQLite JETABLE dans le dossier temporaire (jamais la base de développement : le script refuse de
//      continuer si la connexion active n'est pas cette base), la migre et la remplit avec les seeders ;
//   2. lance le serveur PHP en production (APP_ENV=production, APP_DEBUG=false) sur le build de public/build,
//      jamais le serveur Vite, derrière un mandataire qui applique ou non les règles de public/.htaccess ;
//   3. pilote Edge sans interface (protocole CDP), cache désactivé, réseau bridé « 3G lent » : 400 kbit/s, latence 400 ms ;
//   4. écrit les résultats dans resources/data/mesures-poids.json (les mesures « avant » et « apres » sont conservées).
import { spawn, spawnSync } from 'node:child_process';
import crypto from 'node:crypto';
import fs from 'node:fs';
import http from 'node:http';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { demarrerMandataire } from './serveur-mesure.mjs';

const RACINE = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const SORTIE = path.join(RACINE, 'resources', 'data', 'mesures-poids.json');
const EDGE = process.env.EDGE_PATH ?? 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe';
const PORT_PHP = 8099;
const PORT_MANDATAIRE = 8100;
const PORT_CDP = 9333;
const BASE = `http://127.0.0.1:${PORT_MANDATAIRE}`;
const RESEAU = { profil: '3G lent', debit_descendant_kbit_s: 400, debit_montant_kbit_s: 400, latence_ms: 400 };

const args = Object.fromEntries(process.argv.slice(2).map((a) => a.replace(/^--/, '').split('=')));
const etape = args.etape;
if (!['avant', 'apres', 'apres_sans_regles'].includes(etape)) {
    console.error('Usage : node scripts/mesure-poids.mjs --etape=avant|apres|apres_sans_regles [--regles=aucune|htaccess] [--runs=3]');
    process.exit(1);
}
const regles = args.regles ?? (etape === 'apres' ? 'htaccess' : 'aucune');
const runs = Number(args.runs ?? 3);

const TOUTES = [
    { chemin: '/', role: 'invité' },
    { chemin: '/services', role: 'invité' },
    { chemin: '/urgences', role: 'invité' },
    { chemin: '/alertes', role: 'invité' },
    { chemin: '/login', role: 'invité' },
    { chemin: '/espace', role: 'citoyen' },
    { chemin: '/mes-demandes', role: 'citoyen' },
    { chemin: '/agent/demandes', role: 'agent' },
    { chemin: '/agent', role: 'agent' },
];

const PAGES = args.pages ? TOUTES.filter((p) => args.pages.split(',').includes(p.chemin)) : TOUTES;
const dormir = (ms) => new Promise((r) => setTimeout(r, ms));
const mediane = (v) => [...v].sort((a, b) => a - b)[Math.floor(v.length / 2)];

// --- Environnement jetable ------------------------------------------------------------------------------------

const dossier = fs.mkdtempSync(path.join(os.tmpdir(), 'novaterra-mesure-'));
const baseSqlite = path.join(dossier, 'mesure.sqlite');
fs.writeFileSync(baseSqlite, '');
const mdp = crypto.randomBytes(12).toString('hex'); // jetable, jamais écrit dans un fichier versionné

const envPhp = {
    ...process.env,
    APP_ENV: 'production', APP_DEBUG: 'false', APP_URL: BASE,
    DB_CONNECTION: 'sqlite', DB_DATABASE: baseSqlite,
    SESSION_DRIVER: 'database', CACHE_STORE: 'database', QUEUE_CONNECTION: 'sync',
    LOG_CHANNEL: 'null', MAIL_MAILER: 'array',
    WEBCUP_API_KEY: '', API_KEY: '',
    SEED_CITOYEN_PASSWORD: mdp, SEED_AGENT_PASSWORD: mdp, SEED_ADMIN_PASSWORD: mdp,
};
const php = (arguments_, options = {}) =>
    spawnSync('php', ['-d', 'extension=pdo_sqlite', ...arguments_], { cwd: RACINE, env: envPhp, encoding: 'utf8', ...options });

function preparerBase() {
    const garde = path.join(dossier, 'garde.php');
    fs.writeFileSync(garde, `<?php
require '${RACINE.replaceAll('\\', '/')}/vendor/autoload.php';
$app = require '${RACINE.replaceAll('\\', '/')}/bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
echo config('database.default').'|'.config('database.connections.sqlite.database');
`);
    const sortie = php([garde]).stdout.trim();
    const [connexion, fichier] = sortie.split('|');
    if (connexion !== 'sqlite' || path.resolve(fichier) !== path.resolve(baseSqlite)) {
        throw new Error(`Connexion inattendue (${sortie}) : la base de développement ne doit jamais être touchée.`);
    }

    for (const commande of [
        ['artisan', 'migrate', '--database=sqlite', '--force'],
        ['artisan', 'db:seed', '--database=sqlite', '--force'],
    ]) {
        const r = php(commande);
        if (r.status !== 0) throw new Error(`${commande.join(' ')} a échoué :\n${r.stdout}\n${r.stderr}`);
    }

    // Volume réaliste pour les listes de demandes (10 par page côté habitant, 15 côté agent).
    const volume = path.join(dossier, 'volume.php');
    fs.writeFileSync(volume, `<?php
require '${RACINE.replaceAll('\\', '/')}/vendor/autoload.php';
$app = require '${RACINE.replaceAll('\\', '/')}/bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
$u = App\\Models\\User::where('email', 'citoyen@novaterra.test')->firstOrFail();
App\\Models\\Demande::factory()->count(25)->create(['user_id' => $u->id]);
`);
    const r = php([volume]);
    if (r.status !== 0) throw new Error(`Volume de démonstration : ${r.stdout}\n${r.stderr}`);
}

async function attendre(url, essais = 60) {
    for (let i = 0; i < essais; i++) {
        const ok = await new Promise((res) => http.get(url, (r) => { r.resume(); res(true); }).on('error', () => res(false)));
        if (ok) return;
        await dormir(500);
    }
    throw new Error(`${url} ne répond pas`);
}

// --- Client CDP minimal ---------------------------------------------------------------------------------------

class Cdp {
    constructor(ws) {
        this.ws = ws;
        this.id = 0;
        this.attente = new Map();
        this.ecouteurs = new Set();
        ws.onmessage = (e) => {
            const m = JSON.parse(e.data);
            if (m.id) {
                const p = this.attente.get(m.id);
                this.attente.delete(m.id);
                if (m.error) p.rej(new Error(`${m.error.message}`)); else p.res(m.result);
            } else {
                this.ecouteurs.forEach((h) => h(m));
            }
        };
    }

    static async connecter(url) {
        const ws = new WebSocket(url);
        await new Promise((res, rej) => { ws.onopen = res; ws.onerror = rej; });
        return new Cdp(ws);
    }

    envoyer(method, params = {}, sessionId) {
        const id = ++this.id;
        return new Promise((res, rej) => {
            this.attente.set(id, { res, rej });
            this.ws.send(JSON.stringify({ id, method, params, sessionId }));
        });
    }
}

const TYPES = { Document: 'html', Script: 'js', Stylesheet: 'css', Font: 'polices', Image: 'images' };

async function ouvrirOnglet(cdp, contexte, bride) {
    const { targetId } = await cdp.envoyer('Target.createTarget', { url: 'about:blank', browserContextId: contexte });
    const { sessionId } = await cdp.envoyer('Target.attachToTarget', { targetId, flatten: true });
    const S = (m, p) => cdp.envoyer(m, p, sessionId);
    await S('Network.enable');
    await S('Page.enable');
    await S('Network.setCacheDisabled', { cacheDisabled: true });
    if (bride) {
        await S('Network.emulateNetworkConditions', {
            offline: false,
            latency: RESEAU.latence_ms,
            downloadThroughput: (RESEAU.debit_descendant_kbit_s * 1000) / 8,
            uploadThroughput: (RESEAU.debit_montant_kbit_s * 1000) / 8,
        });
    }
    return { S, sessionId, fermer: () => cdp.envoyer('Target.closeTarget', { targetId }) };
}

async function charger(cdp, contexte, url, bride) {
    const { S, sessionId, fermer } = await ouvrirOnglet(cdp, contexte, bride);
    const requetes = new Map();
    let charge;
    const chargee = new Promise((r) => { charge = r; });

    cdp.ecouteurs.add((m) => {
        if (m.sessionId !== sessionId) return;
        const p = m.params;
        if (m.method === 'Network.requestWillBeSent' && !p.redirectResponse) {
            requetes.set(p.requestId, { url: p.request.url, type: p.type, transfere: 0, decompresse: 0, echec: false });
        } else if (m.method === 'Network.responseReceived' && requetes.has(p.requestId)) {
            requetes.get(p.requestId).type = p.type;
            requetes.get(p.requestId).statut = p.response.status;
        } else if (m.method === 'Network.dataReceived' && requetes.has(p.requestId)) {
            requetes.get(p.requestId).decompresse += p.dataLength;
        } else if (m.method === 'Network.loadingFinished' && requetes.has(p.requestId)) {
            requetes.get(p.requestId).transfere = p.encodedDataLength;
        } else if (m.method === 'Network.loadingFailed' && requetes.has(p.requestId)) {
            requetes.get(p.requestId).echec = true;
        } else if (m.method === 'Page.loadEventFired') {
            charge();
        }
    });

    await S('Page.navigate', { url });
    await Promise.race([chargee, dormir(120000)]);
    await dormir(1500); // requêtes tardives éventuelles
    const { result } = await S('Runtime.evaluate', {
        returnByValue: true,
        expression: `JSON.stringify({
            charge: performance.getEntriesByType('navigation')[0].loadEventEnd,
            dcl: performance.getEntriesByType('navigation')[0].domContentLoadedEventEnd,
            fcp: (performance.getEntriesByType('paint').find(p => p.name === 'first-contentful-paint') || {}).startTime ?? null,
            chemin: location.pathname })`,
    });
    const temps = JSON.parse(result.value);
    await fermer().catch(() => {});

    const lignes = [...requetes.values()].filter((r) => /^https?:/.test(r.url));
    const parType = {};
    const tiers = {};
    for (const r of lignes) {
        const cle = TYPES[r.type] ?? 'autres';
        parType[cle] ??= { requetes: 0, transfere: 0, decompresse: 0 };
        parType[cle].requetes++;
        parType[cle].transfere += r.transfere;
        parType[cle].decompresse += r.decompresse;
        const hote = new URL(r.url).host;
        if (hote !== new URL(BASE).host) tiers[hote] = (tiers[hote] ?? 0) + 1;
    }
    return {
        transfere: lignes.reduce((s, r) => s + r.transfere, 0),
        decompresse: lignes.reduce((s, r) => s + r.decompresse, 0),
        requetes: lignes.length,
        echecs: lignes.filter((r) => r.echec).length,
        par_type: parType,
        domaines_tiers: Object.keys(tiers),
        charge_ms: Math.round(temps.charge),
        dom_pret_ms: Math.round(temps.dcl),
        premier_affichage_ms: temps.fcp === null ? null : Math.round(temps.fcp),
        chemin_final: temps.chemin,
    };
}

async function connecterCompte(cdp, contexte, courriel) {
    const { S, sessionId, fermer } = await ouvrirOnglet(cdp, contexte, false);
    let charge;
    const chargee = () => new Promise((r) => { charge = r; });
    cdp.ecouteurs.add((m) => { if (m.sessionId === sessionId && m.method === 'Page.loadEventFired') charge?.(); });

    let attente = chargee();
    await S('Page.navigate', { url: `${BASE}/login` });
    await Promise.race([attente, dormir(30000)]);
    console.log('  page de connexion chargée');
    attente = chargee();
    await S('Runtime.evaluate', {
        expression: `(() => { const f = document.querySelector('input[name=email]').form;
            f.querySelector('input[name=email]').value = ${JSON.stringify(courriel)};
            f.querySelector('input[name=password]').value = ${JSON.stringify(mdp)};
            f.submit(); })()`,
    });
    await Promise.race([attente, dormir(30000)]);
    console.log('  formulaire envoyé');
    await dormir(500);
    const { result } = await S('Runtime.evaluate', { expression: 'location.pathname', returnByValue: true });
    const { result: texte } = await S('Runtime.evaluate', { expression: 'document.body.innerText.slice(0, 400)', returnByValue: true });
    await fermer();
    if (result.value === '/login') throw new Error(`Connexion refusée pour ${courriel} : ${texte.value}`);
}

// --- Orchestration --------------------------------------------------------------------------------------------

const processus = [];
let nettoye = false;
function nettoyer() {
    if (nettoye) return;
    nettoye = true;
    processus.forEach((p) => { try { p.kill(); } catch {} });
    setTimeout(() => fs.rmSync(dossier, { recursive: true, force: true }), 1500);
}
process.on('exit', nettoyer);

try {
    if (!fs.existsSync(path.join(RACINE, 'public', 'build', 'manifest.json'))) throw new Error('Lancez d\'abord : npm run build');
    if (fs.existsSync(path.join(RACINE, 'public', 'hot'))) throw new Error('public/hot existe : le serveur Vite est actif, mesure refusée.');

    console.log('Préparation de la base jetable…');
    preparerBase();

    // Le routeur de Laravel cherche index.php dans le dossier courant : on se place dans public/.
    const serveurPhp = spawn('php', ['-d', 'extension=pdo_sqlite', '-S', `127.0.0.1:${PORT_PHP}`,
        path.join(RACINE, 'vendor', 'laravel', 'framework', 'src', 'Illuminate', 'Foundation', 'resources', 'server.php')],
        { cwd: path.join(RACINE, 'public'), env: envPhp, stdio: 'ignore' });
    processus.push(serveurPhp);
    const mandataire = await demarrerMandataire({ port: PORT_MANDATAIRE, cible: PORT_PHP, regles });
    await attendre(`${BASE}/services`);
    console.log('Serveur prêt.');

    const edge = spawn(EDGE, ['--headless=new', `--remote-debugging-port=${PORT_CDP}`, `--user-data-dir=${path.join(dossier, 'edge')}`,
        '--no-first-run', '--no-default-browser-check', '--disable-extensions', '--disable-background-networking',
        '--disable-component-update', '--disable-sync', 'about:blank'], { stdio: 'ignore' });
    processus.push(edge);
    await attendre(`http://127.0.0.1:${PORT_CDP}/json/version`);
    const version = await new Promise((res) => http.get(`http://127.0.0.1:${PORT_CDP}/json/version`, (r) => {
        let d = ''; r.on('data', (c) => (d += c)); r.on('end', () => res(JSON.parse(d)));
    }));
    const cdp = await Cdp.connecter(version.webSocketDebuggerUrl);
    console.log('Navigateur prêt :', version.Browser);

    const contextes = {};
    for (const [role, courriel] of [['invité', null], ['citoyen', 'citoyen@novaterra.test'], ['agent', 'agent@novaterra.test']]) {
        const { browserContextId } = await cdp.envoyer('Target.createBrowserContext');
        contextes[role] = browserContextId;
        if (courriel) {
            await connecterCompte(cdp, browserContextId, courriel);
            console.log('Connecté :', role);
        }
    }

    const pages = [];
    for (const { chemin, role } of PAGES) {
        const essais = [];
        for (let i = 0; i < runs; i++) essais.push(await charger(cdp, contextes[role], BASE + chemin, true));
        const premier = essais[0];
        if (premier.chemin_final !== chemin) console.warn(`Attention : ${chemin} a abouti sur ${premier.chemin_final}`);
        pages.push({
            chemin, role,
            transfere_octets: premier.transfere,
            decompresse_octets: premier.decompresse,
            requetes: premier.requetes,
            echecs: premier.echecs,
            par_type: premier.par_type,
            domaines_tiers: premier.domaines_tiers,
            premier_affichage_ms: mediane(essais.map((e) => e.premier_affichage_ms ?? e.charge_ms)),
            chargement_ms: mediane(essais.map((e) => e.charge_ms)),
        });
        console.log(`  ${role.padEnd(8)} ${chemin.padEnd(18)} ${String(premier.transfere).padStart(8)} o  ${premier.requetes} req  ${mediane(essais.map((e) => e.charge_ms))} ms`);
    }

    const mesure = {
        date: new Date().toISOString(),
        environnement: {
            navigateur: version.Browser,
            node: process.version,
            php: php(['-r', 'echo PHP_VERSION;']).stdout.trim(),
            application: 'production (APP_ENV=production, APP_DEBUG=false), build de public/build, base SQLite jetable remplie par les seeders + 25 demandes',
            reseau: RESEAU,
            cache_navigateur: 'désactivé (chargement à froid)',
            chargements_par_page: runs,
            regles_serveur: regles === 'htaccess'
                ? 'règles de public/.htaccess reproduites par un mandataire (gzip des textes, cache immuable sur /build)'
                : 'aucune (état d\'origine : ni compression ni cache long)',
        },
        pages,
    };
    const existant = fs.existsSync(SORTIE) ? JSON.parse(fs.readFileSync(SORTIE, 'utf8')) : {};
    existant[etape] = mesure;
    fs.mkdirSync(path.dirname(SORTIE), { recursive: true });
    fs.writeFileSync(SORTIE, JSON.stringify(existant, null, 4) + '\n');
    console.log(`\nRésultats écrits dans ${path.relative(RACINE, SORTIE)} (${etape}).`);

    mandataire.close();
    cdp.ws.close();
} catch (e) {
    console.error(e.message);
    process.exitCode = 1;
} finally {
    nettoyer();
    setTimeout(() => process.exit(process.exitCode ?? 0), 2000);
}
