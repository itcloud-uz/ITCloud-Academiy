<template>
  <div class="min-h-screen bg-slate-950 text-slate-100 p-6 flex flex-col gap-6">
    <!-- Real-Time Cyber-Glass Toast Notification -->
    <ToastNotification />

    <!-- Top Gamification & Student Telemetry Header -->
    <header
      class="flex flex-wrap items-center justify-between gap-4 rounded-2xl p-4 bg-white/5 backdrop-blur-md border border-cyan-500/30 shadow-[0_0_15px_rgba(6,182,212,0.5)]"
    >
      <div class="flex items-center gap-4">
        <div
          class="flex h-12 w-12 items-center justify-center rounded-xl bg-white/5 border border-cyan-500/40 font-mono text-lg font-extrabold text-cyan-300 shadow-[0_0_15px_rgba(6,182,212,0.5)]"
        >
          L{{ studentStore.level }}
        </div>
        <div>
          <div class="flex items-center gap-2">
            <h1 class="text-base font-bold tracking-wide text-white">
              {{ task.title }}
            </h1>
            <span
              class="rounded-md px-2.5 py-0.5 text-[11px] font-mono uppercase border bg-white/5"
              :class="[
                studentStore.tierBadgeConfig.borderClass,
                studentStore.tierBadgeConfig.glowClass,
              ]"
            >
              {{ studentStore.tierBadgeConfig.label }}
            </span>
          </div>
          <p class="text-xs text-slate-400 font-mono mt-0.5">
            Guruh: {{ studentStore.groupName }} | Kun #{{ task.day_number }} | Mukofot: +{{ task.reward_xp }} XP
          </p>
        </div>
      </div>

      <!-- XP Progress & Wallet Telemetry -->
      <div class="flex items-center gap-6">
        <div class="w-48">
          <div class="flex justify-between text-xs font-mono mb-1">
            <span class="text-cyan-300">{{ studentStore.xpPoints }} XP</span>
            <span class="text-slate-400">{{ studentStore.nextLevelTargetXp }} XP</span>
          </div>
          <div class="h-2 w-full overflow-hidden rounded-full bg-white/5 border border-cyan-500/30">
            <div
              class="h-full bg-gradient-to-r from-cyan-400 to-purple-500 transition-all duration-500"
              :style="{ width: `${studentStore.levelProgressPercentage}%` }"
            />
          </div>
        </div>

        <div
          class="rounded-xl bg-white/5 backdrop-blur-md border border-cyan-500/30 px-4 py-2 font-mono text-xs"
        >
          <span class="text-slate-400 block">BALANS</span>
          <span class="text-emerald-400 font-bold text-sm">
            ${{ studentStore.walletBalance.toFixed(2) }}
          </span>
        </div>
      </div>
    </header>

    <!-- Main IDE Workspace Split Grid -->
    <main class="grid grid-cols-1 lg:grid-cols-12 gap-6 flex-1">
      <!-- Left Panel: Task Specification & Security Rules -->
      <section
        class="lg:col-span-4 flex flex-col justify-between rounded-2xl p-5 bg-white/5 backdrop-blur-md border border-cyan-500/30 shadow-[0_0_20px_rgba(168,85,247,0.3)]"
      >
        <div class="space-y-4 overflow-y-auto pr-1">
          <div class="flex items-center justify-between border-b border-cyan-500/20 pb-3">
            <span class="text-xs font-mono uppercase tracking-widest text-cyan-400">
              // VAZIFA SHARTI
            </span>
            <span class="rounded bg-purple-500/20 border border-purple-400/40 px-2 py-0.5 text-xs font-mono text-purple-300">
              {{ task.difficulty.toUpperCase() }}
            </span>
          </div>

          <p class="text-sm leading-relaxed text-slate-200 whitespace-pre-line">
            {{ task.description }}
          </p>

          <div class="rounded-xl bg-white/5 border border-cyan-500/20 p-3.5 space-y-2">
            <h3 class="text-xs font-mono uppercase text-cyan-300">
              Gemini Security Auditor Talablari:
            </h3>
            <ul class="text-xs text-slate-300 space-y-1 list-disc list-inside font-mono">
              <li>SQL Injection xavfi bo'lmasligi (Prepared Statements)</li>
              <li>Memory Leak va cheksiz sikllardan xoli bo'lishi</li>
              <li>Time Complexity: O(N log N) yoki undan tezroq</li>
            </ul>
          </div>
        </div>

        <!-- Latest AI Audit Telemetry Box -->
        <div
          v-if="lastEvaluation"
          class="mt-6 rounded-xl bg-white/5 backdrop-blur-md border border-cyan-500/30 p-3.5 text-xs font-mono space-y-1.5"
        >
          <div class="flex items-center justify-between">
            <span class="text-cyan-300 font-bold">SO'NGGI AI XULOSASI:</span>
            <span class="uppercase" :class="lastEvaluation.status === 'passed' ? 'text-emerald-400' : 'text-rose-400'">
              {{ lastEvaluation.status }}
            </span>
          </div>
          <p class="text-slate-300">{{ lastEvaluation.feedback }}</p>
        </div>
      </section>

      <!-- Right Panel: Monaco Web IDE -->
      <section
        class="lg:col-span-8 flex flex-col rounded-2xl overflow-hidden bg-white/5 backdrop-blur-md border border-cyan-500/30 shadow-[0_0_15px_rgba(6,182,212,0.5)]"
      >
        <!-- IDE Toolbar -->
        <div class="flex items-center justify-between px-4 py-3 border-b border-cyan-500/20 bg-white/5">
          <div class="flex items-center gap-3">
            <span class="h-3 w-3 rounded-full bg-rose-500/80" />
            <span class="h-3 w-3 rounded-full bg-amber-500/80" />
            <span class="h-3 w-3 rounded-full bg-emerald-500/80" />
            <span class="ml-2 text-xs font-mono text-cyan-300">
              solution.{{ fileExtension }}
            </span>
          </div>

          <div class="flex items-center gap-3">
            <select
              v-model="selectedLanguage"
              @change="updateEditorLanguage"
              class="rounded-lg bg-slate-900/90 border border-cyan-500/30 px-3 py-1 text-xs font-mono text-cyan-300 focus:outline-none"
            >
              <option value="php">PHP 8.3</option>
              <option value="javascript">JavaScript (Node)</option>
              <option value="python">Python 3.12</option>
              <option value="sql">PostgreSQL</option>
            </select>

            <button
              type="button"
              :disabled="isSubmitting"
              @click="submitCodeForAiAudit"
              class="rounded-xl bg-cyan-500/20 hover:bg-cyan-500/30 disabled:opacity-50 border border-cyan-400/50 px-4 py-1.5 text-xs font-mono font-bold text-cyan-200 shadow-[0_0_15px_rgba(6,182,212,0.5)] transition"
            >
              {{ isSubmitting ? 'AI TEKSHIRMOQDA...' : 'AI AUDITGA YUBORISH ▶' }}
            </button>
          </div>
        </div>

        <!-- Monaco Editor Mount Container -->
        <div ref="monacoContainerRef" class="w-full flex-1 min-h-[520px]" />
      </section>
    </main>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, shallowRef } from 'vue';
import * as monaco from 'monaco-editor';
import axios from 'axios';
import { useStudentStore } from '../../stores/useStudentStore';
import { eventBus } from '../../utils/eventBus';
import ToastNotification from '../ui/ToastNotification.vue';

const props = defineProps({
  initialTask: {
    type: Object,
    required: false,
    default: () => ({
      id: '01929b4c-7c00-7000-8000-000000000001',
      title: 'Highload Atomic Balance Transfer',
      description:
        'PostgreSQL SELECT ... FOR UPDATE yordamida Race Condition xavfisiz pul va XP o‘tkazmasini amalga oshiruvchi servis metodini yozing.',
      language: 'php',
      difficulty: 'hard',
      day_number: 14,
      reward_xp: 250,
      starter_code: `<?php\n\ndeclare(strict_types=1);\n\nfunction transferAtomic(PDO $pdo, string $fromId, string $toId, float $amount): void\n{\n    // Kodni shu yerda yozing\n}\n`,
    }),
  },
  supabaseClient: {
    type: Object,
    required: false,
    default: null,
  },
});

const studentStore = useStudentStore();
const task = ref(props.initialTask);
const selectedLanguage = ref(props.initialTask.language || 'php');
const isSubmitting = ref(false);
const lastEvaluation = ref(null);

const monacoContainerRef = ref(null);
const editorInstance = shallowRef(null);
let realtimeChannel = null;

const fileExtension = computed(() => {
  const map = { php: 'php', javascript: 'js', python: 'py', sql: 'sql' };
  return map[selectedLanguage.value] || 'txt';
});

function initMonacoEditor() {
  if (!monacoContainerRef.value) return;

  monaco.editor.defineTheme('cyber-glass-dark', {
    base: 'vs-dark',
    inherit: true,
    rules: [
      { token: 'keyword', foreground: '22d3ee', fontStyle: 'bold' },
      { token: 'comment', foreground: '64748b', fontStyle: 'italic' },
      { token: 'string', foreground: 'c084fc' },
    ],
    colors: {
      'editor.background': '#02061700',
      'editor.lineHighlightBackground': '#06b6d415',
      'editorCursor.foreground': '#22d3ee',
      'editorLineNumber.foreground': '#475569',
    },
  });

  editorInstance.value = monaco.editor.create(monacoContainerRef.value, {
    value: task.value.starter_code || '',
    language: selectedLanguage.value,
    theme: 'cyber-glass-dark',
    fontFamily: "'JetBrains Mono', 'Fira Code', monospace",
    fontSize: 14,
    minimap: { enabled: false },
    automaticLayout: true,
    scrollBeyondLastLine: false,
    padding: { top: 16, bottom: 16 },
  });
}

function updateEditorLanguage() {
  if (!editorInstance.value) return;
  const model = editorInstance.value.getModel();
  if (model) {
    monaco.editor.setModelLanguage(model, selectedLanguage.value);
  }
}

async function submitCodeForAiAudit() {
  if (!editorInstance.value || isSubmitting.value) return;

  const code = editorInstance.value.getValue();
  isSubmitting.value = true;

  try {
    await axios.post(`/api/v1/tasks/${task.value.id}/submit`, {
      submitted_code: code,
      language: selectedLanguage.value,
    });
  } catch (err) {
    eventBus.emit('ai:task-evaluated', {
      status: 'failed',
      logic_score: 0,
      security_score: 0,
      awarded_xp: 0,
      feedback:
        err?.response?.data?.message || 'Kodni navbatga yuborishda tarmoq xatoligi yuz berdi.',
    });
    isSubmitting.value = false;
  }
}

function subscribeToRealtimeEvents() {
  const handleEvaluationPayload = (payload) => {
    isSubmitting.value = false;
    lastEvaluation.value = payload;
    studentStore.applyRealtimeEvaluationUpdate(payload);
    eventBus.emit('ai:task-evaluated', payload);
  };

  // 1. Laravel Echo (WebSockets / Reverb / Pusher)
  if (window.Echo && studentStore.studentId) {
    realtimeChannel = window.Echo.private(`student.${studentStore.studentId}`).listen(
      '.task.evaluated',
      handleEvaluationPayload
    );
  }

  // 2. Supabase Realtime Fallback / Bridge
  if (props.supabaseClient && studentStore.studentId) {
    realtimeChannel = props.supabaseClient
      .channel(`student:${studentStore.studentId}`)
      .on('broadcast', { event: 'task.evaluated' }, ({ payload }) => {
        handleEvaluationPayload(payload);
      })
      .subscribe();
  }
}

onMounted(async () => {
  initMonacoEditor();
  try {
    await studentStore.fetchStudentProfile();
  } catch {
    // Profile will remain at initial defaults if offline
  }
  subscribeToRealtimeEvents();
});

onBeforeUnmount(() => {
  if (editorInstance.value) {
    editorInstance.value.dispose();
  }
  if (window.Echo && studentStore.studentId) {
    window.Echo.leave(`student.${studentStore.studentId}`);
  }
  if (props.supabaseClient && realtimeChannel) {
    props.supabaseClient.removeChannel(realtimeChannel);
  }
});
</script>
