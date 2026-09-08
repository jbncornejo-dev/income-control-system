import { defineStore } from 'pinia'

export const useToastStore = defineStore('toast', {
  state: () => ({
    toasts: []
  }),
  actions: {
    success(msg) {
      this._add({ msg, type: 'success' })
    },
    error(msg) {
      this._add({ msg, type: 'error' })
    },
    _add(toast) {
      const id = Date.now()
      this.toasts.push({ id, ...toast })
      setTimeout(() => this.remove(id), 4000)
    },
    remove(id) {
      this.toasts = this.toasts.filter(t => t.id !== id)
    }
  }
})