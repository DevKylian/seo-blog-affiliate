import { chromium } from 'playwright-core';

const [url, executablePath] = process.argv.slice(2);
if (!url || !executablePath) {
    process.stderr.write('Usage: capture-pricing.mjs <url> <chrome>\n');
    process.exit(2);
}

const launchArgs = ['--disable-dev-shm-usage', '--disable-extensions', '--no-first-run'];
if (typeof process.getuid === 'function' && process.getuid() === 0) {
    // Chrome refuse son sandbox classique dans de nombreux conteneurs Linux
    // exécutés en root. Les serveurs non-root conservent le sandbox natif.
    launchArgs.push('--no-sandbox', '--disable-setuid-sandbox');
}

const browser = await chromium.launch({
    executablePath,
    headless: true,
    args: launchArgs,
});

const context = await browser.newContext({
    locale: 'fr-FR',
    userAgent: 'BlogSEOResearchBot/1.0 (+https://blogseo.test)',
});
const page = await context.newPage();
const jsonPayloads = [];
const capturedResponseKeys = new Set();
const responseTasks = new Set();
const pricingOrigin = new URL(url).origin;

const watchResponses = (targetPage) => targetPage.on('response', (response) => {
    const task = (async () => {
        const request = response.request();
        if (!['xhr', 'fetch'].includes(request.resourceType()) || !response.ok()) return;
        const contentType = (response.headers()['content-type'] || '').toLowerCase();
        if (!contentType.includes('json')) return;

        const body = await response.text().catch(() => '');
        if (!body || body.length > 500_000) return;
        const normalized = body.toLowerCase();
        const hasPrice = /"(?:price|amount|monthly_price|monthlyprice|annual_price|annualprice|plans|offers|tiers)"/.test(normalized);
        const hasCommercialContext = /(?:€|eur|usd|gbp|month|mois|year|annuel|pricing|tarif|subscription|abonnement)/.test(normalized);
        if (!hasPrice || !hasCommercialContext) return;
        const responseUrl = new URL(response.url());
        const relevantEndpoint = /pricing|tarifs?|prices?|plans?|offers?|subscriptions?|billing|tiers?/i.test(responseUrl.pathname);
        const hasOfferCollection = /"(?:plans|offers|subscriptions|tiers|packages)"\s*:/.test(normalized);
        if (!relevantEndpoint && !(responseUrl.origin === pricingOrigin && hasOfferCollection)) return;

        const responseKey = `${response.url()}|${body.length}|${body.slice(0, 300)}`;
        if (capturedResponseKeys.has(responseKey)) return;
        capturedResponseKeys.add(responseKey);

        const data = JSON.parse(body);
        jsonPayloads.push({ url: response.url(), data });
    })().catch(() => {});
    responseTasks.add(task);
    task.finally(() => responseTasks.delete(task));
});
watchResponses(page);

const snapshots = [];
const rememberDom = async (targetPage = page) => {
    const html = await targetPage.content();
    if (html.length <= 3_000_000 && !snapshots.includes(html)) snapshots.push(html);
};

const scrollPage = async (targetPage) => targetPage.evaluate(async () => {
    const height = Math.max(document.body.scrollHeight, document.documentElement.scrollHeight);
    for (let y = 0; y <= height; y += Math.max(500, window.innerHeight * 0.8)) {
        window.scrollTo(0, y);
        await new Promise((resolve) => setTimeout(resolve, 100));
    }
    window.scrollTo(0, 0);
});

try {
    const mainResponse = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 35_000 });
    await page.waitForLoadState('networkidle', { timeout: 10_000 }).catch(() => {});

    // Déclenche les sections chargées au défilement sans cliquer sur un CTA.
    await scrollPage(page);
    await page.waitForTimeout(500);
    await rememberDom();

    // Capture au maximum deux états de bascule tarifaire. Les autres profils
    // restent généralement présents dans le DOM rendu sous forme d'onglets.
    const toggles = page.locator('button, [role="tab"], label').filter({
        hasText: /^\s*(mensuel(?:le)?|annuel(?:le)?|monthly|yearly|annual)\b/i,
    }).or(page.locator('[aria-label*="fréquence" i], [aria-label*="frequency" i]'));
    const toggleCount = Math.min(await toggles.count(), 5);
    for (let index = 0; index < toggleCount && snapshots.length < 3; index += 1) {
        const toggle = toggles.nth(index);
        if (!(await toggle.isVisible().catch(() => false))) continue;
        await toggle.click({ timeout: 2_000 }).catch(() => {});
        await page.waitForTimeout(1_500);
        await rememberDom();
    }

    const profileButtons = page.locator('button, [role="tab"]').filter({
        hasText: /(?:micro.?entreprise|avec tva|service à la personne|\bEI\b|\bSARL\b|\bSASU?\b|\bEURL\b|\bSCI\b|salarié|employees?)/i,
    });
    const profileButtonCount = Math.min(await profileButtons.count(), 6);
    for (let index = 0; index < profileButtonCount && snapshots.length < 8; index += 1) {
        const profileButton = profileButtons.nth(index);
        if (!(await profileButton.isVisible().catch(() => false))) continue;
        await profileButton.click({ timeout: 2_000 }).catch(() => {});
        await page.waitForTimeout(1_000);
        await rememberDom();
    }

    // Certains sites exposent les profils (indépendants, PME, 51+ salariés)
    // sous forme de sous-pages tarifaires. Elles sont rendues en parallèle.
    const profileUrls = await page.locator('a[href]').evaluateAll((links, baseUrl) => {
        const base = new URL(baseUrl);
        return [...new Set(links.map((link) => {
            const text = (link.textContent || '').replace(/\s+/g, ' ').trim();
            if (!/(?:indépendant|salarié|employé|employees?|51\s*\+)/i.test(text)) return null;
            const target = new URL(link.href, base);
            if (target.origin !== base.origin || !/tarifs|pricing/i.test(target.pathname)) return null;
            return target.href;
        }).filter(Boolean))];
    }, url);

    const profileTargets = profileUrls.filter((target) => target !== page.url()).slice(0, 6);
    for (let offset = 0; offset < profileTargets.length; offset += 3) {
        const batch = profileTargets.slice(offset, offset + 3);
        await Promise.all(batch.map(async (target) => {
            const profilePage = await context.newPage();
            watchResponses(profilePage);
            try {
                await profilePage.goto(target, { waitUntil: 'domcontentloaded', timeout: 18_000 });
                await profilePage.waitForTimeout(500);
                await scrollPage(profilePage);
                await rememberDom(profilePage);
            } catch {
                // Une sous-page inaccessible ne doit pas invalider la page principale.
            } finally {
                await profilePage.close();
            }
        }));
    }

    // Une réponse XHR interrompue ou Chrome lui-même peuvent conserver une
    // promesse ouverte alors que toutes les données utiles sont déjà prêtes.
    // On ne laisse jamais ce nettoyage retenir le worker PHP indéfiniment.
    await Promise.race([
        Promise.allSettled([...responseTasks]),
        new Promise((resolve) => setTimeout(resolve, 2_000)),
    ]);
    const output = JSON.stringify({
        html_snapshots: snapshots.slice(0, 8),
        json_payloads: jsonPayloads.slice(0, 20),
        http_status: mainResponse?.status() || 200,
    });

    await Promise.race([
        browser.close().catch(() => {}),
        new Promise((resolve) => setTimeout(resolve, 2_000)),
    ]);
    process.stdout.write(output, () => process.exit(0));
} finally {
    if (browser.isConnected()) {
        await Promise.race([
            browser.close().catch(() => {}),
            new Promise((resolve) => setTimeout(resolve, 2_000)),
        ]);
    }
}
