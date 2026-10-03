// Vérifie dans un vrai navigateur (Edge sans interface) que la Content-Security-Policy en mode « enforce » ne casse pas
// les menus Alpine : menu mobile (clic et clavier, Échap), menu du compte, déconnexion par bouton.
//
//   npm run build
//   node scripts/verifier-csp.mjs
//
// Même principe que mesure-poids.mjs : base SQLite jetable, serveur PHP intégré, aucun accès à la base de développement.
import { spawn, spawnSync } from 'node:child_process';
import crypto from 'node:crypto';
import fs from 'node:fs';
import http from 'node:http';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const RACINE = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const EDGE = process.env.EDGE_PATH ?? 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe';
const PORT_PHP = 8123;
const PORT_CDP = 9334;
const BASE = `http://127.0.0.1:${PORT_PHP}`;
const dormir = (ms) => new Promise((r) => setTimeout(r, ms));

const dossier = fs.mkdtempSync(path.join(os.tmpdir(), 'novaterra-csp-'));
const baseSqlite = path.join(dossier, 'csp.sqlite');
fs.writeFileSync(baseSqlite, '');
const mdp = crypto.randomBytes(12).toString('hex');
const env = {
    ...process.env, APP_ENV: 'production', APP_DEBUG: 'false', APP_URL: BASE, DB_CONNECTION: 'sqlite', DB_DATABASE: baseSqlite,
    SESSION_DRIVER: 'database', CACHE_STORE: 'database', LOG_CHANNEL: 'null', WEBCUP_API_KEY: '', API_KEY: '',
    NOVATERRA_CSP_MODE: process.env.NOVATERRA_CSP_MODE ?? 'enforce',
    SEED_CITOYEN_PASSWORD: mdp, SEED_AGENT_PASSWORD: mdp, SEED_ADMIN_PASSWORD: mdp,
};
const php = (a) => spawnSync('php', ['-d', 'extension=pdo_sqlite', ...a], { cwd: RACINE, env, encoding: 'utf8' });
const processus = [];
const resultats = [];
const verifier = (nom, ok, detail = '') => { resultats.push({ nom, ok }); console.log(`${ok ? 'OK    ' : 'ÉCHEC '} ${nom}${detail ? ` — ${detail}` : ''}`); };

async function attendre(url) {
    for (let i = 0; i < 60; i++) {
        if (await new Promise((res) => http.get(url, (r) => { r.resume(); res(true); }).on('error', () => res(false)))) return;
        await dormir(500);
    }
    throw new Error(`${url} ne répond pas`);
}

class Cdp {
    constructor(ws) {
        this.ws = ws; this.id = 0; this.attente = new Map(); this.ecouteurs = [];
        ws.onmessage = (e) => {
            const m = JSON.parse(e.data);
            if (m.id) { const p = this.attente.get(m.id); this.attente.delete(m.id); m.error ? p.rej(new Error(m.error.message)) : p.res(m.result); } else this.ecouteurs.forEach((h) => h(m));
        };
    }
    static async connecter(url) { const ws = new WebSocket(url); await new Promise((res, rej) => { ws.onopen = res; ws.onerror = rej; }); return new Cdp(ws); }
    envoyer(method, params = {}, sessionId) { const id = ++this.id; return new Promise((res, rej) => { this.attente.set(id, { res, rej }); this.ws.send(JSON.stringify({ id, method, params, sessionId })); }); }
}

try {
    for (const c of [['artisan', 'migrate', '--database=sqlite', '--force'], ['artisan', 'db:seed', '--database=sqlite', '--force']]) {
        const r = php(c);
        if (r.status !== 0) throw new Error(`${c.join(' ')} : ${r.stdout}${r.stderr}`);
    }

    processus.push(spawn('php', ['-d', 'extension=pdo_sqlite', '-S', `127.0.0.1:${PORT_PHP}`,
        path.join(RACINE, 'vendor', 'laravel', 'framework', 'src', 'Illuminate', 'Foundation', 'resources', 'server.php')],
        { cwd: path.join(RACINE, 'public'), env, stdio: 'ignore' }));
    await attendre(`${BASE}/services`);

    processus.push(spawn(EDGE, ['--headless=new', `--remote-debugging-port=${PORT_CDP}`, `--user-data-dir=${path.join(dossier, 'edge')}`,
        '--no-first-run', '--disable-extensions', 'about:blank'], { stdio: 'ignore' }));
    await attendre(`http://127.0.0.1:${PORT_CDP}/json/version`);
    const version = await new Promise((res) => http.get(`http://127.0.0.1:${PORT_CDP}/json/version`, (r) => { let d = ''; r.on('data', (c) => (d += c)); r.on('end', () => res(JSON.parse(d))); }));
    const cdp = await Cdp.connecter(version.webSocketDebuggerUrl);
    console.log(`Navigateur : ${version.Browser} — mode CSP : ${env.NOVATERRA_CSP_MODE}\n`);

    const { targetId } = await cdp.envoyer('Target.createTarget', { url: 'about:blank' });
    const { sessionId } = await cdp.envoyer('Target.attachToTarget', { targetId, flatten: true });
    const S = (m, p) => cdp.envoyer(m, p, sessionId);
    const violations = [];
    let charge;
    cdp.ecouteurs.push((m) => {
        if (m.sessionId !== sessionId) return;
        if (m.method === 'Log.entryAdded' && /Content Security Policy|Refused to/i.test(m.params.entry.text)) violations.push(m.params.entry.text);
        if (m.method === 'Runtime.consoleAPICalled' && /Content Security Policy|Refused to/i.test(JSON.stringify(m.params.args))) violations.push(JSON.stringify(m.params.args));
        if (m.method === 'Runtime.exceptionThrown') violations.push(`exception : ${m.params.exceptionDetails.text} ${m.params.exceptionDetails.exception?.description ?? ''}`);
        if (m.method === 'Page.loadEventFired') charge?.();
    });
    await S('Page.enable'); await S('Runtime.enable'); await S('Log.enable');

    const aller = async (url) => { const p = new Promise((r) => { charge = r; }); await S('Page.navigate', { url }); await Promise.race([p, dormir(15000)]); await dormir(600); };
    const evaluer = async (expression) => (await S('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true })).result.value;
    const touche = async (key, code) => { for (const type of ['keyDown', 'keyUp']) await S('Input.dispatchKeyEvent', { type, key, code, windowsVirtualKeyCode: key === 'Escape' ? 27 : 13, ...(key === 'Enter' && type === 'keyDown' ? { text: '\r' } : {}) }); await dormir(300); };

    // 1. Connexion (les menus Alpine n'existent que pour un utilisateur connecté), puis le script s'exécute malgré la CSP.
    await S('Emulation.setDeviceMetricsOverride', { width: 400, height: 800, deviceScaleFactor: 1, mobile: true });
    await aller(`${BASE}/login`);
    await evaluer(`(() => { const f = document.querySelector('input[name=email]').form; f.querySelector('input[name=email]').value = 'citoyen@novaterra.test'; f.querySelector('input[name=password]').value = ${JSON.stringify(mdp)}; f.submit(); })()`);
    await dormir(2500);
    verifier('connexion possible sous la CSP', (await evaluer('location.pathname')) === '/espace');
    verifier('Alpine démarre sous la CSP', (await evaluer('typeof window.Alpine === "object"')) === true);

    // 2. Menu mobile : clic.
    const menuOuvert = () => evaluer(`getComputedStyle(document.getElementById('menu-mobile')).display !== 'none'`);
    const burger = `document.querySelector('button[aria-controls="menu-mobile"]')`;
    verifier('menu mobile fermé au départ', (await menuOuvert()) === false);
    await evaluer(`${burger}.click()`); await dormir(300);
    verifier('menu mobile ouvert au clic', (await menuOuvert()) === true && (await evaluer(`${burger}.getAttribute('aria-expanded')`)) === 'true');

    // 3. Clavier : Échap referme et rend le focus au bouton.
    await touche('Escape', 'Escape');
    verifier('Échap referme le menu mobile', (await menuOuvert()) === false);
    verifier('Échap rend le focus au bouton', (await evaluer(`document.activeElement === ${burger}`)) === true);

    // 4. Clavier : Entrée sur le bouton focalisé l'ouvre.
    await evaluer(`${burger}.focus()`); await touche('Enter', 'Enter');
    verifier('Entrée ouvre le menu mobile', (await menuOuvert()) === true);

    // 5. Menu du compte (bureau) et déconnexion par bouton.
    await S('Emulation.setDeviceMetricsOverride', { width: 1280, height: 800, deviceScaleFactor: 1, mobile: false });
    await aller(`${BASE}/espace`);
    const bouton = `document.querySelector('nav [aria-haspopup="true"]')`;
    const contenu = `document.querySelector('nav [aria-haspopup="true"]').closest('[x-data]').querySelector('[x-cloak], [x-show]')`;
    verifier('menu du compte fermé au départ', (await evaluer(`getComputedStyle(${contenu}).display === 'none'`)) === true);
    await evaluer(`${bouton}.click()`); await dormir(500);
    verifier('menu du compte ouvert au clic', (await evaluer(`getComputedStyle(${contenu}).display !== 'none'`)) === true);
    await touche('Escape', 'Escape');
    verifier('Échap referme le menu du compte', (await evaluer(`getComputedStyle(${contenu}).display === 'none'`)) === true);
    await evaluer(`${bouton}.click()`); await dormir(500);
    await evaluer(`document.querySelector('nav form[action$="/logout"] button[type=submit]').click()`);
    await dormir(2000);
    verifier('déconnexion par bouton (sans gestionnaire en ligne)', (await evaluer('location.pathname')) === '/' );

    // 6. Aucune violation de CSP ni exception de script pendant tout le parcours.
    verifier('aucune violation de CSP ni exception JavaScript', violations.length === 0, violations.slice(0, 3).join(' | '));
    cdp.ws.close();
} catch (e) {
    console.error(e.message);
    resultats.push({ nom: 'exécution', ok: false });
} finally {
    processus.forEach((p) => { try { p.kill(); } catch {} });
    setTimeout(() => { fs.rmSync(dossier, { recursive: true, force: true }); process.exit(resultats.every((r) => r.ok) ? 0 : 1); }, 1500);
}
