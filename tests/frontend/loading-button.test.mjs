import assert from 'node:assert/strict';
import test from 'node:test';
import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { loadVue } from './load-vue.mjs';

const LoadingButton = await loadVue('resources/js/components/ui/LoadingButton.vue');
const Button = await loadVue('resources/js/components/ui/Button.vue');
const LoadingSpinner = await loadVue('resources/js/components/ui/LoadingSpinner.vue');
const render = component => renderToString(createSSRApp({ render: () => component }));

test('LoadingButton preserves Button markup, attributes and disabled states', async () => {
    for (const loading of [false, true]) {
        for (const disabled of [false, true]) {
            for (const variant of ['primary', 'danger']) {
                const attrs = { variant, disabled, type: 'submit', class: 'btn-modal', 'aria-label': 'Guardar' };
                const actual = await render(h(LoadingButton, { ...attrs, loading }, () => 'Guardar cambios'));
                const original = await render(h(Button, attrs, () => loading
                    ? h(LoadingSpinner, { size: 'small' })
                    : h('span', 'Guardar cambios')));

                // Slot boundary comments are not visible DOM elements.
                const visibleMarkup = html => html.replace(/<!--[\s\S]*?-->/g, '');
                assert.equal(visibleMarkup(actual), visibleMarkup(original));
                assert.equal(actual.includes('spinner-wrapper small'), loading);
                assert.equal(actual.includes('Guardar cambios'), !loading);
            }
        }
    }
});
