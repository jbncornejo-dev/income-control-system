import { h } from 'vue';

export const Head = () => null;
export const Link = (props, { attrs, slots }) => h('a', { ...attrs, href: props.href }, slots.default?.());
