<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../services/api'
import { markStepCompleted, setCurrentAssessmentId } from '../services/eraProgress'

const route = useRoute()
const router = useRouter()
const assessmentId = Number(route.params.assessmentId)

const loading = ref(true)
const savingChecklist = ref(false)
const templates = ref([])
const tasks = ref([])
const answers = ref([])
const currentStep = ref(1)

const answerMap = computed(() => {
  const map = {}
  answers.value.forEach(a => {
    map[`${a.task_id}_${a.checklist_item_id}`] = a.answer
  })
  return map
})

const getYesCount = (taskId, template) => {
  return answers.value.filter(a =>
    a.task_id === taskId &&
    template.items.some(i => i.id === a.checklist_item_id) &&
    a.answer === true
  ).length
}

const getNoCount = (taskId, template) => {
  return answers.value.filter(a =>
    a.task_id === taskId &&
    template.items.some(i => i.id === a.checklist_item_id) &&
    a.answer === false
  ).length
}

const nextStep = () => {
  if (currentStep.value < templates.value.length) {
    currentStep.value++
  }
}

const previousStep = () => {
  if (currentStep.value > 1) {
    currentStep.value--
  }
}

const goPrevious = () => {
  if (currentStep.value > 1) {
    previousStep()
    return
  }

  router.push('/era-form')
}

const setAnswer = (taskId, itemId, value) => {
  const existingIndex = answers.value.findIndex(
    a => a.task_id === taskId && a.checklist_item_id === itemId
  )

  if (existingIndex !== -1) {
    answers.value[existingIndex].answer = value
  }
}

const generateInitialAnswers = () => {
  const generated = []

  templates.value.forEach(template => {
    template.items.forEach(item => {
      tasks.value.forEach(task => {
        generated.push({
          assessment_id: assessmentId,
          task_id: task.id,
          checklist_item_id: item.id,
          answer: false,
        })
      })
    })
  })

  answers.value = generated
}

const loadChecklist = async () => {
  try {
    const response = await api.get(`/era-checklist/${assessmentId}`)

    templates.value = response.data.templates ?? []
    tasks.value = response.data.tasks ?? []

    if (response.data.answers && response.data.answers.length > 0) {
      answers.value = response.data.answers
    } else {
      generateInitialAnswers()
    }
  } catch (error) {
    console.error(error)
  } finally {
    loading.value = false
  }
}

const saveChecklistAndContinue = async () => {
  if (!assessmentId) {
    alert('Please submit Step 1 first to create an assessment before saving.')
    return
  }

  if (savingChecklist.value) return
  savingChecklist.value = true

  try {
    await api.post('/era-checklist', {
      assessment_id: assessmentId,
      answers: answers.value,
    })

    markStepCompleted(assessmentId, 2)
    router.push(`/era-forceful-exertion/${assessmentId}`)
  } catch (error) {
    console.error(error.response?.data || error)
    alert('Error saving checklist.')
  } finally {
    savingChecklist.value = false
  }
}

onMounted(() => {
  setCurrentAssessmentId(assessmentId)
  loadChecklist()
})
</script>

<template>
  <div v-if="loading" class="loading-state">
    Loading checklist...
  </div>

  <div v-else class="checklist-wrapper">
    <div class="page-hero">
      <div class="hero-left">
        <div class="hero-tag">Step 2 of 7</div>
        <h1 class="hero-title">ERA Checklist</h1>
        <p class="hero-sub">File: `EraChecklist.vue` - Ergonomic Risk Factor Checklist for all tasks. Assessment ID: #{{ assessmentId }}</p>
      </div>
      <div class="hero-steps">
        <div v-for="s in 7" :key="s" class="step-pip" :class="{ active: s === 2 }">
          <div class="pip-dot"></div>
          <div class="pip-label">Step {{ s }}</div>
        </div>
      </div>
    </div>

    <div class="step-bar">
      <div
        v-for="(template, index) in templates"
        :key="template.id"
        class="step-chip"
        :class="{ active: currentStep === index + 1, done: currentStep > index + 1 }"
        @click="currentStep = index + 1"
      >
        <span class="step-num">{{ index + 1 }}</span>
        <span class="step-name">{{ template.name }}</span>
      </div>
    </div>

    <div
      v-for="(template, index) in templates"
      :key="template.id"
      v-show="currentStep === index + 1"
      class="checklist-section"
    >
      <h3 class="section-title">{{ template.name }}</h3>

      <div class="table-scroll">
        <table class="checklist-table">
          <thead>
            <tr>
              <th class="col-body">Body Part</th>
              <th class="col-risk">Physical Risk Factor</th>
              <th class="col-duration">Max Exposure Duration</th>
              <th
                v-for="task in tasks"
                :key="task.id"
                colspan="2"
                class="col-task"
              >
                {{ task.title }}
              </th>
            </tr>

            <tr>
              <th></th>
              <th></th>
              <th></th>
              <template v-for="task in tasks" :key="`sub_${task.id}`">
                <th class="td-radio">Yes</th>
                <th class="td-radio">No</th>
              </template>
            </tr>
          </thead>

          <tbody>
            <tr
              v-for="(item, rowIdx) in (template.items ?? [])"
              :key="item.id"
              :class="rowIdx % 2 === 0 ? 'row-white' : 'row-gray'"
            >
              <td>{{ item.body_part }}</td>
              <td>{{ item.description }}</td>
              <td class="td-center">{{ item.max_duration }}</td>

              <template v-for="task in tasks" :key="`row_${item.id}_${task.id}`">
                <td class="td-radio">
                  <input
                    type="radio"
                    :name="`cell_${task.id}_${item.id}`"
                    :checked="answerMap[`${task.id}_${item.id}`] === true"
                    @change="setAnswer(task.id, item.id, true)"
                  />
                </td>
                <td class="td-radio">
                  <input
                    type="radio"
                    :name="`cell_${task.id}_${item.id}`"
                    :checked="answerMap[`${task.id}_${item.id}`] === false"
                    @change="setAnswer(task.id, item.id, false)"
                  />
                </td>
              </template>
            </tr>

            <tr class="subtotal-row">
              <td colspan="3" class="subtotal-label">
                Sub Total (Number of tick(s))
              </td>
              <template v-for="task in tasks" :key="`subtotal_${task.id}`">
                <td class="subtotal-yes">{{ getYesCount(task.id, template) }}</td>
                <td class="subtotal-no">{{ getNoCount(task.id, template) }}</td>
              </template>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="step-nav">
      <button
        class="btn-nav btn-prev"
        @click="goPrevious"
      >
        Previous
      </button>

      <div class="step-counter">Step {{ currentStep }} of {{ templates.length }}</div>

      <button
        v-if="currentStep < templates.length"
        class="btn-nav btn-next"
        @click="nextStep"
      >
        Proceed
      </button>

      <button
        v-if="currentStep === templates.length && templates.length > 0"
        class="btn-nav btn-save"
        :disabled="savingChecklist"
        @click="saveChecklistAndContinue"
      >
        {{ savingChecklist ? 'Saving...' : 'Save & Proceed (Step 3)' }}
      </button>
    </div>
  </div>
</template>

<style scoped>
.loading-state {
  padding: 40px;
  text-align: center;
  font-size: 15px;
  color: #555;
}

.checklist-wrapper {
  font-family: 'DM Sans', Arial, sans-serif;
  font-size: 13px;
  color: #111;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.page-hero {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 20px;
  padding: 22px 24px;
  background: linear-gradient(135deg, #0b1a2a 0%, #17324f 58%, #224f7a 100%);
  border-radius: 10px;
}
.hero-left { display: flex; flex-direction: column; gap: 6px; }
.hero-tag {
  width: fit-content;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.05em;
  color: #e8a020;
  background: rgba(232,160,32,0.25);
  border: 1px solid rgba(232,160,32,0.55);
  text-transform: uppercase;
}
.hero-title { font-size: 36px; font-weight: 700; color: #f7fbff; line-height: 1.2; }
.hero-sub { font-size: 13px; color: rgba(231,241,251,0.96); max-width: 780px; line-height: 1.5; }
.hero-steps { display: flex; gap: 8px; align-items: center; padding-top: 4px; }
.step-pip { display: flex; flex-direction: column; align-items: center; gap: 4px; opacity: 0.65; }
.step-pip.active { opacity: 1; }
.pip-dot { width: 10px; height: 10px; border-radius: 50%; background: rgba(255,255,255,0.45); border: 2px solid rgba(255,255,255,0.25); }
.step-pip.active .pip-dot { background: #e8a020; border-color: #e8a020; box-shadow: 0 0 8px #e8a020; }
.pip-label { font-size: 9px; color: rgba(255,255,255,0.78); font-weight: 700; text-transform: uppercase; white-space: nowrap; }
.step-pip.active .pip-label { color: #e8a020; }

@media (max-width: 900px) {
  .page-hero { flex-direction: column; }
  .hero-steps { flex-wrap: wrap; }
}

.step-bar {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.step-chip {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 7px 14px;
  border-radius: 20px;
  border: 1px solid #ccc;
  background: #f5f5f5;
  cursor: pointer;
  font-size: 12.5px;
  transition: all 0.15s;
}

.step-chip:hover {
  background: #e8eef5;
}

.step-chip.active {
  background: #2f3e4d;
  color: white;
  border-color: #2f3e4d;
}

.step-chip.done {
  background: #e5f2ec;
  border-color: #7bbf9e;
  color: #2a7a52;
}

.step-num {
  font-weight: 700;
  font-size: 13px;
}

.checklist-section {
  background: white;
  border: 1px solid #ddd;
  border-radius: 4px;
  padding: 20px;
}

.section-title {
  font-size: 15px;
  font-weight: 700;
  margin: 0 0 14px 0;
  color: #2f3e4d;
  padding-bottom: 8px;
  border-bottom: 2px solid #2f3e4d;
}

.table-scroll {
  overflow-x: auto;
}

.checklist-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 12.5px;
  min-width: 600px;
}

.checklist-table th {
  background: #2f3e4d;
  color: white;
  padding: 9px 10px;
  font-weight: 600;
  text-align: left;
  border: 1px solid #1e2d3a;
  white-space: nowrap;
}

.checklist-table td {
  border: 1px solid #ccc;
  padding: 7px 10px;
  vertical-align: middle;
}

.col-body { width: 120px; }
.col-risk { width: 220px; }
.col-duration { width: 120px; }
.col-task { width: 110px; text-align: center; }

.td-center { text-align: center; }
.td-radio { text-align: center; }

.row-white { background: #fff; }
.row-gray { background: #f7f8f9; }

.subtotal-row {
  background: #f2f2f2;
  font-weight: 600;
}

.subtotal-label {
  text-align: right;
}

.step-nav {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 0;
  flex-wrap: wrap;
}

.step-counter {
  flex: 1;
  text-align: center;
  font-size: 13px;
  color: #666;
  font-weight: 500;
}

.btn-nav {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 12px 24px;
  font-size: 14px;
  font-weight: 700;
  font-family: 'Sora', 'DM Sans', Arial, sans-serif;
  letter-spacing: 0.02em;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
  border: 1.5px solid transparent;
  line-height: 1;
}

.btn-nav:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
  box-shadow: none;
}

.btn-prev {
  background: transparent;
  color: #17324f;
  border-color: #17324f;
}

.btn-prev:hover:not(:disabled) {
  background: #0b1a2a;
  color: #fff;
  border-color: #0b1a2a;
}

.btn-next,
.btn-save {
  background: linear-gradient(180deg, #15385a 0%, #0e2740 100%);
  color: #fff;
  border-color: #0e2740;
  box-shadow: 0 4px 12px rgba(15,30,46,0.28);
}

.btn-next:hover:not(:disabled),
.btn-save:hover:not(:disabled) {
  background: linear-gradient(180deg, #1b466f 0%, #123150 100%);
  box-shadow: 0 4px 16px rgba(15,30,46,0.35);
  transform: translateY(-1px);
}
</style>
