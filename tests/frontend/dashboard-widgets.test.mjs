import assert from 'node:assert/strict';
import test from 'node:test';
import { createSSRApp, h } from 'vue';
import { renderToString } from '@vue/server-renderer';
import { loadVue } from './load-vue.mjs';

const OfficialLinks = await loadVue('resources/js/components/ui/OfficialLinks.vue');
const DashboardNews = await loadVue('resources/js/components/ui/DashboardNews.vue');
const render = (component, props) => renderToString(createSSRApp({ render: () => h(component, props) }));

test('OfficialLinks preserves links, colors and each dashboard class set', async () => {
    const links = [{ url: 'https://www.umss.edu.bo/', titulo: 'UMSS', desc: 'Portal', color: '#123456' }];
    for (const [variant, classes] of [['admin', ['link-item', 'link-title', 'link-desc']], ['docente', ['official-link-card', 'official-link-title', 'official-link-desc']]]) {
        const html = await render(OfficialLinks, { links, variant });
        assert.match(html, /href="https:\/\/www.umss.edu.bo\/" target="_blank" rel="noopener"/);
        assert.match(html, /style="border-left-color:#123456;"/);
        for (const name of classes) assert.ok(html.includes(`class="${name}"`));
        assert.match(html, />UMSS<\/span>/);
        assert.match(html, />Portal<\/span>/);
    }
});

test('DashboardNews distinguishes missing news from an empty list', async () => {
    const fallback = await render(DashboardNews, { news: null });
    assert.match(fallback, /class="news-fallback"/);
    assert.match(fallback, /href="https:\/\/www.umss.edu.bo\/categoria\/noticias\/" target="_blank" class="news-fallback-link"/);
    const empty = await render(DashboardNews, { news: [] });
    assert.match(empty, /class="news-list"/);
    assert.doesNotMatch(empty, /news-fallback/);
});

test('DashboardNews preserves each variant and its missing-link behavior', async () => {
    const news = [{ titulo: 'Aviso', fecha: '09/11/2026', resumen: 'Información' }];
    const admin = await render(DashboardNews, { news });
    assert.match(admin, /<a target="_blank" rel="noopener" class="news-item">/);
    for (const name of ['news-fecha', 'news-titulo', 'news-texto']) assert.ok(admin.includes(`class="${name}"`));
    const docente = await render(DashboardNews, { news, variant: 'docente' });
    assert.match(docente, /href="https:\/\/www.umss.edu.bo\/categoria\/noticias\/" target="_blank" rel="noopener" class="news-card-item"/);
    for (const name of ['news-card-date', 'news-card-title', 'news-card-summary']) assert.ok(docente.includes(`class="${name}"`));
    assert.match(await render(DashboardNews, { news: [{ ...news[0], enlace: '/aviso' }] }), /href="\/aviso"/);
});

test('Welcome preserves the three protocol cards and their animation delays', async () => {
    const Welcome = await loadVue('resources/js/Pages/Welcome.vue', {
        '@inertiajs/vue3': new URL('./inertia-stub.mjs', import.meta.url).href,
    });
    const html = await render(Welcome);
    assert.equal((html.match(/class="step-card reveal delay-\d+"/g) || []).length, 3);
    for (const delay of ['delay-100', 'delay-200', 'delay-300']) assert.ok(html.includes(`class="step-card reveal ${delay}"`));
    for (const number of ['01', '02', '03']) assert.ok(html.includes(`class="step-number">${number}</span>`));
    for (const title of ['Autenticación', 'Escaneo Rápido', 'Confirmación']) assert.ok(html.includes(`>${title}</h3>`));
});
