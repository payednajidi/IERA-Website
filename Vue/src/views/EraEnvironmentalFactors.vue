<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../services/api'
import { markStepCompleted, setCurrentAssessmentId } from '../services/eraProgress'

const route = useRoute()
const router = useRouter()
const assessmentId = Number(route.params.assessmentId)

const loading = ref(true)
const saving = ref(false)
const tasks = ref([])
const rows = ref([])
const taskNotApplicable = ref({})

const rowTemplate = [
  { key: 'inadequate_lighting', physical_risk_factor: 'Inadequate lighting' },
  { key: 'extreme_temperature', physical_risk_factor: 'Extreme temperature (hot/cold)' },
  { key: 'inadequate_air_ventilation', physical_risk_factor: 'Inadequate air ventilation or poor IAQ' },
  { key: 'noise_above_pel', physical_risk_factor: 'Noise exposure above PEL' },
  { key: 'annoying_noise_more_than_8_hours', physical_risk_factor: 'Exposed to annoying noise more than 8 hours' },
]

const defaultRows = () => {
  return rowTemplate.map(row => ({
    ...row,
    remarks: '',
    responses: tasks.value.map(task => ({
      task_id: task.id,
      answer: false,
      not_applicable: false,
    })),
  }))
}

const defaultTaskNotApplicable = () => {
  const out = {}
  tasks.value.forEach(task => {
    out[String(task.id)] = false
  })
  return out
}

const normalizeRows = apiRows => {
  const source = Array.isArray(apiRows) ? apiRows : []
  return rowTemplate.map(row => {
    const found = source.find(item => item.key === row.key) || {}
    const savedResponses = Array.isArray(found.responses) ? found.responses : []

    return {
      ...row,
      remarks: found.remarks ?? '',
      responses: tasks.value.map(task => {
        const saved = savedResponses.find(resp => Number(resp.task_id) === Number(task.id)) || {}
        return {
          task_id: task.id,
          answer: typeof saved.answer === 'boolean' ? saved.answer : false,
          not_applicable: typeof saved.not_applicable === 'boolean' ? saved.not_applicable : false,
        }
      }),
    }
  })
}

const responseFor = (row, taskId) => row.responses.find(resp => Number(resp.task_id) === Number(taskId))
const isTaskNA = taskId => Boolean(taskNotApplicable.value[String(taskId)])

const setTaskNA = (taskId, checked) => {
  taskNotApplicable.value[String(taskId)] = checked
  rows.value.forEach(row => {
    const target = responseFor(row, taskId)
    if (!target) return
    target.not_applicable = checked
    if (checked) target.answer = false
  })
}

const getAnswer = (row, taskId) => Boolean(responseFor(row, taskId)?.answer)

const setAnswer = (rowIndex, taskId, value) => {
  if (isTaskNA(taskId)) return
  const row = rows.value[rowIndex]
  if (!row) return
  const target = responseFor(row, taskId)
  if (!target) return
  target.answer = value
}

const yesCount = taskId => {
  if (isTaskNA(taskId)) return 0
  return rows.value.filter(row => getAnswer(row, taskId) === true).length
}

const noCount = taskId => {
  if (isTaskNA(taskId)) return 0
  return rows.value.filter(row => getAnswer(row, taskId) === false).length
}

const loadPage = async () => {
  try {
    const checklist = await api.get(`/era-checklist/${assessmentId}`)
    tasks.value = checklist.data.tasks ?? []
    setCurrentAssessmentId(assessmentId)

    if (!(checklist.data.answers ?? []).length) {
      rows.value = defaultRows()
      taskNotApplicable.value = defaultTaskNotApplicable()
      return
    }

    const response = await api.get(`/era-environmental-factors/${assessmentId}`)
    rows.value = normalizeRows(response.data.rows)

    const apiTaskNA = response.data.task_not_applicable ?? {}
    const normalizedTaskNA = defaultTaskNotApplicable()
    tasks.value.forEach(task => {
      if (typeof apiTaskNA[String(task.id)] === 'boolean') {
        normalizedTaskNA[String(task.id)] = apiTaskNA[String(task.id)]
      }
    })
    taskNotApplicable.value = normalizedTaskNA
  } catch (error) {
    console.error(error.response?.data || error)
    rows.value = defaultRows()
    taskNotApplicable.value = defaultTaskNotApplicable()
  } finally {
    loading.value = false
  }
}

const save = async () => {
  if (!assessmentId) {
    alert('Please submit Step 1 first to create an assessment before saving.')
    return
  }

  if (saving.value) return
  saving.value = true

  try {
    await api.post('/era-environmental-factors', {
      assessment_id: assessmentId,
      task_not_applicable: taskNotApplicable.value,
      rows: rows.value,
    })

    markStepCompleted(assessmentId, 6)
    router.push(`/era-summary/${assessmentId}`)
  } catch (error) {
    console.error(error.response?.data || error)
    alert('Error saving environmental factors checklist.')
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  loadPage()
})
</script>

<template>
  <div v-if="loading" class="loading-state">Loading environmental factors checklist...</div>

  <div v-else class="page-wrapper">
    <div class="page-hero">
      <div class="hero-left">
        <div class="hero-tag">Step 6 of 7</div>
        <h1 class="hero-title">Environmental Factors</h1>
        <p class="hero-sub">File: `EraEnvironmentalFactors.vue` - Environmental ergonomic risk checklist. Assessment ID: #{{ assessmentId }}</p>
      </div>
      <div class="hero-steps">
        <div v-for="s in 7" :key="s" class="step-pip" :class="{ active: s === 6 }">
          <div class="pip-dot"></div>
          <div class="pip-label">Step {{ s }}</div>
        </div>
      </div>
    </div>

    <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th rowspan="2">Physical Risk Factor</th>
              <th v-for="task in tasks" :key="`head-${task.id}`" colspan="2">
                <div>{{ task.title }}</div>
                <label class="na-toggle">
                  <input
                    type="checkbox"
                    :checked="isTaskNA(task.id)"
                    @change="setTaskNA(task.id, $event.target.checked)"
                  />
                  Not Applicable
                </label>
              </th>
              <th rowspan="2">Remarks</th>
            </tr>
            <tr>
              <template v-for="task in tasks" :key="`sub-${task.id}`">
                <th>Yes</th>
                <th>No</th>
              </template>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, rowIndex) in rows" :key="row.key">
              <td>{{ row.physical_risk_factor }}</td>
              <template v-for="task in tasks" :key="`row-${row.key}-${task.id}`">
                <td class="td-center">
                  <input
                    type="radio"
                    :name="`yesno-${row.key}-${task.id}`"
                    :checked="getAnswer(row, task.id) === true"
                    :disabled="isTaskNA(task.id)"
                    @change="setAnswer(rowIndex, task.id, true)"
                  />
                </td>
                <td class="td-center">
                  <input
                    type="radio"
                    :name="`yesno-${row.key}-${task.id}`"
                    :checked="getAnswer(row, task.id) === false"
                    :disabled="isTaskNA(task.id)"
                    @change="setAnswer(rowIndex, task.id, false)"
                  />
                </td>
              </template>
              <td>
                <input v-model="row.remarks" class="cell-input" placeholder="Remarks" />
              </td>
            </tr>
            <tr class="subtotal-row">
              <td class="subtotal-label">Sub Total (Number of tick(s))</td>
              <template v-for="task in tasks" :key="`subtotal-${task.id}`">
                <td class="subtotal-yes">{{ yesCount(task.id) }}</td>
                <td class="subtotal-no">{{ noCount(task.id) }}</td>
              </template>
              <td></td>
            </tr>
          </tbody>
        </table>
    </div>

    <div class="action-row">
      <button class="btn-nav btn-prev" @click="router.push(`/era-vibration/${assessmentId}`)">Previous</button>
      <button class="btn-nav btn-next" :disabled="saving" @click="save">
        {{ saving ? 'Saving...' : 'Save & Proceed (Step 7)' }}
      </button>
    </div>
  </div>
</template>

<style scoped>
.loading-state { padding: 40px; text-align: center; }
.page-wrapper { font-family: DM Sans, Arial, sans-serif; display: flex; flex-direction: column; gap: 16px; }
.page-hero { display: flex; align-items: flex-start; justify-content: space-between; gap: 20px; padding: 22px 24px; background: linear-gradient(135deg, #0b1a2a 0%, #17324f 58%, #224f7a 100%); border-radius: 10px; }
.hero-left { display: flex; flex-direction: column; gap: 6px; }
.hero-tag { width: fit-content; padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; letter-spacing: 0.05em; color: #e8a020; background: rgba(232,160,32,0.25); border: 1px solid rgba(232,160,32,0.55); text-transform: uppercase; }
.hero-title { font-size: 36px; font-weight: 700; color: #f7fbff; line-height: 1.2; }
.hero-sub { font-size: 13px; color: rgba(231,241,251,0.96); max-width: 780px; line-height: 1.5; }
.hero-steps { display: flex; gap: 8px; align-items: center; padding-top: 4px; }
.step-pip { display: flex; flex-direction: column; align-items: center; gap: 4px; opacity: 0.65; }
.step-pip.active { opacity: 1; }
.pip-dot { width: 10px; height: 10px; border-radius: 50%; background: rgba(255,255,255,0.45); border: 2px solid rgba(255,255,255,0.25); }
.step-pip.active .pip-dot { background: #e8a020; border-color: #e8a020; box-shadow: 0 0 8px #e8a020; }
.pip-label { font-size: 9px; color: rgba(255,255,255,0.78); font-weight: 700; text-transform: uppercase; white-space: nowrap; }
.step-pip.active .pip-label { color: #e8a020; }
.blocked-panel { background: #fff5f5; border: 1px solid #f0c2c2; border-radius: 6px; padding: 14px; display: flex; flex-direction: column; gap: 10px; }
.blocked-title { font-weight: 700; }
.table-wrap { overflow-x: auto; border: 1px solid #ddd; border-radius: 6px; background: #fff; }
.table { width: 100%; min-width: 1000px; border-collapse: collapse; }
.table th, .table td { border: 1px solid #333; padding: 8px; vertical-align: middle; }
.table th { background: #f2f2f2; }
.td-center { text-align: center; }
.cell-input { width: 100%; box-sizing: border-box; border: 1px solid #bfc5cb; border-radius: 4px; padding: 6px 8px; }
.na-toggle { margin-top: 6px; display: inline-flex; align-items: center; gap: 4px; font-size: 12px; font-weight: 500; }
.subtotal-row { background: #f7f7f7; }
.subtotal-label { text-align: right; font-weight: 700; }
.subtotal-yes, .subtotal-no { text-align: center; font-weight: 700; color: #b21212; }
.action-row { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
.btn-nav {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 12px 24px;
  border-radius: 6px;
  border: 1.5px solid transparent;
  font-size: 14px;
  font-weight: 700;
  font-family: 'Sora', 'DM Sans', Arial, sans-serif;
  letter-spacing: 0.02em;
  cursor: pointer;
  transition: all 0.2s;
  line-height: 1;
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
.btn-next {
  background: linear-gradient(180deg, #15385a 0%, #0e2740 100%);
  color: #fff;
  border-color: #0e2740;
  box-shadow: 0 4px 12px rgba(15,30,46,0.28);
}
.btn-next:hover:not(:disabled) {
  background: linear-gradient(180deg, #1b466f 0%, #123150 100%);
  box-shadow: 0 4px 16px rgba(15,30,46,0.35);
  transform: translateY(-1px);
}
.btn-nav:disabled { opacity: 0.6; cursor: not-allowed; transform: none; box-shadow: none; }
@media (max-width: 900px) { .page-hero { flex-direction: column; } .hero-steps { flex-wrap: wrap; } }
</style>
