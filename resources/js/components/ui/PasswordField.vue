<script setup>
import { ref } from 'vue';

defineOptions({ inheritAttrs: false });

defineProps({
    label: String,
    toggleLabel: String,
    error: String,
});

const model = defineModel({ type: String });
const visible = ref(false);
</script>

<template>
    <div class="form-group">
        <label class="form-label">{{ label }} <span class="required">*</span></label>
        <div class="password-input-container">
            <input
                :type="visible ? 'text' : 'password'"
                v-model="model"
                required
                v-bind="$attrs"
                class="form-input"
                :class="{ 'input-error': error }"
            />
            <button type="button" class="btn-eye" @click="visible = !visible" :aria-label="toggleLabel">👁</button>
        </div>
        <p v-if="error" class="error-msg">{{ error }}</p>
    </div>
</template>

<style scoped>
.form-group { margin-bottom: 16px; }
.form-label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px; }
.required { color: #d32f2f; }
.password-input-container { position: relative; }
.password-input-container .form-input { padding-right: 42px; }
.form-input {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    font-size: 14px;
    background: #f9fafb;
    color: #1f2937;
    box-sizing: border-box;
}
.form-input:focus { outline: none; border-color: #1d3653; box-shadow: 0 0 0 3px rgba(29, 54, 83, 0.15); }
.input-error { border-color: #d32f2f !important; }
.error-msg { color: #d32f2f; font-size: 12px; margin-top: 4px; }
.btn-eye {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    cursor: pointer;
    font-size: 16px;
    opacity: 0.7;
}
</style>
