<script setup>
import Button from './Button.vue';
import LoadingSpinner from './LoadingSpinner.vue';

defineProps({ loading: Boolean });
</script>

<template>
    <Button>
        <LoadingSpinner v-if="loading" size="small" />
        <span v-else><slot /></span>
    </Button>
</template>
