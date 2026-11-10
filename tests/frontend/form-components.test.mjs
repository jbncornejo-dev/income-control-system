import assert from 'node:assert/strict';
import test from 'node:test';
import { createRenderer, createSSRApp, h, nextTick, ref } from 'vue';
import { renderToString } from '@vue/server-renderer';
import { loadVue } from './load-vue.mjs';

const FormField = await loadVue('resources/js/components/ui/FormField.vue');
const PasswordField = await loadVue('resources/js/components/ui/PasswordField.vue');
const render = (component, props, slots) => renderToString(createSSRApp({ render: () => h(component, props, slots) }));

test('FormField preserves its wrapper, slot, error tag and hidden error state', async () => {
    const slot = { default: () => h('input', { id: 'nombre', required: true }) };
    const plain = await render(FormField, {}, slot);
    assert.match(plain, /^<div class="form-group">/);
    assert.match(plain, /<input id="nombre" required>/);
    assert.doesNotMatch(plain, /error-msg/);
    assert.match(await render(FormField, { error: 'Campo obligatorio' }, slot), /<span class="error-msg">Campo obligatorio<\/span>/);
    assert.match(await render(FormField, { error: 'Revisar', errorTag: 'p' }, slot), /<p class="error-msg">Revisar<\/p>/);
});

test('PasswordField keeps input validation, labels and field errors', async () => {
    const html = await render(PasswordField, {
        modelValue: 'temporal', label: 'Nueva contraseña', toggleLabel: 'Mostrar nueva contraseña',
        minlength: '8', error: 'Mínimo 8 caracteres',
    });
    assert.match(html, /<label class="form-label">Nueva contraseña <span class="required">\*<\/span><\/label>/);
    assert.match(html, /<input[^>]*type="password"[^>]*required[^>]*minlength="8"[^>]*class="form-input input-error"[^>]*value="temporal"/);
    assert.match(html, /<button[^>]*type="button"[^>]*aria-label="Mostrar nueva contraseña"/);
    assert.match(html, /<p class="error-msg">Mínimo 8 caracteres<\/p>/);
});

test('ImportStat preserves the CSV summary markup and a zero value', async () => {
    const ImportStat = await loadVue('resources/js/components/forms/ImportStat.vue');
    const html = await render(ImportStat, { value: 0, label: 'Registrados', class: 'stat-ok' });
    assert.equal(html, '<div class="stat-card stat-ok"><div class="stat-info"><strong class="stat-number">0</strong><span class="stat-label">Registrados</span></div></div>');
});

test('PasswordField updates its model and toggles visibility independently', async () => {
    const node = tag => ({ tag, props: {}, children: [], listeners: {}, addEventListener(event, handler) { this.listeners[event] = handler; } });
    const renderer = createRenderer({
        createElement: node, createText: text => ({ text }), createComment: text => ({ comment: text }),
        insert(child, parent) { parent.children.push(child); child.parent = parent; },
        remove(child) { child.parent.children.splice(child.parent.children.indexOf(child), 1); },
        setText(child, text) { child.text = text; }, setElementText(child, text) { child.text = text; },
        parentNode: child => child.parent, nextSibling: () => null,
        patchProp(child, key, previous, value) { child.props[key] = value; child[key] = value; },
    });
    const value = ref('temporal');
    const container = node('root');
    const app = renderer.createApp({ render: () => h('div', [
        h(PasswordField, { modelValue: value.value, 'onUpdate:modelValue': updated => { value.value = updated; } }),
        h(PasswordField, { modelValue: 'otra' }),
    ]) });
    app.mount(container);
    const [first, second] = container.children[0].children;
    const [input, button] = first.children.find(child => child.props?.class === 'password-input-container').children;
    input.value = 'personal';
    input.listeners.input({ target: input });
    assert.equal(value.value, 'personal');
    button.props.onClick();
    await nextTick();
    assert.equal(input.props.type, 'text');
    assert.equal(second.children.find(child => child.props?.class === 'password-input-container').children[0].props.type, 'password');
    button.props.onClick();
    await nextTick();
    assert.equal(input.props.type, 'password');
    app.unmount();
});
