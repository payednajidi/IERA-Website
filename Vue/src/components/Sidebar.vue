<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { getAssessmentProgress, getCurrentAssessmentId, setCurrentAssessmentId } from '../services/eraProgress'

const route = useRoute()
const assessmentId = ref('')
const isMenuOpen = ref(true)
const progress = ref({
  step1: false,
  step2: false,
  step3: false,
  step4: false,
  step5: false,
  step6: false,
  step7: false,
})

const stepCompleted = stepNumber => Boolean(progress.value[`step${stepNumber}`])

const isEraRoute = computed(() => route.path.startsWith('/era-'))

const refreshLinks = () => {
  const routeId = route.params.assessmentId ? String(route.params.assessmentId) : ''
  const storedId = getCurrentAssessmentId()
  const currentId = routeId || storedId

  assessmentId.value = currentId

  if (routeId) {
    setCurrentAssessmentId(routeId)
  }

  progress.value = currentId ? getAssessmentProgress(currentId) : {
    step1: false,
    step2: false,
    step3: false,
    step4: false,
    step5: false,
    step6: false,
    step7: false,
  }
}

const checklistLink = computed(() => {
  return `/era-checklist/${assessmentId.value || 0}`
})

const forcefulLink = computed(() => {
  return `/era-forceful-exertion/${assessmentId.value || 0}`
})

const repetitiveLink = computed(() => {
  return `/era-repetitive-motion/${assessmentId.value || 0}`
})

const vibrationLink = computed(() => {
  return `/era-vibration/${assessmentId.value || 0}`
})

const environmentalLink = computed(() => {
  return `/era-environmental-factors/${assessmentId.value || 0}`
})

const summaryLink = computed(() => {
  return `/era-summary/${assessmentId.value || 0}`
})

const handleProgressUpdated = () => {
  refreshLinks()
}

onMounted(() => {
  refreshLinks()
  window.addEventListener('era-progress-updated', handleProgressUpdated)
  window.addEventListener('storage', handleProgressUpdated)
})

onBeforeUnmount(() => {
  window.removeEventListener('era-progress-updated', handleProgressUpdated)
  window.removeEventListener('storage', handleProgressUpdated)
})

watch(
  () => route.fullPath,
  () => {
    refreshLinks()
  }
)
</script>

<template>
  <div class="sidebar">
    <div class="logo">
      ERA SYSTEM
    </div>

    <nav>
      <button type="button" class="nav-item nav-parent" :class="{ 'nav-parent-active': isEraRoute }" @click="isMenuOpen = !isMenuOpen">
        <span>ERA Assessment</span>
        <span class="dropdown-icon">{{ isMenuOpen ? '▾' : '▸' }}</span>
      </button>

      <div v-show="isMenuOpen" class="nav-group">
        <RouterLink to="/era-form" class="nav-item nav-sub-item">
          <span>Step 1: ERA Assessment</span>
          <span v-if="stepCompleted(1)" class="step-check" aria-label="Step completed">&#10003;</span>
        </RouterLink>

        <RouterLink :to="checklistLink" class="nav-item nav-sub-item">
          <span>Step 2: Ergonomic Risk Factor</span>
          <span v-if="stepCompleted(2)" class="step-check" aria-label="Step completed">&#10003;</span>
        </RouterLink>

        <RouterLink :to="forcefulLink" class="nav-item nav-sub-item">
          <span>Step 3: Forceful Exertion</span>
          <span v-if="stepCompleted(3)" class="step-check" aria-label="Step completed">&#10003;</span>
        </RouterLink>

        <RouterLink :to="repetitiveLink" class="nav-item nav-sub-item">
          <span>Step 4: Repetitive Motion</span>
          <span v-if="stepCompleted(4)" class="step-check" aria-label="Step completed">&#10003;</span>
        </RouterLink>

        <RouterLink :to="vibrationLink" class="nav-item nav-sub-item">
          <span>Step 5: Vibration</span>
          <span v-if="stepCompleted(5)" class="step-check" aria-label="Step completed">&#10003;</span>
        </RouterLink>

        <RouterLink :to="environmentalLink" class="nav-item nav-sub-item">
          <span>Step 6: Environmental Factors</span>
          <span v-if="stepCompleted(6)" class="step-check" aria-label="Step completed">&#10003;</span>
        </RouterLink>

        <RouterLink :to="summaryLink" class="nav-item nav-sub-item">
          <span>Step 7: Initial ERA Summary</span>
          <span v-if="stepCompleted(7)" class="step-check" aria-label="Step completed">&#10003;</span>
        </RouterLink>
      </div>

      <RouterLink to="/era-assessment-files" class="nav-item">
        Era Assessment File (Edit/Preview)
      </RouterLink>

      <div class="nav-note">
        You can open any step directly. Tick marks appear only after saving that step.
      </div>
    </nav>
  </div>
</template>

<style scoped>
.sidebar {
  width: 240px;
  flex: 0 0 240px;
  height: 100vh;
  background: #2f3e4d;
  color: white;
  display: flex;
  flex-direction: column;
}

.logo {
  font-weight: bold;
  font-size: 15px;
  padding: 20px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.2);
  letter-spacing: 0.05em;
}

nav {
  display: flex;
  flex-direction: column;
  padding: 8px 0;
}

.nav-item {
  padding: 14px 20px;
  color: white;
  text-decoration: none;
  font-size: 13.5px;
  border-left: 3px solid transparent;
  transition: all 0.15s;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.nav-parent {
  width: 100%;
  background: transparent;
  border: 0;
  text-align: left;
  cursor: pointer;
}

.nav-parent-active {
  background: #3e5164;
  border-left: 3px solid #7eb8f7;
  font-weight: 600;
}

.dropdown-icon {
  font-size: 12px;
  opacity: 0.85;
}

.nav-group {
  display: flex;
  flex-direction: column;
}

.nav-sub-item {
  padding-left: 28px;
  font-size: 13px;
}

.nav-item:hover {
  background: #3e5164;
}

.nav-item.router-link-active {
  background: #3e5164;
  border-left: 3px solid #7eb8f7;
  font-weight: 600;
}

.nav-note {
  padding: 12px 18px;
  font-size: 11.5px;
  color: rgba(255, 255, 255, 0.6);
  line-height: 1.5;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
  margin-top: 8px;
}

.step-check {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 18px;
  height: 18px;
  border-radius: 999px;
  background: #2a7a52;
  color: #fff;
  font-size: 12px;
  font-weight: 700;
}
</style>

