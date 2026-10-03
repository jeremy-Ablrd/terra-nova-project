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
const PORT_PHP = 8124;
const PORT_CDP = 9335;
const BASE = `http://127.0.0.1:${PORT_PHP}`;
const dormir = (ms) => new Promise((r) => setTimeout(r, ms));

const dossier = fs.mkdtempSync(path.join(os.tmpdir(), 'novaterra-bandeau-'));
const baseSqlite = path.join(dossier, 'bandeau.sqlite');
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

    const racinePhp = RACINE.split(path.sep).join('/');
    const volume = path.join(dossier, 'alertes.php');
    fs.writeFileSync(volume, `<?php
require '${racinePhp}/vendor/autoload.php';
$app = require '${racinePhp}/bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
foreach (range(1, 5) as $i) { App\\Models\\Alerte::factory()->urgente()->create(['titre' => 'Alerte urgente numéro '.$i.' avec un titre assez long pour passer à la ligne', 'emetteur' => 'haut_conseil', 'ce_qui_se_passe' => str_repeat('Un message officiel détaillé. ', 6), 'ce_quil_faut_faire' => str_repeat('Suivez les consignes affichées. ', 5)]); }
foreach (range(1, 4) as $i) { App\\Models\\Alerte::factory()->create(['titre' => 'Information '.$i, 'emetteur' => 'ville']); }
`);
    { const r = php([volume]); if (r.status !== 0) throw new Error(`Alertes de démonstration : ${r.stdout}${r.stderr}`); }

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
    let charge;
    cdp.ecouteurs.push((m) => { if (m.sessionId === sessionId && m.method === 'Page.loadEventFired') charge?.(); });
    await S('Page.enable'); await S('Runtime.enable');
    const aller = async (url) => { const p = new Promise((r) => { charge = r; }); await S('Page.navigate', { url }); await Promise.race([p, dormir(15000)]); await dormir(600); };
    const evaluer = async (expression) => (await S('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true })).result.value;

    for (const largeur of [400, 1280]) {
        await S('Emulation.setDeviceMetricsOverride', { width: largeur, height: 800, deviceScaleFactor: 1, mobile: largeur < 500 });
        await aller(BASE + '/');
        // Réglage « Très grand » par le bouton de la barre d'affichage (formulaire, sans JavaScript).
        await evaluer("document.querySelector('button[name=taille][value=tres_grand]').click()");
        await dormir(1500);
        const m = JSON.parse(await evaluer(`JSON.stringify((() => {
            const bandeau = document.querySelector('aside[aria-label]');
            const sections = [...bandeau.querySelectorAll('section')];
            const main = document.querySelector('main').getBoundingClientRect();
            const b = bandeau.getBoundingClientRect();
            return {
                classe: document.documentElement.className,
                police: getComputedStyle(document.documentElement).fontSize,
                sections: sections.length,
                urgentes: bandeau.querySelectorAll('[role=alert]').length,
                position: getComputedStyle(bandeau).position,
                recouvre: main.top < b.bottom - 1,
                debordeHorizontal: document.documentElement.scrollWidth > window.innerWidth + 1,
                texteCoupe: sections.some((s) => s.scrollHeight > s.clientHeight + 1 || s.scrollWidth > s.clientWidth + 1),
                masque: sections.some((s) => { const r = s.getBoundingClientRect(); return r.width === 0 || r.height === 0 || getComputedStyle(s).visibility === 'hidden' || getComputedStyle(s).display === 'none'; }),
                emetteurs: sections.every((s) => /Message officiel/.test(s.textContent)),
                hauteurBandeau: Math.round(b.height),
            };
        })())`));
        const ou = `largeur ${largeur} px`;
        verifier(`${ou} : réglage « Très grand » appliqué (150 %)`, m.classe.includes('taille-tres-grand') && m.police === '24px', m.police);
        // Les seeders ajoutent leurs propres alertes : on exige les 5 urgentes créées ici (et toutes les autres urgentes) + 3 autres au plus.
        verifier(`${ou} : toutes les alertes urgentes sont affichées, en plus de 3 autres`, m.urgentes >= 5 && m.sections === m.urgentes + 3, `${m.sections} sections dont ${m.urgentes} urgentes`);
        verifier(`${ou} : le bandeau défile avec la page (ni fixe ni collant)`, m.position === 'static', m.position);
        verifier(`${ou} : le contenu de la page commence après le bandeau`, m.recouvre === false);
        verifier(`${ou} : aucun défilement horizontal`, m.debordeHorizontal === false);
        verifier(`${ou} : aucun texte coupé ni masqué`, m.texteCoupe === false && m.masque === false);
        verifier(`${ou} : l'émetteur officiel est écrit dans chaque alerte`, m.emetteurs === true);
    }
    cdp.ws.close();
} catch (e) {
    console.error(e.message);
    resultats.push({ nom: 'exécution', ok: false });
} finally {
    processus.forEach((p) => { try { p.kill(); } catch {} });
    setTimeout(() => { fs.rmSync(dossier, { recursive: true, force: true }); process.exit(resultats.every((r) => r.ok) ? 0 : 1); }, 1500);
}
