<template>
  <div
    class="fixed top-6 right-6 z-50 flex flex-col gap-3 w-full max-w-md pointer-events-none"
    aria-live="assertive"
  >
    <TransitionGroup
      enter-active-class="transition duration-300 ease-out"
      enter-from-class="translate-x-10 opacity-0 scale-95"
      enter-to-class="translate-x-0 opacity-100 scale-100"
      leave-active-class="transition duration-200 ease-in"
      leave-from-class="translate-x-0 opacity-100 scale-100"
      leave-to-class="translate-x-10 opacity-0 scale-95"
    >
      <div
        v-for="toast in toasts"
        :key="toast.id"
        class="pointer-events-auto relative overflow-hidden rounded-2xl p-4 text-slate-100 bg-white/5 backdrop-blur-md border border-cyan-500/30 shadow-[0_0_20px_rgba(168,85,247,0.3)]"
      >
        <!-- Ambient Neon Top Accent Line -->
        <div
          class="absolute inset-x-0 top-0 h-[2px] bg-gradient-to-r from-cyan-400 via-purple-500 to-cyan-400"
        />

        <div class="flex items-start justify-between gap-4">
          <div class="flex items-start gap-3">
            <!-- Status Indicator Icon -->
            <div
              class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white/5 border border-cyan-500/30 text-cyan-400 shadow-[0_0_15px_rgba(6,182,212,0.5)]"
            >
              <span v-if="toast.status === 'passed'" class="text-lg font-bold">✓</span>
              <span v-else-if="toast.status === 'security_rejected'" class="text-lg font-bold text-rose-400">⚠</span>
              <span v-else class="text-lg font-bold text-amber-400">!</span>
            </div>

            <div class="space-y-1">
              <div class="flex items-center gap-2">
                <h4 class="text-sm font-bold tracking-wider uppercase text-cyan-300">
                  {{ toast.title }}
                </h4>
                <span
                  v-if="toast.awardedXp > 0"
                  class="rounded-full bg-cyan-500/20 border border-cyan-400/40 px-2 py-0.5 text-[11px] font-semibold text-cyan-300"
                >
                  +{{ toast.awardedXp }} XP
                </span>
              </div>

              <p class="text-xs leading-relaxed text-slate-200/90">
                {{ toast.message }}
              </p>

              <!-- Multi-Agent Scores Footer -->
              <div class="pt-2 flex items-center gap-3 text-[11px] font-mono text-slate-300">
                <span class="px-2 py-0.5 rounded bg-white/5 border border-cyan-500/20">
                  Logic: <strong class="text-cyan-400">{{ toast.logicScore }}%</strong>
                </span>
                <span class="px-2 py-0.5 rounded bg-white/5 border border-purple-500/20">
                  Security: <strong class="text-purple-400">{{ toast.securityScore }}%</strong>
                </span>
              </div>
            </div>
          </div>

          <button
            type="button"
            @click="dismissToast(toast.id)"
            class="rounded-lg p-1 text-slate-400 hover:bg-white/10 hover:text-cyan-300 transition"
            aria-label="Yopish"
          >
            ✕
          </button>
        </div>
      </div>
    </TransitionGroup>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { eventBus } from '../../utils/eventBus';

const toasts = ref([]);
let unsubscribe = null;

function pushToast(payload) {
  const id = `${Date.now()}-${Math.random().toString(36).slice(2, 7)}`;

  const statusTitleMap = {
    passed: 'AI Tekshiruv Muvaffaqiyatli!',
    security_rejected: 'Kiberxavfsizlik Rad Etdi (Gemini Auditor)',
    failed: 'Algoritmik Xatolik Aniqlangan',
  };

  toasts.value.unshift({
    id,
    status: payload.status || 'passed',
    title: statusTitleMap[payload.status] || 'AI Tahlil Yakunlandi',
    message:
      payload.feedback ||
      (payload.has_sql_injection
        ? 'SQL Injection zaifligi aniqlandi!'
        : payload.has_memory_leak
          ? 'Memory Leak xavfi aniqlandi!'
          : 'Kod multi-agent tekshiruvidan o‘tdi.'),
    logicScore: payload.logic_score ?? 0,
    securityScore: payload.security_score ?? 0,
    awardedXp: payload.awarded_xp ?? 0,
  });

  setTimeout(() => {
    dismissToast(id);
  }, 8000);
}

function dismissToast(id) {
  toasts.value = toasts.value.filter((t) => t.id !== id);
}

onMounted(() => {
  unsubscribe = eventBus.on('ai:task-evaluated', pushToast);
});

onBeforeUnmount(() => {
  if (unsubscribe) unsubscribe();
});
</script>
