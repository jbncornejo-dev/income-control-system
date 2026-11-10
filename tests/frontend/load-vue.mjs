import { readFile } from 'node:fs/promises';
import { resolve, dirname } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { compileScript, parse } from '@vue/compiler-sfc';

const root = fileURLToPath(new URL('../../', import.meta.url));
const modules = new Map();

async function compileVue(filename, imports) {
    const { descriptor, errors } = parse(await readFile(filename, 'utf8'), { filename });
    if (errors.length) throw errors[0];
    let { content } = compileScript(descriptor, { id: filename, inlineTemplate: true });

    for (const specifier of new Set([...content.matchAll(/from ['"]([^'"]+)['"]/g)].map(match => match[1]))) {
        const local = specifier.startsWith('@/') ? resolve(root, 'resources/js', specifier.slice(2))
            : specifier.startsWith('.') ? resolve(dirname(filename), specifier) : null;
        const url = imports[specifier] || (local?.endsWith('.vue') ? await moduleUrl(local, imports)
            : local ? pathToFileURL(local).href : import.meta.resolve(specifier));
        content = content.replaceAll(`from '${specifier}'`, `from '${url}'`).replaceAll(`from "${specifier}"`, `from "${url}"`);
    }

    return `data:text/javascript;base64,${Buffer.from(content).toString('base64')}`;
}

function moduleUrl(filename, imports) {
    const key = filename + JSON.stringify(imports);
    if (!modules.has(key)) modules.set(key, compileVue(filename, imports));
    return modules.get(key);
}

export async function loadVue(relativePath, imports = {}) {
    return (await import(await moduleUrl(resolve(root, relativePath), imports))).default;
}
