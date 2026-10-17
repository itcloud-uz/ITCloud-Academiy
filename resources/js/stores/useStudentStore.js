import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import axios from 'axios';

export const useStudentStore = defineStore('student', () => {
  // State
  const studentId = ref(null);
  const fullName = ref('');
  const groupName = ref('');
  const groupTier = ref('foundation'); // 'foundation' | 'intermediate' | 'advanced'
  const xpPoints = ref(0);
  const level = ref(1);
  const walletBalance = ref(0.0);
  const status = ref('active');
  const isLoading = ref(false);
  const error = ref(null);

  // Getters (Computed)
  const currentLevelBaseXp = computed(() => Math.pow(level.value - 1, 2) * 100);
  const nextLevelTargetXp = computed(() => Math.pow(level.value, 2) * 100);

  const levelProgressPercentage = computed(() => {
    const span = nextLevelTargetXp.value - currentLevelBaseXp.value;
    if (span <= 0) return 100;
    const earnedInLevel = xpPoints.value - currentLevelBaseXp.value;
    return Math.min(100, Math.max(0, Math.round((earnedInLevel / span) * 100)));
  });

  const tierBadgeConfig = computed(() => {
    const map = {
      foundation: {
        label: 'FOUNDATION TIER',
        borderClass: 'border-cyan-500/40 text-cyan-300',
        glowClass: 'shadow-[0_0_15px_rgba(6,182,212,0.4)]',
      },
      intermediate: {
        label: 'INTERMEDIATE TIER',
        borderClass: 'border-purple-500/40 text-purple-300',
        glowClass: 'shadow-[0_0_15px_rgba(168,85,247,0.4)]',
      },
      advanced: {
        label: 'CYBER ARCHITECT TIER',
        borderClass: 'border-emerald-500/40 text-emerald-300',
        glowClass: 'shadow-[0_0_15px_rgba(16,185,129,0.5)]',
      },
    };
    return map[groupTier.value] || map.foundation;
  });

  // Actions
  async function fetchStudentProfile() {
    isLoading.value = true;
    error.value = null;
    try {
      const { data } = await axios.get('/api/v1/student/profile');
      hydrateProfile(data.data);
    } catch (err) {
      error.value = err?.response?.data?.message || 'Profil ma\'lumotlarini yuklashda xatolik.';
      throw err;
    } finally {
      isLoading.value = false;
    }
  }

  function hydrateProfile(payload) {
    studentId.value = payload.id;
    fullName.value = payload.full_name ?? '';
    groupName.value = payload.group?.name ?? 'Unassigned';
    groupTier.value = payload.group?.tier ?? 'foundation';
    xpPoints.value = Number(payload.xp_points ?? 0);
    level.value = Number(payload.level ?? 1);
    walletBalance.value = Number(payload.wallet_balance ?? 0.0);
    status.value = payload.status ?? 'active';
  }

  function applyRealtimeEvaluationUpdate(realtimePayload) {
    if (!realtimePayload?.student_state) return;

    xpPoints.value = Number(realtimePayload.student_state.xp_points ?? xpPoints.value);
    level.value = Number(realtimePayload.student_state.level ?? level.value);
    walletBalance.value = Number(
      realtimePayload.student_state.wallet_balance ?? walletBalance.value
    );
  }

  return {
    studentId,
    fullName,
    groupName,
    groupTier,
    xpPoints,
    level,
    walletBalance,
    status,
    isLoading,
    error,
    currentLevelBaseXp,
    nextLevelTargetXp,
    levelProgressPercentage,
    tierBadgeConfig,
    fetchStudentProfile,
    hydrateProfile,
    applyRealtimeEvaluationUpdate,
  };
});
