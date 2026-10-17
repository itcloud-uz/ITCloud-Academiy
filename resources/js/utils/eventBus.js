import { reactive } from 'vue';

class GlobalEventBus {
  constructor() {
    this.listeners = new Map();
    this.activeToastQueue = reactive([]);
  }

  on(event, callback) {
    if (!this.listeners.has(event)) {
      this.listeners.set(event, new Set());
    }
    this.listeners.get(event).add(callback);

    return () => this.off(event, callback);
  }

  off(event, callback) {
    if (this.listeners.has(event)) {
      this.listeners.get(event).delete(callback);
    }
  }

  emit(event, payload) {
    if (this.listeners.has(event)) {
      this.listeners.get(event).forEach((callback) => {
        try {
          callback(payload);
        } catch (err) {
          console.error(`[EventBus Error] (${event}):`, err);
        }
      });
    }
  }
}

export const eventBus = new GlobalEventBus();
