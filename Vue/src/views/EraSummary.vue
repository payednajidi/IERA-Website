<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../services/api'
import { markStepCompleted, resetEraProgress, setCurrentAssessmentId } from '../services/eraProgress'

const route = useRoute()
const router = useRouter()
const assessmentId = Number(route.params.assessmentId)

const loading = ref(true)
const errorMessage = ref('')
const resetting = ref(false)
const savingAll = ref(false)
const saveFeedback = ref('')
const tasks = ref([])
const summaryRows = ref([])

const FACTOR_CONFIG = [
  { key: 'awkward_posture', label: 'Awkward Posture', totalScore: 13, threshold: 6, painEnabled: true },
  { key: 'static_sustained', label: 'Static & Sustained Work Posture', totalScore: 3, threshold: 1, painEnabled: true },
  { key: 'forceful_exertion', label: 'Forceful Exertion', totalScore: 1, threshold: 1, painEnabled: true },
  { key: 'repetition', label: 'Repetition', totalScore: 5, threshold: 1, painEnabled: true },
  { key: 'vibration', label: 'Vibration', totalScore: 4, threshold: 1, painEnabled: true },
  { key: 'lighting', label: 'Lighting', totalScore: 1, threshold: 1, painEnabled: false },
  { key: 'temperature', label: 'Temperature', totalScore: 1, threshold: 1, painEnabled: false },
  { key: 'ventilation', label: 'Ventilation', totalScore: 1, threshold: 1, painEnabled: false },
  { key: 'noise', label: 'Noise', totalScore: 2, threshold: 1, painEnabled: false },
]

const FORCEFUL_GROUPS = [
  {
    key: 'lifting_lowering',
    label: 'Lifting/Lowering',
    rowKeys: [
      'lifting_lowering',
      'repetitive_lifting_lowering',
      'twisted_posture_lifting_lowering',
      'repetitive_with_twisted_posture',
    ],
  },
  { key: 'pushing_pulling', label: 'Pushing/Pulling', rowKeys: ['pushing_pulling'] },
  { key: 'handling_seated', label: 'Handling in Seated Position', rowKeys: ['handling_seated_position'] },
  { key: 'carrying', label: 'Carrying', rowKeys: ['carrying'] },
  { key: 'other', label: 'Other', rowKeys: ['other_forceful_activity'] },
]

const BADGE_CLASS = (needAdvanced) => (needAdvanced ? 'badge-yes' : 'badge-no')
const BODY_PART_OPTIONS = [
  'Neck',
  'Shoulder',
  'Upper back',
  'Lower back',
  'Upper arm',
  'Elbow',
  'Lower Arm',
  'Hand/Wrist',
  'Thigh',
  'Knee',
  'Lower leg',
  'Ankle/Foot',
]

const headerClassForTask = (index) => (index % 2 === 0 ? 'task-head-a' : 'task-head-b')

const toBool = (value) => value === true || value === 1 || value === '1'

const uniq = (arr) => Array.from(new Set(arr))

const normalizePainParts = (bodyPart) => {
  if (!bodyPart || typeof bodyPart !== 'string') return []
  const s = bodyPart.toLowerCase()
  const out = []

  if (s.includes('neck') || s.includes('head')) out.push('Neck')
  if (s.includes('shoulder')) out.push('Shoulder')
  if (s.includes('upper back')) out.push('Upper Back')
  if (s.includes('lower back')) out.push('Lower Back')
  if (s.includes('back') && !s.includes('upper back') && !s.includes('lower back')) out.push('Lower Back')
  if (s.includes('upper arm')) out.push('Upper Arm')
  if (s.includes('lower arm')) out.push('Lower Arm')
  if (s.includes('arm') && !s.includes('upper arm') && !s.includes('lower arm') && !s.includes('hand-arm')) out.push('Upper Arm')
  if (s.includes('elbow')) out.push('Elbow')
  if (s.includes('hand') || s.includes('wrist')) out.push('Hand/Wrist')
  if (s.includes('thigh')) out.push('Thigh')
  if (s.includes('knee')) out.push('Knee')
  if (s.includes('lower leg')) out.push('Lower Leg')
  if (s.includes('leg') && !s.includes('lower leg')) out.push('Lower Leg')
  if (s.includes('ankle') || s.includes('foot')) out.push('Ankle/Foot')
  if (s.includes('whole body')) out.push('Whole Body')
  if (s.includes('hand-arm') || s.includes('hand arm')) out.push('Hand-Arm')

  return uniq(out)
}

const responseForTask = (responses, taskId) => {
  if (!Array.isArray(responses)) return null
  return responses.find(r => Number(r.task_id) === Number(taskId)) || null
}

const yesValue = (resp) => Boolean(resp && toBool(resp.answer) && !toBool(resp.not_applicable))

const countYesRows = (rows, taskId, allowedKeys = null) => {
  if (!Array.isArray(rows)) return 0

  const keySet = allowedKeys ? new Set(allowedKeys) : null
  let count = 0

  rows.forEach(row => {
    if (keySet && !keySet.has(String(row.key))) return
    const resp = responseForTask(row.responses, taskId)
    if (yesValue(resp)) count += 1
  })

  return count
}

const painPartsFromRows = (rows, taskId, bodyPartField = 'body_part') => {
  if (!Array.isArray(rows)) return []

  const parts = []
  rows.forEach(row => {
    const resp = responseForTask(row.responses, taskId)
    if (yesValue(resp)) {
      parts.push(...normalizePainParts(row[bodyPartField]))
    }
  })

  return uniq(parts)
}

const buildSummary = (payload) => {
  const checklist = payload.checklist?.data ?? {}
  const forceful = payload.forceful?.data ?? {}
  const repetitive = payload.repetitive?.data ?? {}
  const vibration = payload.vibration?.data ?? {}
  const environmental = payload.environmental?.data ?? {}

  const checklistTasks = Array.isArray(checklist.tasks) ? checklist.tasks : []
  tasks.value = checklistTasks.map(t => ({
    id: Number(t.id),
    title: t.title || `Task ${t.id}`,
  }))

  const templates = Array.isArray(checklist.templates) ? checklist.templates : []
  const answers = Array.isArray(checklist.answers) ? checklist.answers : []

  const awkwardTemplate = templates.find(t => String(t.name || '').toUpperCase().includes('AWKWARD POSTURE'))
  const staticTemplate = templates.find(t => String(t.name || '').toUpperCase().includes('STATIC AND SUSTAINED WORK POSTURE'))

  const awkwardItems = Array.isArray(awkwardTemplate?.items) ? awkwardTemplate.items : []
  const staticItems = Array.isArray(staticTemplate?.items) ? staticTemplate.items : []
  const awkwardItemMap = new Map(awkwardItems.map(i => [Number(i.id), i]))
  const staticItemMap = new Map(staticItems.map(i => [Number(i.id), i]))

  const answerMap = new Map()
  answers.forEach(a => {
    const key = `${Number(a.task_id)}_${Number(a.checklist_item_id)}`
    answerMap.set(key, toBool(a.answer))
  })

  const checklistScoreAndPain = (taskId, itemMap) => {
    let score = 0
    const painParts = []

    itemMap.forEach((item, itemId) => {
      if (answerMap.get(`${Number(taskId)}_${itemId}`)) {
        score += 1
        painParts.push(...normalizePainParts(item.body_part))
      }
    })

    return { score, painParts: uniq(painParts) }
  }

  const forcefulManualRows = Array.isArray(forceful.manual_summary?.rows) ? forceful.manual_summary.rows : []
  const repetitiveRows = Array.isArray(repetitive.rows) ? repetitive.rows : []
  const vibrationRows = Array.isArray(vibration.rows) ? vibration.rows : []
  const environmentalRows = Array.isArray(environmental.rows) ? environmental.rows : []

  const rows = FACTOR_CONFIG.map(factor => {
    const taskResults = tasks.value.map(task => {
      const taskId = Number(task.id)

      const awkward = checklistScoreAndPain(taskId, awkwardItemMap)
      const statik = checklistScoreAndPain(taskId, staticItemMap)

      const repetitionScore = countYesRows(repetitiveRows, taskId)
      const repetitionPain = painPartsFromRows(repetitiveRows, taskId)
      const vibrationScore = countYesRows(vibrationRows, taskId)
      const vibrationPain = painPartsFromRows(vibrationRows, taskId)

      const forcefulBreakdown = {}
      FORCEFUL_GROUPS.forEach(group => {
        forcefulBreakdown[group.label] = countYesRows(forcefulManualRows, taskId, group.rowKeys)
      })
      const forcefulScore = Object.values(forcefulBreakdown).reduce((sum, value) => sum + Number(value), 0)

      const lightingScore = countYesRows(environmentalRows, taskId, ['inadequate_lighting'])
      const temperatureScore = countYesRows(environmentalRows, taskId, ['extreme_temperature'])
      const ventilationScore = countYesRows(environmentalRows, taskId, ['inadequate_air_ventilation'])
      const noiseScore = countYesRows(environmentalRows, taskId, ['noise_above_pel', 'annoying_noise_more_than_8_hours'])

      let score = 0
      let painParts = []
      let details = null

      if (factor.key === 'awkward_posture') {
        score = awkward.score
        painParts = awkward.painParts
      } else if (factor.key === 'static_sustained') {
        score = statik.score
        painParts = statik.painParts
      } else if (factor.key === 'forceful_exertion') {
        score = forcefulScore
        painParts = uniq([...awkward.painParts, ...statik.painParts, ...repetitionPain])
        details = forcefulBreakdown
      } else if (factor.key === 'repetition') {
        score = repetitionScore
        painParts = repetitionPain
      } else if (factor.key === 'vibration') {
        score = vibrationScore
        painParts = vibrationPain
      } else if (factor.key === 'lighting') {
        score = lightingScore
      } else if (factor.key === 'temperature') {
        score = temperatureScore
      } else if (factor.key === 'ventilation') {
        score = ventilationScore
      } else if (factor.key === 'noise') {
        score = noiseScore
      }

      const scoreTrigger = Number(score) >= Number(factor.threshold)
      const painTrigger = factor.painEnabled && painParts.length > 0
      const needAdvanced = scoreTrigger

      return {
        taskId,
        score,
        details,
        painParts,
        painTrigger,
        needAdvanced,
      }
    })

    const taskResultsById = Object.fromEntries(taskResults.map(result => [String(result.taskId), result]))

    return {
      key: factor.key,
      label: factor.label,
      totalScore: factor.totalScore,
      threshold: factor.threshold,
      taskResults,
      taskResultsById,
    }
  })

  summaryRows.value = rows
}

const getWithFallbackOn422 = async (url, fallbackData) => {
  try {
    return await api.get(url)
  } catch (error) {
    if (error.response?.status === 422) {
      return { data: fallbackData }
    }
    throw error
  }
}

const load = async () => {
  loading.value = true
  errorMessage.value = ''
  saveFeedback.value = ''

  try {
    setCurrentAssessmentId(assessmentId)
    const [checklist, forceful, repetitive, vibration, environmental, summaryPain] = await Promise.all([
      api.get(`/era-checklist/${assessmentId}`),
      getWithFallbackOn422(`/era-forceful-exertion/${assessmentId}`, {
        rows: [],
        push_pull: { activities: [], task_not_applicable: {} },
        carrying_summary: { rows: [], task_not_applicable: {} },
        manual_summary: { rows: [], task_not_applicable: {} },
      }),
      getWithFallbackOn422(`/era-repetitive-motion/${assessmentId}`, { rows: [], task_not_applicable: {} }),
      getWithFallbackOn422(`/era-vibration/${assessmentId}`, { rows: [], task_not_applicable: {} }),
      getWithFallbackOn422(`/era-environmental-factors/${assessmentId}`, { rows: [], task_not_applicable: {} }),
      api.get(`/era-summary-pain-parts/${assessmentId}`).catch(() => ({ data: { has_saved: false, pain_parts: {} } })),
    ])

    buildSummary({ checklist, forceful, repetitive, vibration, environmental })
    initializePainSelections(summaryPain.data?.pain_parts ?? {}, Boolean(summaryPain.data?.has_saved))
  } catch (error) {
    console.error(error.response?.data || error)
    errorMessage.value = 'Unable to load the Initial ERA Summary. Please try again.'
  } finally {
    loading.value = false
  }
}

const resetAssessment = async () => {
  if (resetting.value) return

  const confirmed = window.confirm('Start a new ERA assessment? This will reset all step progress ticks and return to Step 1.')
  if (!confirmed) return

  resetting.value = true

  try {
    // Avoid emitting progress event before route changes so Sidebar
    // does not immediately re-bind the current route assessment ID.
    resetEraProgress(assessmentId, { emitEvent: false })
    await router.push('/era-form')
  } finally {
    resetting.value = false
  }
}

const hasData = computed(() => summaryRows.value.length > 0 && tasks.value.length > 0)
const TASKS_PER_TABLE = 2

const taskGroups = computed(() => {
  const out = []
  for (let i = 0; i < tasks.value.length; i += TASKS_PER_TABLE) {
    out.push({
      index: out.length,
      start: i + 1,
      end: Math.min(i + TASKS_PER_TABLE, tasks.value.length),
      tasks: tasks.value.slice(i, i + TASKS_PER_TABLE),
    })
  }
  return out
})

const EMPTY_RESULT = Object.freeze({
  taskId: null,
  score: 0,
  details: null,
  painParts: [],
  painTrigger: false,
  needAdvanced: false,
})

const getTaskResult = (row, taskId) => row?.taskResultsById?.[String(taskId)] ?? EMPTY_RESULT

const normalizePainPartKey = (part) => String(part || '').toLowerCase().replace(/[-_]/g, ' ').replace(/\s+/g, ' ').trim()

const derivedTaskPainPartLookup = computed(() => {
  const out = {}

  summaryRows.value.forEach(row => {
    ;(row.taskResults || []).forEach(result => {
      const taskKey = String(result.taskId)
      if (!out[taskKey]) out[taskKey] = new Set()
      ;(result.painParts || []).forEach(part => {
        const key = normalizePainPartKey(part)
        if (key) out[taskKey].add(key)
      })
    })
  })

  return out
})

const taskPainSelections = ref({})

const initializePainSelections = (savedPainPartsByTask = {}, hasSaved = false) => {
  const out = {}

  tasks.value.forEach(task => {
    const taskKey = String(task.id)
    const normalized = {}

    BODY_PART_OPTIONS.forEach(part => {
      normalized[normalizePainPartKey(part)] = false
    })

    const savedPartsRaw = Array.isArray(savedPainPartsByTask?.[taskKey]) ? savedPainPartsByTask[taskKey] : []
    const sourceParts = hasSaved
      ? savedPartsRaw
      : Array.from(derivedTaskPainPartLookup.value?.[taskKey] ?? [])

    sourceParts.forEach(part => {
      const key = normalizePainPartKey(part)
      if (Object.prototype.hasOwnProperty.call(normalized, key)) {
        normalized[key] = true
      }
    })

    out[taskKey] = normalized
  })

  taskPainSelections.value = out
}

const isPainSelected = (taskId, part) => {
  const taskMap = taskPainSelections.value?.[String(taskId)] ?? {}
  return Boolean(taskMap[normalizePainPartKey(part)])
}

const setPainSelected = (taskId, part, checked) => {
  const taskKey = String(taskId)
  const partKey = normalizePainPartKey(part)

  if (!taskPainSelections.value[taskKey]) {
    taskPainSelections.value[taskKey] = {}
  }

  taskPainSelections.value[taskKey][partKey] = Boolean(checked)
}

const buildPainPartsPayload = () => {
  const out = []

  tasks.value.forEach(task => {
    const taskKey = String(task.id)
    const map = taskPainSelections.value?.[taskKey] ?? {}

    BODY_PART_OPTIONS.forEach(part => {
      const partKey = normalizePainPartKey(part)
      if (Boolean(map[partKey])) {
        out.push({
          task_id: Number(task.id),
          body_part: partKey,
        })
      }
    })
  })

  return out
}

const saveAllChanged = async () => {
  if (!assessmentId) {
    alert('Please submit Step 1 first to create an assessment before saving.')
    return
  }

  if (savingAll.value) return

  savingAll.value = true
  saveFeedback.value = ''

  try {
    await api.post('/era-summary-pain-parts', {
      assessment_id: assessmentId,
      pain_parts: buildPainPartsPayload(),
    })

    markStepCompleted(assessmentId, 7)
    await load()
    saveFeedback.value = 'All changes saved successfully.'
  } catch (error) {
    console.error(error.response?.data || error)
    saveFeedback.value = 'Unable to save changes. Please try again.'
  } finally {
    savingAll.value = false
  }
}

onMounted(() => {
  load()
})
</script>

<template>
  <div v-if="loading" class="loading-state">Loading Initial ERA summary...</div>

  <div v-else class="summary-wrapper">
    <div class="page-hero">
      <div class="hero-left">
        <div class="hero-tag">Step 7 of 7</div>
        <h1 class="hero-title">Final Summary Scorecard</h1>
        <p class="hero-sub">File: `EraSummary.vue` - Consolidated ERA result and advanced ERA decision. Assessment ID: #{{ assessmentId }}</p>
      </div>
      <div class="hero-steps">
        <div v-for="s in 7" :key="s" class="step-pip" :class="{ active: s === 7 }">
          <div class="pip-dot"></div>
          <div class="pip-label">Step {{ s }}</div>
        </div>
      </div>
    </div>

    <div v-if="errorMessage" class="blocked-panel">
      <div class="blocked-title">Unable to load summary</div>
      <div class="blocked-text">{{ errorMessage }}</div>
      <button class="btn-nav btn-prev" @click="load">Retry</button>
    </div>

    <template v-else-if="hasData">
      <div class="tables-stack">
        <section v-for="group in taskGroups" :key="`group-${group.index}`" class="summary-section">
          <div class="section-caption" :class="{ 'section-caption-muted': group.index > 0 }">
            {{ group.index === 0 ? 'Overall Summary' : 'Continued Summary' }} (Task {{ group.start }}-{{ group.end }})
          </div>

          <div class="table-wrap">
            <table class="summary-table">
              <thead>
                <tr>
                  <th colspan="3" class="fixed-head-spacer"></th>
                  <template v-for="(task, taskIndex) in group.tasks" :key="`task-head-${group.index}-${task.id}`">
                    <th colspan="3" :class="headerClassForTask((group.start - 1) + taskIndex)">{{ task.title }}</th>
                  </template>
                </tr>
                <tr>
                  <th class="letter-head">A</th>
                  <th class="letter-head">B</th>
                  <th class="letter-head">C</th>
                  <template v-for="task in group.tasks" :key="`letter-head-${group.index}-${task.id}`">
                    <th class="letter-head">D</th>
                    <th class="letter-head">E</th>
                    <th class="letter-head">F</th>
                  </template>
                </tr>
                <tr>
                  <th class="col-risk">Risk Factor</th>
                  <th class="col-total">Total score</th>
                  <th class="col-threshold">Minimum Requirements for Advance ERA</th>
                  <template v-for="task in group.tasks" :key="`sub-head-${group.index}-${task.id}`">
                    <th>Result of Initial ERA</th>
                    <th>Any pain or discomfort due to risk factors as found in MSD assessment Refer Part 3.1 (Yes/No)</th>
                    <th>Need advanced ERA (Yes/No)</th>
                  </template>
                </tr>
              </thead>

              <tbody>
                <tr v-for="(row, rowIndex) in summaryRows" :key="`${group.index}-${row.key}`">
                  <td class="risk-name">{{ row.label }}</td>
                  <td class="total-cell">{{ row.totalScore }}</td>
                  <td class="threshold-cell">>= {{ row.threshold }}</td>

                  <template v-for="task in group.tasks" :key="`${group.index}-${row.key}-${task.id}`">
                    <td class="score-cell">
                      <div class="score-main">{{ getTaskResult(row, task.id).score }}</div>
                      <div v-if="row.key === 'forceful_exertion' && getTaskResult(row, task.id).details" class="score-breakdown">
                        <div
                          v-for="(value, label) in getTaskResult(row, task.id).details"
                          :key="`${task.id}-${row.key}-${label}`"
                          class="score-line"
                        >
                          <span>{{ label }}</span>
                          <strong>{{ value }}</strong>
                        </div>
                      </div>
                    </td>

                    <td
                      v-if="rowIndex === 0"
                      class="pain-merged-cell"
                      :rowspan="summaryRows.length"
                    >
                      <div class="pain-merged-title">If YES please tick which part of body</div>
                      <table class="pain-grid">
                        <tbody>
                          <tr v-for="part in BODY_PART_OPTIONS" :key="`${task.id}-pain-${part}`">
                            <td class="pain-grid-part">{{ part }}</td>
                            <td class="pain-grid-mark">
                              <button
                                type="button"
                                class="pain-mark-btn"
                                :class="{ 'pain-mark-btn-checked': isPainSelected(task.id, part) }"
                                @click="setPainSelected(task.id, part, !isPainSelected(task.id, part))"
                              >
                                {{ isPainSelected(task.id, part) ? 'X' : '' }}
                              </button>
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </td>

                    <td class="verdict-cell">
                      <span class="verdict-badge" :class="BADGE_CLASS(getTaskResult(row, task.id).needAdvanced)">
                        {{ getTaskResult(row, task.id).needAdvanced ? 'YES' : 'NO' }}
                      </span>
                    </td>
                  </template>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </div>

      <div class="action-row">
        <button class="btn-nav btn-prev" @click="router.push(`/era-environmental-factors/${assessmentId}`)">Previous</button>
        <button class="btn-nav btn-save" :disabled="savingAll || resetting" @click="saveAllChanged">
          {{ savingAll ? 'Saving...' : 'Saved All Changed' }}
        </button>
        <button class="btn-nav btn-reset" :disabled="resetting" @click="resetAssessment">
          {{ resetting ? 'Resetting...' : 'Reset Assessment' }}
        </button>
      </div>
      <div v-if="saveFeedback" class="save-feedback">{{ saveFeedback }}</div>
    </template>
  </div>
</template>

<style scoped>
.loading-state { padding: 40px; text-align: center; }

.summary-wrapper {
  font-family: DM Sans, Arial, sans-serif;
  display: flex;
  flex-direction: column;
  gap: 16px;
  min-width: 0;
}

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

.blocked-panel {
  background: #fff5f5;
  border: 1px solid #f0c2c2;
  border-radius: 6px;
  padding: 14px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.blocked-title { font-weight: 700; }

.tables-stack {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.summary-section {
  margin: 8px 0;
}

.section-caption {
  font-size: 13px;
  font-weight: 700;
  color: #2f3e4d;
  margin-bottom: 8px;
}

.section-caption-muted {
  color: #536170;
}

.table-wrap {
  overflow-x: auto;
  border: 1px solid #ddd;
  border-radius: 6px;
  background: #fff;
  margin: 10px 0;
}

.summary-table {
  width: 100%;
  min-width: 760px;
  border-collapse: collapse;
  table-layout: fixed;
  font-size: 13px;
}

.summary-table th,
.summary-table td {
  border: 1px solid #333;
  padding: 6px;
  vertical-align: top;
  word-break: break-word;
}

.summary-table th {
  background: #f2f2f2;
  text-align: center;
}

.fixed-head-spacer {
  background: #f2f2f2;
}

.letter-head {
  font-size: 14px;
  font-weight: 800;
  padding: 3px 4px !important;
  line-height: 1.1;
}

.task-head-a {
  background: #8f1f1f !important;
  color: #fff;
  font-size: 15px;
}

.task-head-b {
  background: #1f4c8f !important;
  color: #fff;
  font-size: 15px;
}

.col-risk { width: 150px; }
.col-total { width: 80px; }
.col-threshold { width: 110px; }

.risk-name { font-weight: 700; }
.total-cell { text-align: center; font-weight: 700; }
.threshold-cell { text-align: center; font-weight: 700; }

.score-cell { width: 90px; text-align: center; }
.score-main { font-size: 16px; font-weight: 700; line-height: 1.1; }

.score-breakdown {
  margin-top: 8px;
  border: 1px solid #d8d8d8;
  border-radius: 4px;
  text-align: left;
  overflow: hidden;
}

.score-line {
  display: flex;
  justify-content: space-between;
  gap: 8px;
  padding: 4px 6px;
  font-size: 12px;
  border-bottom: 1px solid #e5e5e5;
}

.score-line:last-child { border-bottom: 0; }

.pain-merged-cell {
  width: 190px;
  min-width: 190px;
  background: #efe6c8;
  vertical-align: top;
}

.pain-merged-title {
  font-size: 18px;
  line-height: 1.25;
  margin-bottom: 6px;
}

.pain-grid {
  width: 100%;
  border-collapse: collapse;
  table-layout: fixed;
  background: #fff;
}

.pain-grid td {
  border: 1px solid #333;
  padding: 2px 6px;
  font-size: 14px;
  line-height: 1.2;
}

.pain-grid-part {
  text-align: left;
}

.pain-grid-mark {
  width: 26px;
  text-align: center;
  font-weight: 800;
  padding: 0 !important;
}

.pain-mark-btn {
  width: 100%;
  min-height: 22px;
  border: 0;
  background: transparent;
  color: #111;
  font-size: 16px;
  font-weight: 800;
  cursor: pointer;
}

.pain-mark-btn:hover {
  background: #f7f7f7;
}

.pain-mark-btn-checked {
  background: #f0f0f0;
}

.verdict-cell {
  width: 100px;
  text-align: center;
}

.verdict-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 72px;
  padding: 6px 10px;
  font-size: 13px;
  font-weight: 800;
  color: #fff;
  border-radius: 4px;
}

.badge-yes { background: #d72626; }
.badge-no { background: #0d9d4a; }

.action-row {
  display: flex;
  justify-content: flex-start;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

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

.btn-nav:disabled { opacity: 0.6; cursor: not-allowed; transform: none; box-shadow: none; }

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

.btn-save {
  background: linear-gradient(180deg, #15385a 0%, #0e2740 100%);
  color: #fff;
  border-color: #0e2740;
  box-shadow: 0 4px 12px rgba(15,30,46,0.28);
}
.btn-save:hover:not(:disabled) {
  background: linear-gradient(180deg, #1b466f 0%, #123150 100%);
  box-shadow: 0 4px 16px rgba(15,30,46,0.35);
  transform: translateY(-1px);
}

.btn-reset { background: #b11a1a; color: #fff; border-color: #8f1616; }
.btn-reset:disabled { opacity: 0.7; cursor: not-allowed; }

.save-feedback {
  font-size: 13px;
  color: #2f3e4d;
}

@media (max-width: 768px) {
  .page-hero { flex-direction: column; }
  .hero-steps { flex-wrap: wrap; }
  .hero-title { font-size: 28px; }
}
</style>
