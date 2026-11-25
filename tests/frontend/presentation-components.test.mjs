import assert from 'node:assert/strict';
import test from 'node:test';
import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { FileText, FileSpreadsheet } from 'lucide-vue-next';
import { loadVue } from './load-vue.mjs';

const ReportExportLink = await loadVue('resources/js/components/ui/ReportExportLink.vue');
const StudentPhotoComparison = await loadVue('resources/js/components/ui/StudentPhotoComparison.vue');
const render = vnode => renderToString(createSSRApp({ render: () => vnode }));
const markup = html => html.replace(/<!--[\s\S]*?-->/g, '');

test('export links preserve the original anchors, icons and download attributes', async () => {
    for (const format of ['pdf', 'excel']) {
        for (const size of [15, 16]) {
            const attrs = { href: `/exportar/${format}?id_examen=5`, title: 'Descargar reporte' };
            if (format === 'pdf') attrs.target = '_blank';
            const actual = await render(h(ReportExportLink, { format, iconSize: size, ...attrs }));
            const original = await render(h('a', { class: `btn-export btn-${format}`, ...attrs }, [
                h(format === 'pdf' ? FileText : FileSpreadsheet, { size }),
                h('span', `Exportar ${format === 'pdf' ? 'PDF' : 'Excel'}`),
            ]));
            assert.equal(markup(actual), markup(original));
        }
    }
});

test('photo comparison preserves independent image errors and missing-photo placeholders', async () => {
    for (const referenceError of [false, true]) {
        for (const currentError of [false, true]) {
            const html = await render(h(StudentPhotoComparison, {
                name: 'Ana', initials: 'AP', referenceUrl: '/referencia.jpg', currentUrl: '/actual.jpg',
                referenceError, currentError,
            }));
            assert.equal(html.includes('src="/referencia.jpg"'), !referenceError);
            assert.equal(html.includes('src="/actual.jpg"'), !currentError);
            assert.equal(html.includes('Sin foto de referencia'), referenceError);
            assert.equal(html.includes('Sin foto actual'), currentError);
            if (!referenceError) assert.match(html, /alt="Foto de Referencia de Ana"/);
            if (!currentError) assert.match(html, /alt="Foto Actual de Ana"/);
        }
    }
    const missing = await render(h(StudentPhotoComparison, { initials: 'AP' }));
    assert.equal((missing.match(/class="placeholder-initials">AP/g) || []).length, 2);
    assert.doesNotMatch(missing, /<img/);
});

test('photo load failures notify the parent independently', () => {
    const events = [];
    const renderPhotos = StudentPhotoComparison.setup({
        referenceUrl: '/referencia.jpg', currentUrl: '/actual.jpg', name: 'Ana',
    }, { expose() {} });
    const tree = renderPhotos({ $emit: name => events.push(name) }, []);
    const images = [];
    function visit(vnode) {
        if (vnode.type === 'img') images.push(vnode);
        if (Array.isArray(vnode.children)) vnode.children.forEach(visit);
    }
    visit(tree);
    assert.equal(images.length, 2);
    images[0].props.onError();
    images[1].props.onError();
    assert.deepEqual(events, ['reference-error', 'current-error']);
});
