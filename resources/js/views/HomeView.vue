<template>
    <button class="btn-primary">Verificar Estudiante</button>
</template>

<style scoped>
.btn-primary {
    background-color: var(--color-primary);
    color: var(--color-white);
    border: none;
    border-radius: var(--radius-md);
    padding: 10px 20px;
    font-family: var(--font-main);
    transition: background-color var(--transition-base);
    cursor: pointer;
}

.btn-primary:hover {
    background-color: var(--color-secondary);
}
</style>