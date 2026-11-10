<script setup>
defineProps({
    error: String,
    errorTag: { type: String, default: 'span' },
});
</script>

<template>
    <div class="form-group">
        <slot />
        <component :is="errorTag" v-if="error" class="error-msg">{{ error }}</component>
    </div>
</template>
