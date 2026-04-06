<script setup>
import { ref, onMounted } from 'vue'
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
const referenceInfo = ref({ repetitive_handling: [], twisted_body_posture: [] })
const pushPull = ref({ conditions: [], activities: [], task_not_applicable: {} })
const carryingSummary = ref({ rows: [], task_not_applicable: {} })
const manualSummary = ref({ rows: [], task_not_applicable: {} })

const referenceImage = '/images/Forceful Exertion.png'
const seatedPositionImage = '/images/Seated Position.png'
const showReferenceImage = ref(true)
const showSeatedImage = ref(true)

const rowTemplate = [
  { key: 'between_floor_and_mid_lower_leg', working_height: 'Between floor to mid-lower leg' },
  { key: 'between_mid_lower_leg_and_knuckle', working_height: 'Between mid-lower leg to knuckle' },
  { key: 'between_knuckle_and_elbow', working_height: 'Between knuckle height and elbow' },
  { key: 'between_elbow_and_shoulder', working_height: 'Between elbow and shoulder' },
  { key: 'above_shoulder', working_height: 'Above the shoulder' },
]

const pushPullActivityTemplate = [
  { key: 'start_stop_load', activity: 'Stopping or starting a load', male_recommended: 'Approximately 1000 kg load (equivalent to 200N pushing or pulling force) on smooth level surface using well maintained handling aid', female_recommended: 'Approximately 750 kg load (equivalent to 150N pushing or pulling force) on smooth level surface using well maintained handling aid' },
  { key: 'keep_load_in_motion', activity: 'Keeping the load in motion', male_recommended: 'Approximately 100 kg load (equivalent to 100N pushing or pulling force) on uneven level surface using well maintained handling aid', female_recommended: 'Approximately 70 kg load (equivalent to 70N pushing or pulling force) on uneven level surface using well maintained handling aid' },
]
const defaultPushPullConditions = [
  'Force is applied using hands',
  'Hands are between knuckle and shoulder height',
  'Distance for pushing or pulling is less than 20 meters',
  'Load is being supported on wheels',
  'Pushing or pulling uses a well maintained handling aid',
]
const carryingSummaryRowTemplate = [
  { key: 'floor_surface_dry_clean', factor: 'Floor Surface', condition: 'Dry and clean floor in good condition', outcome: 'Acceptable' },
  { key: 'floor_surface_poor_uneven', factor: 'Floor Surface', condition: 'Dry floor but in poor condition, worn or uneven', outcome: 'Conduct advanced ERA' },
  { key: 'floor_surface_contaminated', factor: 'Floor Surface', condition: 'Contaminated/wet or steep sloping floor or unstable surface or unsuitable footwear', outcome: 'Conduct advanced ERA' },
  { key: 'other_environmental_no_factor', factor: 'Other environmental factors', condition: 'No factors present', outcome: 'Acceptable' },
  { key: 'other_environmental_has_factor', factor: 'Other environmental factors', condition: 'One or more factors present (e.g., poor lighting and strong air movements)', outcome: 'Conduct advanced ERA' },
  { key: 'carry_distance_2_10', factor: 'Carry distance', condition: '2 m-10 m', outcome: 'Acceptable' },
  { key: 'carry_distance_10_or_more', factor: 'Carry distance', condition: '10 m or more', outcome: 'Conduct advanced ERA' },
  { key: 'obstacles_none', factor: 'Obstacles on route', condition: 'No obstacles and carry route is flat', outcome: 'Acceptable' },
  { key: 'obstacles_present', factor: 'Obstacles on route', condition: 'Steep slope or up steps or through closed doors or trip hazards or using ladders', outcome: 'Conduct advanced ERA' },
  { key: 'other_carrying', factor: 'Other', condition: '', outcome: '' },
]
const manualSummaryRowTemplate = [
  { key: 'lifting_lowering', activity: 'Lifting and lowering; or', recommended_weight: 'Figure 3.1 & Table 3.3' },
  { key: 'repetitive_lifting_lowering', activity: 'Repetitive lifting and lowering; or', recommended_weight: 'Figure 3.1 & Table 3.4' },
  { key: 'twisted_posture_lifting_lowering', activity: 'Twisted body posture while lifting and lowering; or', recommended_weight: 'Figure 3.1 & Table 3.5' },
  { key: 'repetitive_with_twisted_posture', activity: 'Repetitive lifting and lowering with twisted body posture; or', recommended_weight: 'Based on Figure 3.1, Table 3.4 and Table 3.5' },
  { key: 'pushing_pulling', activity: 'Pushing and pulling; or', recommended_weight: 'Based on Table 3.6' },
  { key: 'handling_seated_position', activity: 'Handling in seated position; or', recommended_weight: 'Based on Figure 3.2' },
  { key: 'carrying', activity: 'Carrying', recommended_weight: 'Based on Table 3.7' },
  { key: 'other_forceful_activity', activity: 'Other Forceful Activity', recommended_weight: '' },
]
const defaultReferenceInfo = {
  repetitive_handling: [
    { if_employee_repeats: 'Once or twice per minutes', weight_reduction: '30%' },
    { if_employee_repeats: 'Five to eight times per minute', weight_reduction: '50%' },
    { if_employee_repeats: 'More than 12 times per minute', weight_reduction: '80%' },
  ],
  twisted_body_posture: [
    { twist_angle: '45 degrees', weight_reduction: '10%' },
    { twist_angle: '90 degrees', weight_reduction: '20%' },
  ],
}

const taskMap = () => Object.fromEntries(tasks.value.map(t => [String(t.id), false]))
const respForTasks = () => tasks.value.map(t => ({ task_id: t.id, answer: false, not_applicable: false }))

const buildDefaultRows = () => rowTemplate.map(r => ({ ...r, recommended_weight: '', current_weight: '', remarks: '', answers: tasks.value.map(t => ({ task_id: t.id, answer: false })) }))
const normalizeRows = apiRows => rowTemplate.map(def => {
  const existing = (Array.isArray(apiRows) ? apiRows : []).find(r => r.key === def.key || r.working_height === def.working_height) || {}
  return {
    ...def,
    recommended_weight: existing.recommended_weight ?? '',
    current_weight: existing.current_weight ?? '',
    remarks: existing.remarks ?? '',
    answers: tasks.value.map(t => {
      const saved = Array.isArray(existing.answers) ? existing.answers.find(a => Number(a.task_id) === t.id) : null
      return { task_id: t.id, answer: typeof saved?.answer === 'boolean' ? saved.answer : false }
    }),
  }
})
const buildDefaultPushPull = () => ({
  conditions: [...defaultPushPullConditions],
  task_not_applicable: taskMap(),
  activities: pushPullActivityTemplate.map(a => ({ ...a, responses: tasks.value.map(t => ({ task_id: t.id, answer: false, not_applicable: false })) })),
})
const normalizePushPull = api => {
  const out = buildDefaultPushPull()
  if (!api || typeof api !== 'object') return out
  if (Array.isArray(api.conditions) && api.conditions.length) out.conditions = api.conditions
  tasks.value.forEach(t => { if (typeof api.task_not_applicable?.[String(t.id)] === 'boolean') out.task_not_applicable[String(t.id)] = api.task_not_applicable[String(t.id)] })
  const arr = Array.isArray(api.activities) ? api.activities : []
  out.activities = out.activities.map(a => {
    const existingA = arr.find(x => x.key === a.key) || {}
    const existingR = Array.isArray(existingA.responses) ? existingA.responses : []
    return { ...a, responses: a.responses.map(r => {
      const ex = existingR.find(x => Number(x.task_id) === Number(r.task_id)) || {}
      return { task_id: r.task_id, answer: typeof ex.answer === 'boolean' ? ex.answer : false, not_applicable: typeof ex.not_applicable === 'boolean' ? ex.not_applicable : out.task_not_applicable[String(r.task_id)] }
    }) }
  })
  return out
}
const buildDefaultSummary = template => ({ task_not_applicable: taskMap(), rows: template.map(r => ({ ...r, remarks: '', responses: respForTasks() })) })
const normalizeSummary = (template, api) => {
  const out = buildDefaultSummary(template)
  if (!api || typeof api !== 'object') return out
  tasks.value.forEach(t => { if (typeof api.task_not_applicable?.[String(t.id)] === 'boolean') out.task_not_applicable[String(t.id)] = api.task_not_applicable[String(t.id)] })
  const arr = Array.isArray(api.rows) ? api.rows : []
  out.rows = out.rows.map(r => {
    const existingR = arr.find(x => x.key === r.key) || {}
    const existingResp = Array.isArray(existingR.responses) ? existingR.responses : []
    return { ...r, remarks: existingR.remarks ?? '', responses: r.responses.map(resp => {
      const ex = existingResp.find(x => Number(x.task_id) === Number(resp.task_id)) || {}
      return { task_id: resp.task_id, answer: typeof ex.answer === 'boolean' ? ex.answer : false, not_applicable: typeof ex.not_applicable === 'boolean' ? ex.not_applicable : out.task_not_applicable[String(resp.task_id)] }
    }) }
  })
  return out
}

const isTaskNA = (map, taskId) => Boolean(map?.[String(taskId)])
const getChecklistBlock = objOrRef => {
  if (objOrRef && typeof objOrRef === 'object' && Object.prototype.hasOwnProperty.call(objOrRef, 'value')) {
    return objOrRef.value
  }
  return objOrRef
}
const setTaskNA = (objOrRef, taskId, checked) => {
  const block = getChecklistBlock(objOrRef)
  if (!block || typeof block !== 'object') return

  if (!block.task_not_applicable || typeof block.task_not_applicable !== 'object') {
    block.task_not_applicable = {}
  }
  block.task_not_applicable[String(taskId)] = checked

  const rowsKey = Array.isArray(block.activities) ? 'activities' : 'rows'
  const rowsToUpdate = Array.isArray(block[rowsKey]) ? block[rowsKey] : []

  rowsToUpdate.forEach(row => {
    const responses = row.responses || []
    const target = responses.find(r => Number(r.task_id) === Number(taskId))
    if (!target) return
    target.not_applicable = checked
    if (checked) {
      target.answer = false
    }
  })
}
const summaryResp = (summaryObj, rowKey, taskId) => summaryObj.rows.find(r => r.key === rowKey)?.responses.find(r => Number(r.task_id) === Number(taskId))
const getSummaryAnswer = (summaryObj, rowKey, taskId) => Boolean(summaryResp(summaryObj, rowKey, taskId)?.answer)
const setSummaryAnswer = (summaryObj, summaryMap, rowKey, taskId, value) => {
  if (isTaskNA(summaryMap, taskId)) return
  const target = summaryResp(summaryObj, rowKey, taskId)
  if (target) target.answer = value
}
const pushResp = (activityKey, taskId) => pushPull.value.activities.find(a => a.key === activityKey)?.responses.find(r => Number(r.task_id) === Number(taskId))
const getPushPullAnswer = (activityKey, taskId) => Boolean(pushResp(activityKey, taskId)?.answer)
const setPushPullAnswer = (activityKey, taskId, value) => {
  if (isTaskNA(pushPull.value.task_not_applicable, taskId)) return
  const target = pushResp(activityKey, taskId)
  if (!target) return
  target.answer = value
}
const getManualYesCount = taskId => manualSummary.value.rows.filter(r => !isTaskNA(manualSummary.value.task_not_applicable, taskId) && Boolean(r.responses.find(x => Number(x.task_id) === Number(taskId))?.answer)).length
const getAnswer = (row, taskId) => Boolean(row.answers.find(a => Number(a.task_id) === Number(taskId))?.answer)
const setAnswer = (rowIndex, taskId, value) => { const t = rows.value[rowIndex]?.answers.find(a => Number(a.task_id) === Number(taskId)); if (t) t.answer = value }

const flattenPushPull = () => {
  const out = []
  pushPull.value.activities.forEach(a => tasks.value.forEach(t => {
    const r = pushResp(a.key, t.id)
    const not_applicable = isTaskNA(pushPull.value.task_not_applicable, t.id) || Boolean(r?.not_applicable)
    out.push({ activity_key: a.key, task_id: t.id, answer: not_applicable ? false : Boolean(r?.answer), not_applicable })
  }))
  return out
}
const flattenSummary = summaryObj => {
  const out = []
  summaryObj.rows.forEach(row => tasks.value.forEach(t => {
    const r = row.responses.find(x => Number(x.task_id) === Number(t.id))
    const not_applicable = isTaskNA(summaryObj.task_not_applicable, t.id) || Boolean(r?.not_applicable)
    out.push({ row_key: row.key, task_id: t.id, answer: not_applicable ? false : Boolean(r?.answer), not_applicable, remarks: row.remarks ?? '' })
  }))
  return out
}

const loadPage = async () => {
  try {
    const checklist = await api.get(`/era-checklist/${assessmentId}`)
    tasks.value = checklist.data.tasks ?? []
    setCurrentAssessmentId(assessmentId)
    if (!(checklist.data.answers ?? []).length) {
      rows.value = buildDefaultRows()
      pushPull.value = buildDefaultPushPull()
      carryingSummary.value = buildDefaultSummary(carryingSummaryRowTemplate)
      manualSummary.value = buildDefaultSummary(manualSummaryRowTemplate)
      return
    }
    const forceful = await api.get(`/era-forceful-exertion/${assessmentId}`)
    rows.value = normalizeRows(forceful.data.rows)
    referenceInfo.value = forceful.data.reference_info ?? defaultReferenceInfo
    pushPull.value = normalizePushPull(forceful.data.push_pull)
    carryingSummary.value = normalizeSummary(carryingSummaryRowTemplate, forceful.data.carrying_summary)
    manualSummary.value = normalizeSummary(manualSummaryRowTemplate, forceful.data.manual_summary)
  } catch (error) {
    console.error(error.response?.data || error)
    rows.value = buildDefaultRows()
    referenceInfo.value = defaultReferenceInfo
    pushPull.value = buildDefaultPushPull()
    carryingSummary.value = buildDefaultSummary(carryingSummaryRowTemplate)
    manualSummary.value = buildDefaultSummary(manualSummaryRowTemplate)
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
    await api.post('/era-forceful-exertion', {
      assessment_id: assessmentId,
      rows: rows.value,
      push_pull: { responses: flattenPushPull() },
      carrying_summary: { responses: flattenSummary(carryingSummary.value) },
      manual_summary: { responses: flattenSummary(manualSummary.value) },
    })
    markStepCompleted(assessmentId, 3)
    router.push(`/era-repetitive-motion/${assessmentId}`)
  } catch (error) {
    console.error(error.response?.data || error)
    alert('Error saving forceful exertion checklist.')
  } finally {
    saving.value = false
  }
}
onMounted(() => {
  setCurrentAssessmentId(assessmentId)
  loadPage()
})
</script>

<template>
  <div v-if="loading" class="loading-state">Loading forceful exertion checklist...</div>
  <div v-else class="page-wrapper">
    <div class="page-hero">
      <div class="hero-left">
        <div class="hero-tag">Step 3 of 7</div>
        <h1 class="hero-title">Forceful Exertion</h1>
        <p class="hero-sub">File: `EraForcefulExertion.vue` - Manual handling forceful exertion assessment. Assessment ID: #{{ assessmentId }}</p>
      </div>
      <div class="hero-steps">
        <div v-for="s in 7" :key="s" class="step-pip" :class="{ active: s === 3 }">
          <div class="pip-dot"></div>
          <div class="pip-label">Step {{ s }}</div>
        </div>
      </div>
    </div>
    <div class="image-card">
        <img v-if="showReferenceImage" :src="referenceImage" class="reference-image" alt="Forceful Exertion reference" @error="showReferenceImage = false" />
        <div v-else class="image-fallback">Image not found: <code>/Vue/public/images/Forceful Exertion.png</code></div>
    </div>

      <div class="table-wrap">
        <table class="table">
          <thead><tr><th rowspan="2">Working height</th><th rowspan="2">Recommended weight</th><th rowspan="2">Current weight</th><th v-for="task in tasks" :key="`fhead-${task.id}`" colspan="2">{{ task.title }}</th><th rowspan="2">Remarks</th></tr><tr><template v-for="task in tasks" :key="`fsub-${task.id}`"><th>Yes</th><th>No</th></template></tr></thead>
          <tbody>
            <tr v-for="(row, rowIndex) in rows" :key="row.key">
              <td>{{ row.working_height }}</td><td><input v-model="row.recommended_weight" class="cell-input" /></td><td><input v-model="row.current_weight" class="cell-input" /></td>
              <template v-for="task in tasks" :key="`frow-${row.key}-${task.id}`">
                <td><input type="radio" :name="`f-${row.key}-${task.id}`" :checked="getAnswer(row, task.id) === true" @change="setAnswer(rowIndex, task.id, true)" /></td>
                <td><input type="radio" :name="`f-${row.key}-${task.id}`" :checked="getAnswer(row, task.id) === false" @change="setAnswer(rowIndex, task.id, false)" /></td>
              </template>
              <td><input v-model="row.remarks" class="cell-input" /></td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="table-wrap">
        <table class="table">
          <thead><tr><th colspan="2">Repetitive handling</th><th colspan="2">Twisted body posture</th></tr><tr><th>If employee repeats operations</th><th>Weight reduction</th><th>If employee twists body</th><th>Weight reduction</th></tr></thead>
          <tbody><tr v-for="i in 3" :key="`ref-${i}`"><td>{{ referenceInfo.repetitive_handling?.[i - 1]?.if_employee_repeats ?? '' }}</td><td>{{ referenceInfo.repetitive_handling?.[i - 1]?.weight_reduction ?? '' }}</td><td>{{ referenceInfo.twisted_body_posture?.[i - 1]?.twist_angle ?? '' }}</td><td>{{ referenceInfo.twisted_body_posture?.[i - 1]?.weight_reduction ?? '' }}</td></tr></tbody>
        </table>
      </div>

      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr><th rowspan="2">Conditions</th><th rowspan="2">Activity</th><th colspan="2">Recommended weight</th><th :colspan="tasks.length">Exceed (Y/N)</th></tr>
            <tr><th>Male</th><th>Female</th><th v-for="task in tasks" :key="`pp-h-${task.id}`"><div>{{ task.title }}</div><label class="na-toggle"><input type="checkbox" :checked="isTaskNA(pushPull.task_not_applicable, task.id)" @change="setTaskNA(pushPull, task.id, $event.target.checked)" /> Not Applicable</label></th></tr>
          </thead>
          <tbody>
            <tr v-for="(activity, idx) in pushPull.activities" :key="activity.key">
              <td v-if="idx === 0" :rowspan="pushPull.activities.length"><ol><li v-for="(c, ci) in pushPull.conditions" :key="`c-${ci}`">{{ c }}</li></ol></td>
              <td>{{ activity.activity }}</td><td>{{ activity.male_recommended }}</td><td>{{ activity.female_recommended }}</td>
              <td v-for="task in tasks" :key="`pp-${activity.key}-${task.id}`">
                <label><input type="radio" :name="`pp-${activity.key}-${task.id}`" :checked="getPushPullAnswer(activity.key, task.id) === true" :disabled="isTaskNA(pushPull.task_not_applicable, task.id)" @change="setPushPullAnswer(activity.key, task.id, true)" /> Yes</label>
                <label><input type="radio" :name="`pp-${activity.key}-${task.id}`" :checked="getPushPullAnswer(activity.key, task.id) === false" :disabled="isTaskNA(pushPull.task_not_applicable, task.id)" @change="setPushPullAnswer(activity.key, task.id, false)" /> No</label>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="image-card">
        <img v-if="showSeatedImage" :src="seatedPositionImage" class="reference-image" alt="Seated Position" @error="showSeatedImage = false" />
        <div v-else class="image-fallback">Image not found: <code>/Vue/public/images/Seated Position.png</code></div>
      </div>

      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr><th colspan="3">Summary of Carrying Activity</th><th :colspan="tasks.length">Exceed limit (Y/N)</th><th rowspan="2">Remarks</th></tr>
            <tr><th>Factor</th><th>Condition</th><th>Outcome</th><th v-for="task in tasks" :key="`car-h-${task.id}`"><div>{{ task.title }}</div><label class="na-toggle"><input type="checkbox" :checked="isTaskNA(carryingSummary.task_not_applicable, task.id)" @change="setTaskNA(carryingSummary, task.id, $event.target.checked)" /> Not Applicable</label></th></tr>
          </thead>
          <tbody>
            <tr v-for="row in carryingSummary.rows" :key="`car-${row.key}`">
              <td>{{ row.factor }}</td><td>{{ row.condition }}</td><td>{{ row.outcome }}</td>
              <td v-for="task in tasks" :key="`car-r-${row.key}-${task.id}`">
                <label><input type="radio" :name="`car-${row.key}-${task.id}`" :checked="getSummaryAnswer(carryingSummary, row.key, task.id) === true" :disabled="isTaskNA(carryingSummary.task_not_applicable, task.id)" @change="setSummaryAnswer(carryingSummary, carryingSummary.task_not_applicable, row.key, task.id, true)" /> Yes</label>
                <label><input type="radio" :name="`car-${row.key}-${task.id}`" :checked="getSummaryAnswer(carryingSummary, row.key, task.id) === false" :disabled="isTaskNA(carryingSummary.task_not_applicable, task.id)" @change="setSummaryAnswer(carryingSummary, carryingSummary.task_not_applicable, row.key, task.id, false)" /> No</label>
              </td>
              <td><input v-model="row.remarks" class="cell-input" /></td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr><th colspan="2">Summary of Forceful Exertion (Manual Handling)</th><th :colspan="tasks.length">Exceed limit (Y/N)</th><th rowspan="2">Remarks</th></tr>
            <tr><th>Activity</th><th>Recommended weight</th><th v-for="task in tasks" :key="`man-h-${task.id}`"><div>{{ task.title }}</div><label class="na-toggle"><input type="checkbox" :checked="isTaskNA(manualSummary.task_not_applicable, task.id)" @change="setTaskNA(manualSummary, task.id, $event.target.checked)" /> Not Applicable</label></th></tr>
          </thead>
          <tbody>
            <tr v-for="row in manualSummary.rows" :key="`man-${row.key}`">
              <td>{{ row.activity }}</td><td>{{ row.recommended_weight }}</td>
              <td v-for="task in tasks" :key="`man-r-${row.key}-${task.id}`">
                <label><input type="radio" :name="`man-${row.key}-${task.id}`" :checked="getSummaryAnswer(manualSummary, row.key, task.id) === true" :disabled="isTaskNA(manualSummary.task_not_applicable, task.id)" @change="setSummaryAnswer(manualSummary, manualSummary.task_not_applicable, row.key, task.id, true)" /> Yes</label>
                <label><input type="radio" :name="`man-${row.key}-${task.id}`" :checked="getSummaryAnswer(manualSummary, row.key, task.id) === false" :disabled="isTaskNA(manualSummary.task_not_applicable, task.id)" @change="setSummaryAnswer(manualSummary, manualSummary.task_not_applicable, row.key, task.id, false)" /> No</label>
              </td>
              <td><input v-model="row.remarks" class="cell-input" /></td>
            </tr>
            <tr><td colspan="2"><strong>Sub Total (Number of tick(s))</strong></td><td v-for="task in tasks" :key="`man-sub-${task.id}`"><strong>{{ getManualYesCount(task.id) }}</strong></td><td></td></tr>
          </tbody>
        </table>
      </div>

    <div class="action-row">
      <button class="btn-back" @click="router.push(`/era-checklist/${assessmentId}`)">Previous</button>
      <button class="btn-save" :disabled="saving" @click="save">{{ saving ? 'Saving...' : 'Save & Proceed (Step 4)' }}</button>
    </div>
  </div>
</template>

<style scoped>
.loading-state{padding:40px;text-align:center}
.page-wrapper{font-family:DM Sans,Arial,sans-serif;display:flex;flex-direction:column;gap:16px}
.page-hero{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;padding:22px 24px;background:linear-gradient(135deg,#0b1a2a 0%,#17324f 58%,#224f7a 100%);border-radius:10px}
.hero-left{display:flex;flex-direction:column;gap:6px}
.hero-tag{width:fit-content;padding:4px 10px;border-radius:999px;font-size:11px;font-weight:700;letter-spacing:.05em;color:#e8a020;background:rgba(232,160,32,.25);border:1px solid rgba(232,160,32,.55);text-transform:uppercase}
.hero-title{font-size:36px;font-weight:700;color:#f7fbff;line-height:1.2}
.hero-sub{font-size:13px;color:rgba(231,241,251,.96);max-width:780px;line-height:1.5}
.hero-steps{display:flex;gap:8px;align-items:center;padding-top:4px}
.step-pip{display:flex;flex-direction:column;align-items:center;gap:4px;opacity:.65}
.step-pip.active{opacity:1}
.pip-dot{width:10px;height:10px;border-radius:50%;background:rgba(255,255,255,.45);border:2px solid rgba(255,255,255,.25)}
.step-pip.active .pip-dot{background:#e8a020;border-color:#e8a020;box-shadow:0 0 8px #e8a020}
.pip-label{font-size:9px;color:rgba(255,255,255,.78);font-weight:700;text-transform:uppercase;white-space:nowrap}
.step-pip.active .pip-label{color:#e8a020}
.blocked-panel{background:#fff5f5;border:1px solid #f0c2c2;border-radius:6px;padding:14px}
.table-wrap,.image-card{overflow-x:auto;border:1px solid #ddd;border-radius:6px;background:#fff;padding:10px}
.table{width:100%;min-width:1100px;border-collapse:collapse}
.table th,.table td{border:1px solid #333;padding:8px;vertical-align:top}
.table th{background:#f2f2f2}
.cell-input{width:100%;box-sizing:border-box;border:1px solid #bfc5cb;border-radius:4px;padding:6px 8px}
.na-toggle{display:inline-flex;align-items:center;gap:4px;margin-top:6px}
.reference-image{width:100%;display:block}
.action-row{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
.btn-save,.btn-back{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  padding:12px 24px;
  font-size:14px;
  font-weight:700;
  font-family:'Sora','DM Sans',Arial,sans-serif;
  letter-spacing:.02em;
  border-radius:6px;
  border:1.5px solid transparent;
  cursor:pointer;
  transition:all .2s;
  line-height:1;
}
.btn-save:disabled,.btn-back:disabled{opacity:.6;cursor:not-allowed;transform:none;box-shadow:none}
.btn-save{
  color:#fff;
  background:linear-gradient(180deg,#15385a 0%,#0e2740 100%);
  border-color:#0e2740;
  box-shadow:0 4px 12px rgba(15,30,46,.28);
}
.btn-save:hover:not(:disabled){
  background:linear-gradient(180deg,#1b466f 0%,#123150 100%);
  box-shadow:0 4px 16px rgba(15,30,46,.35);
  transform:translateY(-1px);
}
.btn-back{
  background:transparent;
  color:#17324f;
  border-color:#17324f;
}
.btn-back:hover:not(:disabled){
  background:#0b1a2a;
  color:#fff;
  border-color:#0b1a2a;
}
@media (max-width:900px){.page-hero{flex-direction:column}.hero-steps{flex-wrap:wrap}}
</style>
