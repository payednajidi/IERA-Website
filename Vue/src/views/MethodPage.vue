<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '../services/api'

// ── Section navigation ──────────────────────────────────────────────────────
const activeSection = ref('method')

// ── Assessment list ─────────────────────────────────────────────────────────
const allAssessments = ref([])
const loadingList = ref(false)
const loadingIds = ref([])
const assessmentData = ref({})
const selectedOrder = ref([])
const listError = ref('')

// ── BOSS body-parts data (Section 4.0 Table 4.1) ───────────────────────────
const bossData = ref({})
const bossLoadingIds = ref([])

// ── Step 7 risk-factor data (Section 4.0 Table 4.2) ────────────────────────
const step7Data = ref({})
const step7LoadingIds = ref([])

// ── Raw step API responses (for Table 4.3+ descriptions) ───────────────────
const rawStepData = ref({})

// ── Custom (user-edited) descriptions for Table 4.3+ rows ──────────────────
// Key: `${assessmentId}_${taskId}_${rfKey}` → string[] (bullet texts)
const customDescriptions = ref({})
const loadedDescIds = ref(new Set())

// Edit state: which row is currently open for editing
const editingState = ref(null)  // { assessmentId, taskId, rfKey, draft: string }
const descSaving   = ref(false)
const descRestoring = ref(null) // `${assessmentId}_${taskId}_${rfKey}` while restoring

// ═══════════════════  TABLE 4.2 CONSTANTS  ═══════════════════════════════════

const T42_FORCEFUL_GROUPS = [
  {
    key: 'lifting_lowering',
    label: 'Lifting/Lowering',
    rowKeys: ['lifting_lowering', 'repetitive_lifting_lowering', 'twisted_posture_lifting_lowering', 'repetitive_with_twisted_posture'],
  },
  { key: 'pushing_pulling', label: 'Pushing/Pulling', rowKeys: ['pushing_pulling'] },
  { key: 'handling_seated', label: 'Handling in Seated', rowKeys: ['handling_seated_position'] },
  { key: 'carrying', label: 'Carrying', rowKeys: ['carrying'] },
  { key: 'other', label: 'Other', rowKeys: ['other_forceful_activity'] },
]

const VIBRATION_KEY_TO_TYPE = {
  hand_arm_without_ppe: 'HAV',
  hand_arm_with_ppe: 'HAV',
  whole_body_exposure: 'WBV',
  whole_body_with_complaint: 'WBV',
}

// ═══════════════════  SHARED HELPERS  ════════════════════════════════════════

const toBool = (v) => v === true || v === 1 || v === '1'

const respForTask = (responses, taskId) =>
  Array.isArray(responses)
    ? (responses.find(r => Number(r.task_id) === Number(taskId)) ?? null)
    : null

const isYes = (resp) => Boolean(resp && toBool(resp.answer) && !toBool(resp.not_applicable))

const countYes = (rows, taskId, allowedKeys = null) => {
  if (!Array.isArray(rows)) return 0
  const ks = allowedKeys ? new Set(allowedKeys) : null
  return rows.reduce((n, row) => {
    if (ks && !ks.has(String(row.key))) return n
    return isYes(respForTask(row.responses, taskId)) ? n + 1 : n
  }, 0)
}

const triggeredVibTypes = (rows, taskId) => {
  const types = new Set()
  if (!Array.isArray(rows)) return []
  rows.forEach(row => {
    if (isYes(respForTask(row.responses, taskId))) {
      const t = VIBRATION_KEY_TO_TYPE[row.key]
      if (t) types.add(t)
    }
  })
  return [...types]
}

// ═══════════════════  ASSESSMENT LIST  ═══════════════════════════════════════

const fetchList = async () => {
  loadingList.value = true
  listError.value = ''
  try {
    const res = await api.get('/era-assessments')
    allAssessments.value = Array.isArray(res.data?.assessments) ? res.data.assessments : []
  } catch {
    listError.value = 'Unable to load ERA assessment files.'
  } finally {
    loadingList.value = false
  }
}

const isSelected = (id) => selectedOrder.value.includes(Number(id))
const isLoading = (id) => loadingIds.value.includes(Number(id))

const toggleAssessment = async (id) => {
  const numId = Number(id)
  const idx = selectedOrder.value.indexOf(numId)
  if (idx !== -1) { selectedOrder.value.splice(idx, 1); return }
  selectedOrder.value.push(numId)
  if (!assessmentData.value[numId]) {
    loadingIds.value.push(numId)
    try {
      const res = await api.get(`/era-assessments/${numId}`)
      assessmentData.value = { ...assessmentData.value, [numId]: res.data }
    } finally {
      loadingIds.value = loadingIds.value.filter(i => i !== numId)
    }
  }
}

const moveUp = (idx) => {
  if (idx <= 0) return
  const arr = [...selectedOrder.value]
  ;[arr[idx - 1], arr[idx]] = [arr[idx], arr[idx - 1]]
  selectedOrder.value = arr
}

const moveDown = (idx) => {
  if (idx >= selectedOrder.value.length - 1) return
  const arr = [...selectedOrder.value]
  ;[arr[idx], arr[idx + 1]] = [arr[idx + 1], arr[idx]]
  selectedOrder.value = arr
}

const removeFromOrder = (idx) => { selectedOrder.value.splice(idx, 1) }

const getMeta = (id) => allAssessments.value.find(a => Number(a.id) === Number(id))

// ═══════════════════  SECTION 3.0 METHOD TABLES  ═════════════════════════════

const methodTables = computed(() => {
  const tables = []
  for (const id of selectedOrder.value) {
    const data = assessmentData.value[id]
    if (!data) continue
    const photoGroups = data.photo_groups || []
    for (const process of (data.processes || [])) {
      const photosByTask = {}
      for (const group of photoGroups) {
        if (group.task_id != null) {
          if (!photosByTask[group.task_id]) photosByTask[group.task_id] = []
          photosByTask[group.task_id].push(...(group.photos || []))
        }
      }
      tables.push({
        department: data.assessment?.department || '',
        processName: process.name || '(Unnamed Process)',
        tasks: (process.tasks || []).map(task => ({
          id: task.id,
          title: task.title || '(Untitled Task)',
          worker_activities: task.worker_activities || '',
          photos: photosByTask[task.id] || [],
        })),
      })
    }
  }
  return tables
})

const anyLoading = computed(() => loadingIds.value.length > 0)

// ═══════════════════  SECTION NAVIGATION  ════════════════════════════════════

const safeGet = (url) =>
  api.get(url).catch(e => {
    if (e.response?.status === 422) return { data: {} }
    return { data: {} }
  })

const goToResult = async () => {
  activeSection.value = 'result'

  // Parallel fetches per assessment (BOSS + Step 7 data)
  const ids = selectedOrder.value
  await Promise.all(ids.map(async id => {
    const numId = Number(id)

    // ── BOSS body parts ──
    if (!bossData.value[numId]) {
      if (!bossLoadingIds.value.includes(numId)) bossLoadingIds.value.push(numId)
      try {
        const res = await api.get(`/boss-questionnaires/by-assessment/${numId}`)
        bossData.value = { ...bossData.value, [numId]: res.data }
      } catch { /* silent */ } finally {
        bossLoadingIds.value = bossLoadingIds.value.filter(i => i !== numId)
      }
    }

    // ── Step 7 risk-factor data ──
    if (!step7Data.value[numId]) {
      if (!step7LoadingIds.value.includes(numId)) step7LoadingIds.value.push(numId)
      try {
        const [checklist, forceful, repetitive, vibration, environmental] = await Promise.all([
          safeGet(`/era-checklist/${numId}`),
          safeGet(`/era-forceful-exertion/${numId}`),
          safeGet(`/era-repetitive-motion/${numId}`),
          safeGet(`/era-vibration/${numId}`),
          safeGet(`/era-environmental-factors/${numId}`),
        ])
        // Store raw payloads for Table 4.3+ per-task description generation
        rawStepData.value = { ...rawStepData.value, [numId]: { checklist, forceful, repetitive, vibration, environmental } }
        step7Data.value = {
          ...step7Data.value,
          [numId]: buildTable42Data({ checklist, forceful, repetitive, vibration, environmental }),
        }
      } catch { /* silent */ } finally {
        step7LoadingIds.value = step7LoadingIds.value.filter(i => i !== numId)
      }
    }

    // ── Custom description overrides ──
    if (!loadedDescIds.value.has(numId)) {
      loadedDescIds.value = new Set([...loadedDescIds.value, numId])
      try {
        const descResp = await safeGet(`/era-result-descriptions/${numId}`)
        if (Array.isArray(descResp?.data)) {
          const patch = {}
          descResp.data.forEach(row => {
            patch[`${numId}_${row.task_id}_${row.risk_factor_key}`] = row.bullets
          })
          customDescriptions.value = { ...customDescriptions.value, ...patch }
        }
      } catch { /* silent */ }
    }
  }))
}

const goToMethod = () => { activeSection.value = 'method' }

// ═══════════════════  BUILD TABLE 4.2 DATA  ══════════════════════════════════

const buildTable42Data = (payload) => {
  const checklist    = payload.checklist?.data    ?? {}
  const forceful     = payload.forceful?.data     ?? {}
  const repetitive   = payload.repetitive?.data   ?? {}
  const vibration    = payload.vibration?.data    ?? {}
  const environmental = payload.environmental?.data ?? {}

  const templates = Array.isArray(checklist.templates) ? checklist.templates : []
  const answers   = Array.isArray(checklist.answers)   ? checklist.answers   : []
  const tasks     = Array.isArray(checklist.tasks)     ? checklist.tasks     : []

  const awkwardTemplate = templates.find(t => String(t.name || '').toUpperCase().includes('AWKWARD POSTURE'))
  const staticTemplate  = templates.find(t => String(t.name || '').toUpperCase().includes('STATIC AND SUSTAINED WORK POSTURE'))

  const awkwardItems = Array.isArray(awkwardTemplate?.items) ? awkwardTemplate.items : []
  const staticItems  = Array.isArray(staticTemplate?.items)  ? staticTemplate.items  : []

  const answerMap = new Map()
  answers.forEach(a => answerMap.set(`${Number(a.task_id)}_${Number(a.checklist_item_id)}`, toBool(a.answer)))

  const checklistScore = (taskId, items) =>
    items.reduce((n, item) => n + (answerMap.get(`${Number(taskId)}_${Number(item.id)}`) ? 1 : 0), 0)

  const forcefulManualRows  = Array.isArray(forceful.manual_summary?.rows) ? forceful.manual_summary.rows : []
  const repetitiveRows      = Array.isArray(repetitive.rows)               ? repetitive.rows              : []
  const vibrationRows       = Array.isArray(vibration.rows)                ? vibration.rows               : []
  const environmentalRows   = Array.isArray(environmental.rows)            ? environmental.rows           : []

  const summaryByTaskId = {}

  tasks.forEach(task => {
    const taskId = Number(task.id)

    // ── Awkward Posture ──
    const awkwardScore = checklistScore(taskId, awkwardItems)
    const awkwardFlag  = awkwardScore >= 6

    // ── Static & Sustained ──
    const staticScore = checklistScore(taskId, staticItems)
    const staticFlag  = staticScore >= 1

    // ── Forceful Exertion ──
    const triggeredGroups = T42_FORCEFUL_GROUPS
      .filter(g => countYes(forcefulManualRows, taskId, g.rowKeys) > 0)
      .map(g => g.label)
    const forcefulFlag = triggeredGroups.length > 0

    // ── Repetitive Motions ──
    const repetitionScore = countYes(repetitiveRows, taskId)
    const repetitionFlag  = repetitionScore >= 1

    // ── Vibration ──
    const vibTypes   = triggeredVibTypes(vibrationRows, taskId)
    const vibFlag    = vibTypes.length > 0

    // ── Environmental Factors ──
    const envParts = []
    if (countYes(environmentalRows, taskId, ['inadequate_lighting']) >= 1)              envParts.push('Lighting')
    if (countYes(environmentalRows, taskId, ['extreme_temperature']) >= 1)              envParts.push('Temperature')
    if (countYes(environmentalRows, taskId, ['inadequate_air_ventilation']) >= 1)       envParts.push('Ventilation')
    if (countYes(environmentalRows, taskId, ['noise_above_pel', 'annoying_noise_more_than_8_hours']) >= 1) envParts.push('Noise')
    const envFlag = envParts.length > 0

    summaryByTaskId[String(taskId)] = {
      awkward_posture:  { needAdvanced: awkwardFlag,    displayText: 'Recommended\n(Extreme Posture)' },
      static_sustained: { needAdvanced: staticFlag,     displayText: 'Recommended' },
      forceful_exertion:{ needAdvanced: forcefulFlag,   displayText: triggeredGroups.join('\n') },
      repetition:       { needAdvanced: repetitionFlag, displayText: 'Repetitive Motion' },
      vibration:        { needAdvanced: vibFlag,        displayText: vibTypes.join(', ') },
      environmental:    { needAdvanced: envFlag,        displayText: envParts.join(', ') },
    }
  })

  return { tasks, summaryByTaskId }
}

// ═══════════════════  SECTION 4.0 RESULT ROWS  ═══════════════════════════════

// ── Table 4.1 rows ──
const resultRows = computed(() => {
  const rows = []
  let no = 1
  for (const id of selectedOrder.value) {
    const data = assessmentData.value[id]
    if (!data) continue
    const boss        = bossData.value[id]
    const taskBP      = boss?.task_body_parts   || {}
    const fallbackBP  = boss?.selected_body_parts || []
    const noMatchIds  = new Set((boss?.no_match_task_ids || []).map(String))
    const loading     = bossLoadingIds.value.includes(Number(id))
    for (const process of (data.processes || [])) {
      const group = []
      for (const task of (process.tasks || [])) {
        const k = String(task.id)
        let bodyParts = loading ? null : !boss ? [] : noMatchIds.has(k) ? fallbackBP : (taskBP[k] || [])
        group.push({ no: no++, processName: process.name || '', taskTitle: task.title || '', taskDesc: task.description || '', bodyParts, isFirstInGroup: false, groupSize: 0 })
      }
      if (group.length > 0) { group[0].isFirstInGroup = true; group[0].groupSize = group.length; rows.push(...group) }
    }
  }
  return rows
})

// ── Table 4.2 rows ──
const table42Rows = computed(() => {
  const rows = []
  let no = 1
  for (const id of selectedOrder.value) {
    const data = assessmentData.value[id]
    if (!data) continue
    const boss        = bossData.value[id]
    const taskBP      = boss?.task_body_parts    || {}
    const fallbackBP  = boss?.selected_body_parts || []
    const noMatchIds  = new Set((boss?.no_match_task_ids || []).map(String))
    const s7          = step7Data.value[id]
    const loadingBoss = bossLoadingIds.value.includes(Number(id))
    const loadingS7   = step7LoadingIds.value.includes(Number(id))

    for (const process of (data.processes || [])) {
      const group = []
      for (const task of (process.tasks || [])) {
        const k = String(task.id)
        const bodyParts = loadingBoss ? null : !boss ? [] : noMatchIds.has(k) ? fallbackBP : (taskBP[k] || [])
        const ts = s7?.summaryByTaskId?.[k] ?? {}
        const awkward    = ts.awkward_posture   ?? { needAdvanced: false, displayText: '' }
        const statik     = ts.static_sustained  ?? { needAdvanced: false, displayText: '' }
        const forceful   = ts.forceful_exertion ?? { needAdvanced: false, displayText: '' }
        const repetition = ts.repetition        ?? { needAdvanced: false, displayText: '' }
        const vibration  = ts.vibration         ?? { needAdvanced: false, displayText: '' }
        const envFactor  = ts.environmental     ?? { needAdvanced: false, displayText: '' }

        // Build recommendation list
        const recs = []
        if (awkward.needAdvanced)    recs.push('Awkward Posture')
        if (statik.needAdvanced)     recs.push('Static & Sustained Posture')
        if (forceful.needAdvanced)   recs.push('Forceful Exertion')
        if (repetition.needAdvanced) recs.push('Repetitive Motion')
        if (vibration.needAdvanced)  recs.push(vibration.displayText || 'Vibration')
        if (envFactor.needAdvanced)  recs.push(...(envFactor.displayText ? envFactor.displayText.split(', ') : ['Environmental Factors']))

        group.push({
          no: no++,
          processName: process.name || '',
          taskTitle:   task.title        || '',
          taskDesc:    task.description  || '',
          bodyParts,
          awkward,
          statik,
          forceful,
          repetition,
          vibration,
          envFactor,
          recommendations: recs,
          loadingS7,
          isFirstInGroup: false,
          groupSize: 0,
        })
      }
      if (group.length > 0) { group[0].isFirstInGroup = true; group[0].groupSize = group.length; rows.push(...group) }
    }
  }
  return rows
})

const anyBossLoading  = computed(() => bossLoadingIds.value.length  > 0)
const anyStep7Loading = computed(() => step7LoadingIds.value.length > 0)

const formatBodyParts = (parts) => {
  if (parts === null) return '…'
  if (!parts || parts.length === 0) return '-'
  return parts.map(p => p.replace(/\b\w/g, c => c.toUpperCase())).join(', ')
}

// ═══════════════════  TABLE 4.3+ PER-TASK DETAIL TABLES  ═════════════════════

// Guard against null / "undefined" string values coming from API fields
const safeStr = v =>
  (v != null && typeof v === 'string' && v.trim() !== '' && v.trim() !== 'undefined' && v.trim() !== 'null')
    ? v.trim()
    : ''

// ── Description helpers ──────────────────────────────────────────────────────

// Returns custom bullets if saved, otherwise falls back to auto-generated descItems
const getEffectiveBullets = (assessmentId, taskId, rfKey, descItems) => {
  const key = `${assessmentId}_${taskId}_${rfKey}`
  return customDescriptions.value[key] ?? descItems.map(d => d.text)
}

const hasCustomDescription = (assessmentId, taskId, rfKey) =>
  Object.prototype.hasOwnProperty.call(customDescriptions.value, `${assessmentId}_${taskId}_${rfKey}`)

const startEdit = (assessmentId, taskId, rfKey, bullets) => {
  editingState.value = { assessmentId, taskId, rfKey, draft: bullets.join('\n') }
}

const cancelEdit = () => { editingState.value = null }

const isEditing = (assessmentId, taskId, rfKey) =>
  editingState.value?.assessmentId === assessmentId &&
  editingState.value?.taskId === taskId &&
  editingState.value?.rfKey === rfKey

const saveCustomDescription = async () => {
  if (!editingState.value) return
  const { assessmentId, taskId, rfKey, draft } = editingState.value
  const bullets = draft.split('\n').map(l => l.trim()).filter(l => l.length > 0)
  descSaving.value = true
  try {
    await api.post('/era-result-descriptions', {
      assessment_id: assessmentId,
      task_id: taskId,
      risk_factor_key: rfKey,
      bullets,
    })
    customDescriptions.value = { ...customDescriptions.value, [`${assessmentId}_${taskId}_${rfKey}`]: bullets }
    editingState.value = null
  } finally {
    descSaving.value = false
  }
}

const restoreAutoDescription = async (assessmentId, taskId, rfKey) => {
  const key = `${assessmentId}_${taskId}_${rfKey}`
  descRestoring.value = key
  try {
    await api.delete(`/era-result-descriptions/${assessmentId}/${taskId}/${rfKey}`)
    const { [key]: _removed, ...rest } = customDescriptions.value
    customDescriptions.value = rest
  } finally {
    descRestoring.value = null
  }
}

const table43Items = computed(() => {
  const items = []
  let tableIdx = 3
  let subSectionIdx = 1

  for (const id of selectedOrder.value) {
    const data = assessmentData.value[id]
    if (!data) continue
    const s7  = step7Data.value[id]
    const raw = rawStepData.value[id]
    if (!s7 || !raw) continue

    const boss       = bossData.value[id]
    const taskBP     = boss?.task_body_parts    || {}
    const fallbackBP = boss?.selected_body_parts || []
    const noMatchIds = new Set((boss?.no_match_task_ids || []).map(String))

    // Unpack raw step data
    const clData       = raw.checklist?.data    ?? {}
    const forcefulData = raw.forceful?.data     ?? {}
    const repData      = raw.repetitive?.data   ?? {}
    const vibData      = raw.vibration?.data    ?? {}
    const envData      = raw.environmental?.data ?? {}

    const clTemplates = Array.isArray(clData.templates) ? clData.templates : []
    const clAnswers   = Array.isArray(clData.answers)   ? clData.answers   : []

    const awkwardTpl = clTemplates.find(t => String(t.name || '').toUpperCase().includes('AWKWARD POSTURE'))
    const staticTpl  = clTemplates.find(t => String(t.name || '').toUpperCase().includes('STATIC AND SUSTAINED'))
    const awkwardItems = Array.isArray(awkwardTpl?.items) ? awkwardTpl.items : []
    const staticItems  = Array.isArray(staticTpl?.items)  ? staticTpl.items  : []

    // Build Set of 'taskId_itemId' for answered-Yes checklist items
    const yesSet = new Set()
    clAnswers.forEach(a => { if (toBool(a.answer)) yesSet.add(`${Number(a.task_id)}_${Number(a.checklist_item_id)}`) })

    // Forceful Exertion sub-tables (all accessed from raw.forceful.data)
    const forcefulLiftingRows    = Array.isArray(forcefulData.rows)                     ? forcefulData.rows                     : []
    const forcefulPushPullActs   = Array.isArray(forcefulData.push_pull?.activities)    ? forcefulData.push_pull.activities    : []
    const forcefulCarryingRows   = Array.isArray(forcefulData.carrying_summary?.rows)   ? forcefulData.carrying_summary.rows   : []
    const forcefulManualRows     = Array.isArray(forcefulData.manual_summary?.rows)     ? forcefulData.manual_summary.rows     : []

    const repRows  = Array.isArray(repData.rows)  ? repData.rows  : []
    const vibRows  = Array.isArray(vibData.rows)  ? vibData.rows  : []
    const envRows  = Array.isArray(envData.rows)  ? envData.rows  : []

    for (const process of (data.processes || [])) {
      for (const task of (process.tasks || [])) {
        const k      = String(task.id)
        const taskId = Number(task.id)
        const ts     = s7.summaryByTaskId?.[k] ?? {}

        const anyRed = ts.awkward_posture?.needAdvanced
          || ts.static_sustained?.needAdvanced
          || ts.forceful_exertion?.needAdvanced
          || ts.repetition?.needAdvanced
          || ts.vibration?.needAdvanced
          || ts.environmental?.needAdvanced
        if (!anyRed) continue

        // Body parts from BOSS for this task
        const bodyParts = noMatchIds.has(k) ? fallbackBP : (taskBP[k]?.length ? taskBP[k] : fallbackBP)

        // Photos for this task — guard all string fields
        const photoGroups = Array.isArray(data.photo_groups) ? data.photo_groups : []
        const taskPhotos = photoGroups
          .filter(g => Number(g.task_id) === taskId)
          .flatMap(g => (g.photos || []).map(p => ({
            ...p,
            groupTitle: safeStr(g.title),
            groupDesc:  safeStr(g.description),
          })))

        const rows = []

        // ── Awkward Posture ──────────────────────────────────────────────────
        if (ts.awkward_posture?.needAdvanced) {
          const triggered = awkwardItems.filter(item => yesSet.has(`${taskId}_${Number(item.id)}`))
          rows.push({
            rfKey: 'awkward_posture',
            factor: 'Awkward Posture',
            descItems: triggered.map(item => ({ text: item.description, part: item.body_part })),
            bodyParts,
            showBP: true,
          })
        }

        // ── Static & Sustained Work Posture ─────────────────────────────────
        if (ts.static_sustained?.needAdvanced) {
          const triggered = staticItems.filter(item => yesSet.has(`${taskId}_${Number(item.id)}`))
          rows.push({
            rfKey: 'static_sustained',
            factor: 'Static & Sustained Work Posture',
            descItems: triggered.map(item => ({ text: item.description, part: item.body_part })),
            bodyParts,
            showBP: true,
          })
        }

        // ── Forceful Exertion ────────────────────────────────────────────────
        // Each manual_summary row that is Yes maps to a sub-table; show the
        // sub-table's Yes conditions as bullets (fallback to the activity label).
        if (ts.forceful_exertion?.needAdvanced) {
          const descItems  = []
          const remarks    = []
          const addedTexts = new Set()

          const LIFTING_KEYS = new Set([
            'lifting_lowering', 'repetitive_lifting_lowering',
            'twisted_posture_lifting_lowering', 'repetitive_with_twisted_posture',
          ])
          let liftingHandled = false

          forcefulManualRows.forEach(row => {
            if (!isYes(respForTask(row.responses, taskId))) return
            const rowKey     = safeStr(row.key)
            const fallbackTxt = safeStr(String(row.activity || '').replace(/\s*;\s*or\s*$/i, ''))
            if (row.remarks) remarks.push(row.remarks)

            if (LIFTING_KEYS.has(rowKey)) {
              // Deduplicate: all lifting-type rows share the same height-zone sub-table
              if (!liftingHandled) {
                liftingHandled = true
                const bullets = forcefulLiftingRows
                  .filter(lRow => {
                    const ans = Array.isArray(lRow.answers)
                      ? lRow.answers.find(a => Number(a.task_id) === taskId)
                      : null
                    return ans && toBool(ans.answer) && !toBool(ans.not_applicable)
                  })
                  .map(lRow => safeStr(lRow.working_height))
                  .filter(t => t && !addedTexts.has(t))

                if (bullets.length > 0) {
                  bullets.forEach(t => { descItems.push({ text: t, part: '' }); addedTexts.add(t) })
                } else if (fallbackTxt && !addedTexts.has(fallbackTxt)) {
                  descItems.push({ text: fallbackTxt, part: '' }); addedTexts.add(fallbackTxt)
                }
              }

            } else if (rowKey === 'pushing_pulling') {
              const bullets = forcefulPushPullActs
                .filter(act => isYes(respForTask(act.responses, taskId)))
                .map(act => safeStr(act.activity))
                .filter(t => t && !addedTexts.has(t))

              if (bullets.length > 0) {
                bullets.forEach(t => { descItems.push({ text: t, part: '' }); addedTexts.add(t) })
              } else if (fallbackTxt && !addedTexts.has(fallbackTxt)) {
                descItems.push({ text: fallbackTxt, part: '' }); addedTexts.add(fallbackTxt)
              }

            } else if (rowKey === 'carrying') {
              // Use the condition text from each Yes row in carrying_summary
              forcefulCarryingRows
                .filter(cRow => isYes(respForTask(cRow.responses, taskId)) && cRow.remarks)
                .forEach(cRow => remarks.push(cRow.remarks))

              const bullets = forcefulCarryingRows
                .filter(cRow => isYes(respForTask(cRow.responses, taskId)))
                .map(cRow => safeStr(cRow.condition))
                .filter(t => t && !addedTexts.has(t))

              if (bullets.length > 0) {
                bullets.forEach(t => { descItems.push({ text: t, part: '' }); addedTexts.add(t) })
              } else if (fallbackTxt && !addedTexts.has(fallbackTxt)) {
                descItems.push({ text: fallbackTxt, part: '' }); addedTexts.add(fallbackTxt)
              }

            } else {
              // handling_seated_position, other_forceful_activity — no sub-table; use label
              if (fallbackTxt && !addedTexts.has(fallbackTxt)) {
                descItems.push({ text: fallbackTxt, part: '' }); addedTexts.add(fallbackTxt)
              }
            }
          })

          rows.push({
            rfKey: 'forceful_exertion',
            factor: 'Forceful Exertion',
            descItems,
            bodyParts,
            showBP: true,
            remarks: remarks.filter(Boolean).join('; '),
          })
        }

        // ── Repetitive Motions ───────────────────────────────────────────────
        if (ts.repetition?.needAdvanced) {
          const triggered = repRows.filter(row => isYes(respForTask(row.responses, taskId)))
          rows.push({
            rfKey: 'repetitive_motions',
            factor: 'Repetitive Motions',
            descItems: triggered.map(row => ({ text: row.physical_risk_factor, part: '' })),
            bodyParts,
            showBP: true,
            remarks: triggered.map(r => r.remarks).filter(Boolean).join('; '),
          })
        }

        // ── Vibration ────────────────────────────────────────────────────────
        if (ts.vibration?.needAdvanced) {
          const triggered = vibRows.filter(row => isYes(respForTask(row.responses, taskId)))
          rows.push({
            rfKey: 'vibration',
            factor: 'Vibration',
            descItems: triggered.map(row => ({ text: row.physical_risk_factor, part: '' })),
            bodyParts,
            showBP: true,
            remarks: triggered.map(r => r.remarks).filter(Boolean).join('; '),
          })
        }

        // ── Environmental Factors ────────────────────────────────────────────
        if (ts.environmental?.needAdvanced) {
          const triggered = envRows.filter(row => isYes(respForTask(row.responses, taskId)))
          rows.push({
            rfKey: 'environmental',
            factor: `Environmental Factor – ${ts.environmental.displayText}`,
            descItems: triggered.map(row => ({ text: row.physical_risk_factor, part: '' })),
            bodyParts: [],
            showBP: false,
            remarks: triggered.map(r => r.remarks).filter(Boolean).join('; '),
          })
        }

        // ── Other Concern (always appended) ─────────────────────────────────
        rows.push({
          rfKey: 'other_concern',
          factor: 'Other Concern',
          descItems: [],
          bodyParts: [],
          showBP: false,
        })

        if (rows.length === 0) continue

        items.push({
          header: `4.2.${subSectionIdx} ${process.name || ''} – ${task.title || ''}`,
          title: `Table 4.${tableIdx}: Summary of ergonomics risk factors for ${(task.title || '').toLowerCase()}`,
          assessmentId: Number(id),
          taskId,
          taskTitle: task.title || '',
          photos: taskPhotos,
          rows,
        })
        tableIdx++
        subSectionIdx++
      }
    }
  }
  return items
})

onMounted(fetchList)
</script>

<template>
  <!-- ════════════════════════  3.0  M E T H O D  ════════════════════════ -->
  <div v-if="activeSection === 'method'" class="method-root">

    <!-- Left: Assessment Selection Panel -->
    <aside class="selection-panel">
      <div class="sp-header">
        <div class="sp-title">ERA Assessment Files</div>
        <div class="sp-sub">Check records to include in the Method section</div>
      </div>
      <div class="sp-list-wrap">
        <div v-if="loadingList" class="sp-state"><div class="spinner"></div><span>Loading...</span></div>
        <div v-else-if="listError" class="sp-state sp-error">{{ listError }}</div>
        <div v-else-if="allAssessments.length === 0" class="sp-state">No ERA assessment records found.</div>
        <div v-else class="assessment-list">
          <label
            v-for="item in allAssessments" :key="item.id"
            class="assessment-item"
            :class="{ 'item-selected': isSelected(item.id), 'item-loading': isLoading(item.id) }"
          >
            <input type="checkbox" class="item-cb" :checked="isSelected(item.id)" :disabled="isLoading(item.id)" @change="toggleAssessment(item.id)" />
            <div class="item-info">
              <div class="item-name">{{ item.assessor_name || 'Unknown' }}</div>
              <div class="item-meta"><span class="item-id">#{{ item.id }}</span><span class="item-date">{{ item.assessment_date }}</span></div>
              <div class="item-dept">{{ item.department }}</div>
            </div>
            <div v-if="isLoading(item.id)" class="spinner sm"></div>
          </label>
        </div>
      </div>

      <div v-if="selectedOrder.length > 0" class="order-panel">
        <div class="order-header">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/>
            <line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>
          </svg>
          Display Order
        </div>
        <div class="order-list">
          <div v-for="(id, idx) in selectedOrder" :key="id" class="order-item">
            <span class="order-seq">{{ idx + 1 }}</span>
            <div class="order-info">
              <div class="order-name">{{ getMeta(id)?.assessor_name || `Assessment #${id}` }}</div>
              <div class="order-dept">{{ getMeta(id)?.department }}</div>
            </div>
            <div class="order-btns">
              <button type="button" class="ord-btn" :disabled="idx === 0" @click="moveUp(idx)" title="Move up">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="18 15 12 9 6 15"/></svg>
              </button>
              <button type="button" class="ord-btn" :disabled="idx === selectedOrder.length - 1" @click="moveDown(idx)" title="Move down">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
              </button>
              <button type="button" class="ord-btn ord-remove" @click="removeFromOrder(idx)" title="Remove">
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
              </button>
            </div>
          </div>
        </div>
      </div>
    </aside>

    <!-- Right: Method Preview -->
    <main class="method-content">
      <div class="mc-topbar">
        <div class="mc-heading">
          <span class="mc-section-label">Section</span>
          <h2 class="mc-title">3.0&nbsp;&nbsp;Method</h2>
        </div>
        <div v-if="anyLoading" class="mc-loading-pill"><div class="spinner sm"></div>Loading data…</div>
      </div>

      <div v-if="selectedOrder.length === 0" class="mc-empty">
        <svg width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
        <p class="mc-empty-title">No assessments selected</p>
        <p class="mc-empty-sub">Select ERA Assessment files on the left to automatically generate the Method section tables.</p>
      </div>

      <div v-else class="tables-area">
        <div v-for="(table, tIdx) in methodTables" :key="tIdx" class="method-block">
          <div class="table-caption">Table 3.{{ tIdx + 1 }}: Task Description – {{ table.department }} – {{ table.processName }}</div>
          <table class="method-table">
            <thead>
              <tr>
                <th class="th-task">Job Task</th>
                <th class="th-activities">Worker Activities</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(task, rowIdx) in table.tasks" :key="rowIdx" class="task-row">
                <td class="td-task">
                  <div class="task-title">{{ task.title }}</div>
                  <div v-if="task.photos.length > 0" class="task-photos">
                    <div v-for="photo in task.photos" :key="photo.id" class="photo-wrapper">
                      <img :src="photo.url" :alt="task.title" class="task-photo" />
                    </div>
                  </div>
                </td>
                <td class="td-activities"><div class="activities-text">{{ task.worker_activities }}</div></td>
              </tr>
              <tr v-if="table.tasks.length === 0">
                <td colspan="2" class="no-tasks">No tasks recorded for this process.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-for="id in selectedOrder.filter(id => !assessmentData[id] && isLoading(id))" :key="`loading-${id}`" class="method-block method-block-loading">
          <div class="table-caption-placeholder">Loading Table 3.? — {{ getMeta(id)?.assessor_name || `Assessment #${id}` }}…</div>
          <div class="loading-bar"></div>
        </div>
      </div>

      <div class="mc-footer">
        <button type="button" class="section-nav-btn section-nav-next" :disabled="selectedOrder.length === 0" @click="goToResult">
          Next
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
        </button>
      </div>
    </main>
  </div>

  <!-- ════════════════════════  4.0  R E S U L T  ════════════════════════ -->
  <div v-else class="result-root">

    <div class="mc-topbar">
      <div class="mc-heading">
        <span class="mc-section-label">Section</span>
        <h2 class="mc-title">4.0&nbsp;&nbsp;Result</h2>
      </div>
      <div v-if="anyBossLoading || anyStep7Loading" class="mc-loading-pill">
        <div class="spinner sm"></div>Loading data…
      </div>
    </div>

    <div class="rc-body">

      <!-- ── Table 4.1 ── -->
      <div class="result-block">
        <div class="table-caption">Table 4.1: Complaint on discomfort/pain by workers</div>
        <div v-if="resultRows.length === 0" class="rc-empty">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
          <p>No assessment data to display.</p>
        </div>
        <table v-else class="result-table">
          <thead>
            <tr>
              <th class="rt-no">No.</th>
              <th class="rt-process">Process/Location</th>
              <th class="rt-jobtask">Job Task and Task Description</th>
              <th class="rt-bodyparts">Body Parts (Complaint)</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, idx) in resultRows" :key="idx" class="rt-row">
              <td class="rt-td-no">{{ row.no }}</td>
              <td v-if="row.isFirstInGroup" :rowspan="row.groupSize" class="rt-td-process">{{ row.processName }}</td>
              <td class="rt-td-jobtask"><strong>{{ row.taskTitle }}</strong><span v-if="row.taskDesc"> – {{ row.taskDesc }}</span></td>
              <td class="rt-td-bodyparts">{{ formatBodyParts(row.bodyParts) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- ── Table 4.2 ── -->
      <div class="result-block">
        <div class="table-caption">Table 4.2: Summary Result of Ergonomics Risk Factors Assessment for All Tasks Assessed</div>

        <div v-if="table42Rows.length === 0" class="rc-empty">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
          <p>No assessment data to display. Ensure Steps 2–6 have been completed for selected assessments.</p>
        </div>

        <div v-else class="t42-scroll">
          <table class="t42-table">
            <thead>
              <tr>
                <th rowspan="2" class="t42h-no">No.</th>
                <th rowspan="2" class="t42h-dept">Department/<br>Location</th>
                <th rowspan="2" class="t42h-desc">Description of the Task</th>
                <th colspan="6" class="t42h-erf">Ergonomics Risk Factors</th>
                <th rowspan="2" class="t42h-pain">Pain or Discomfort Found in Musculoskeletal Assessment</th>
                <th rowspan="2" class="t42h-rec">Recommendation for Advanced ERA and Remarks</th>
              </tr>
              <tr>
                <th class="t42h-sub">Awkward Posture</th>
                <th class="t42h-sub">Static and Sustained Posture</th>
                <th class="t42h-sub">Forceful Exertion</th>
                <th class="t42h-sub">Repetitive Motions</th>
                <th class="t42h-sub">Vibration</th>
                <th class="t42h-sub">Environmental Factor<br><span class="t42-sub-note">(Lighting, Temperature, Ventilation, Noise)</span></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, idx) in table42Rows" :key="idx" class="t42-row">
                <td class="t42-td-no">{{ row.no }}</td>
                <td v-if="row.isFirstInGroup" :rowspan="row.groupSize" class="t42-td-dept">{{ row.processName }}</td>
                <td class="t42-td-desc">
                  <strong>{{ row.taskTitle }}</strong><span v-if="row.taskDesc"> – {{ row.taskDesc }}</span>
                </td>

                <!-- Risk Factor cells: green = safe, red = flagged -->
                <td :class="row.loadingS7 ? 'rf-loading' : row.awkward.needAdvanced ? 'rf-red' : 'rf-green'">
                  <span v-if="row.loadingS7" class="rf-spinner"></span>
                  <span v-else-if="row.awkward.needAdvanced" class="rf-text">{{ row.awkward.displayText }}</span>
                </td>
                <td :class="row.loadingS7 ? 'rf-loading' : row.statik.needAdvanced ? 'rf-red' : 'rf-green'">
                  <span v-if="row.loadingS7" class="rf-spinner"></span>
                  <span v-else-if="row.statik.needAdvanced" class="rf-text">{{ row.statik.displayText }}</span>
                </td>
                <td :class="row.loadingS7 ? 'rf-loading' : row.forceful.needAdvanced ? 'rf-red' : 'rf-green'">
                  <span v-if="row.loadingS7" class="rf-spinner"></span>
                  <span v-else-if="row.forceful.needAdvanced" class="rf-text">{{ row.forceful.displayText }}</span>
                </td>
                <td :class="row.loadingS7 ? 'rf-loading' : row.repetition.needAdvanced ? 'rf-red' : 'rf-green'">
                  <span v-if="row.loadingS7" class="rf-spinner"></span>
                  <span v-else-if="row.repetition.needAdvanced" class="rf-text">{{ row.repetition.displayText }}</span>
                </td>
                <td :class="row.loadingS7 ? 'rf-loading' : row.vibration.needAdvanced ? 'rf-red' : 'rf-green'">
                  <span v-if="row.loadingS7" class="rf-spinner"></span>
                  <span v-else-if="row.vibration.needAdvanced" class="rf-text">{{ row.vibration.displayText }}</span>
                </td>
                <td :class="row.loadingS7 ? 'rf-loading' : row.envFactor.needAdvanced ? 'rf-red' : 'rf-green'">
                  <span v-if="row.loadingS7" class="rf-spinner"></span>
                  <span v-else-if="row.envFactor.needAdvanced" class="rf-text">{{ row.envFactor.displayText }}</span>
                </td>

                <!-- Pain & Recommendations -->
                <td class="t42-td-pain">{{ formatBodyParts(row.bodyParts) }}</td>
                <td class="t42-td-rec">{{ row.recommendations.join(', ') || '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ── Tables 4.3+ (Per-task risk factor detail) ── -->
      <template v-if="!anyStep7Loading && table43Items.length > 0">
        <div v-for="(tbl, tIdx) in table43Items" :key="`t43-${tIdx}`" class="result-block t43-block">
          <div class="t43-task-header">{{ tbl.header }}</div>
          <div class="table-caption">{{ tbl.title }}</div>
          <table class="t43-table">
            <thead>
              <tr>
                <th class="t43h-factor">Risk Factors</th>
                <th class="t43h-desc">Description</th>
                <th class="t43h-photo">Photo / Remarks</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, rIdx) in tbl.rows" :key="`${tIdx}-${rIdx}`" class="t43-row">
                <td class="t43-td-factor">{{ row.factor }}</td>

                <!-- Description cell: view mode or edit mode -->
                <td class="t43-td-desc">
                  <!-- ── Edit mode ── -->
                  <template v-if="isEditing(tbl.assessmentId, tbl.taskId, row.rfKey)">
                    <div class="t43-edit-hint">Enter each bullet point on a new line</div>
                    <textarea
                      v-model="editingState.draft"
                      class="t43-desc-textarea"
                      rows="6"
                      placeholder="One bullet per line…"
                    ></textarea>
                    <div class="t43-edit-actions">
                      <button class="t43-btn-save" :disabled="descSaving" @click="saveCustomDescription">
                        <svg v-if="!descSaving" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        <span class="t43-btn-spinner" v-if="descSaving"></span>
                        {{ descSaving ? 'Saving…' : 'Save' }}
                      </button>
                      <button class="t43-btn-cancel" @click="cancelEdit">Cancel</button>
                    </div>
                  </template>

                  <!-- ── View mode ── -->
                  <template v-else>
                    <div class="t43-desc-toolbar">
                      <button
                        class="t43-btn-edit"
                        @click="startEdit(tbl.assessmentId, tbl.taskId, row.rfKey, getEffectiveBullets(tbl.assessmentId, tbl.taskId, row.rfKey, row.descItems))"
                        title="Edit description"
                      >
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        Edit
                      </button>
                      <button
                        v-if="hasCustomDescription(tbl.assessmentId, tbl.taskId, row.rfKey)"
                        class="t43-btn-restore"
                        :disabled="descRestoring === `${tbl.assessmentId}_${tbl.taskId}_${row.rfKey}`"
                        @click="restoreAutoDescription(tbl.assessmentId, tbl.taskId, row.rfKey)"
                        title="Revert to auto-generated description"
                      >
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.17"/></svg>
                        {{ descRestoring === `${tbl.assessmentId}_${tbl.taskId}_${row.rfKey}` ? 'Restoring…' : 'Restore Auto' }}
                      </button>
                      <span v-if="hasCustomDescription(tbl.assessmentId, tbl.taskId, row.rfKey)" class="t43-custom-badge">Custom</span>
                    </div>
                    <ul
                      v-if="getEffectiveBullets(tbl.assessmentId, tbl.taskId, row.rfKey, row.descItems).length > 0"
                      class="t43-desc-list"
                    >
                      <li
                        v-for="(bullet, bIdx) in getEffectiveBullets(tbl.assessmentId, tbl.taskId, row.rfKey, row.descItems)"
                        :key="bIdx"
                      >{{ bullet }}</li>
                    </ul>
                    <span v-else class="t43-empty-desc">—</span>
                    <div v-if="row.remarks" class="t43-remarks-note"><strong>Remarks:</strong> {{ row.remarks }}</div>
                  </template>
                </td>

                <!-- Photo/Remarks cell: rowspan across all factor rows for this task -->
                <td v-if="rIdx === 0" :rowspan="tbl.rows.length" class="t43-td-photo">
                  <div v-if="tbl.photos.length > 0" class="t43-photos">
                    <div v-for="(photo, pIdx) in tbl.photos" :key="pIdx" class="t43-photo-item">
                      <img :src="photo.url" class="t43-photo-img" :alt="tbl.taskTitle" />
                      <div v-if="photo.groupTitle || photo.groupDesc" class="t43-photo-meta">
                        <div v-if="photo.groupTitle" class="t43-photo-label">{{ photo.groupTitle }}</div>
                        <div v-if="photo.groupDesc"  class="t43-photo-caption">{{ photo.groupDesc }}</div>
                      </div>
                    </div>
                  </div>
                  <div v-else class="t43-no-photo">No photo uploaded</div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>

    </div><!-- /rc-body -->

    <div class="rc-footer">
      <button type="button" class="section-nav-btn section-nav-prev" @click="goToMethod">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
        Previous
      </button>
      <button type="button" class="section-nav-btn section-nav-save" disabled title="Save functionality will be added in a future update">
        Save
      </button>
    </div>
  </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&display=swap');
* { box-sizing: border-box; }

/* ── Shared: topbar ── */
.mc-topbar { display:flex; align-items:center; justify-content:space-between; padding:12px 28px; background:#fff; border-bottom:1px solid #eaecf0; flex-shrink:0; }
.mc-heading { display:flex; align-items:baseline; gap:10px; }
.mc-section-label { font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; color:#9aa3ae; background:#f0f2f5; border:1px solid #dde1e8; border-radius:4px; padding:2px 7px; }
.mc-title { font-size:18px; font-weight:700; color:#1e2a36; margin:0; font-family:Arial,sans-serif; }
.mc-loading-pill { display:flex; align-items:center; gap:7px; font-size:12px; color:#7a8694; background:#f4f6f9; border:1px solid #dde1e8; border-radius:99px; padding:5px 12px; }

/* ── Shared: table caption ── */
.table-caption { font-size:14px; font-weight:700; color:#1e2a36; text-align:center; padding:14px 24px; background:#f8fafc; border-bottom:2px solid #dde1e8; letter-spacing:0.01em; font-family:Arial,sans-serif; }

/* ── Shared: spinners ── */
.spinner { width:26px; height:26px; border:3px solid #e0e4ea; border-top-color:#3b7dd8; border-radius:50%; animation:spin .7s linear infinite; flex-shrink:0; }
.spinner.sm { width:14px; height:14px; border-width:2px; }
@keyframes spin { to { transform:rotate(360deg); } }

/* ── Section-navigation buttons ── */
.section-nav-btn { display:inline-flex; align-items:center; gap:7px; padding:9px 22px; font-size:13px; font-weight:600; font-family:Arial,sans-serif; border-radius:6px; cursor:pointer; border:none; transition:background .15s, opacity .15s; }
.section-nav-next { background:#2a7a52; color:#fff; }
.section-nav-next:hover:not(:disabled) { background:#226642; }
.section-nav-next:disabled { opacity:.42; cursor:not-allowed; }
.section-nav-prev { background:#fff; color:#2f3e4d; border:1px solid #d0d5dd; }
.section-nav-prev:hover { background:#f4f6f8; }
.section-nav-save { background:#3b7dd8; color:#fff; }
.section-nav-save:hover:not(:disabled) { background:#3069c5; }
.section-nav-save:disabled { opacity:.5; cursor:not-allowed; }

/* ════════  3.0 METHOD LAYOUT  ════════ */
.method-root { font-family:Arial,sans-serif; display:flex; height:100%; background:#f0f2f5; overflow:hidden; border-top:1px solid #d0d5dd; }

.selection-panel { width:300px; flex-shrink:0; display:flex; flex-direction:column; background:#fff; border-right:1px solid #dde1e8; overflow:hidden; }
.sp-header { padding:16px 18px 12px; border-bottom:1px solid #eaecf0; background:#f8fafc; flex-shrink:0; }
.sp-title { font-size:13px; font-weight:700; color:#1e2a36; }
.sp-sub { font-size:11px; color:#9aa3ae; margin-top:2px; line-height:1.4; }
.sp-list-wrap { flex:1; overflow-y:auto; min-height:0; }
.sp-state { display:flex; flex-direction:column; align-items:center; justify-content:center; gap:10px; padding:32px 16px; color:#9aa3ae; font-size:12.5px; text-align:center; }
.sp-error { color:#c0392b; }
.assessment-list { display:flex; flex-direction:column; padding:6px 0; }
.assessment-item { display:flex; align-items:flex-start; gap:10px; padding:9px 14px; cursor:pointer; border-left:3px solid transparent; transition:background .12s, border-color .12s; }
.assessment-item:hover { background:#f4f6f9; }
.assessment-item.item-selected { background:#eff6ff; border-left-color:#3b7dd8; }
.assessment-item.item-loading { opacity:.7; cursor:wait; }
.item-cb { margin-top:3px; accent-color:#3b7dd8; width:14px; height:14px; flex-shrink:0; cursor:pointer; }
.item-info { flex:1; min-width:0; }
.item-name { font-size:12.5px; font-weight:600; color:#1e2a36; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.item-meta { display:flex; gap:6px; font-size:11px; color:#8a95a4; margin-top:1px; }
.item-id { color:#b0bac5; font-weight:600; }
.item-dept { font-size:11px; color:#a0abb6; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

.order-panel { border-top:1px solid #dde1e8; background:#f8fafc; flex-shrink:0; max-height:230px; overflow-y:auto; }
.order-header { display:flex; align-items:center; gap:6px; padding:9px 14px 6px; font-size:11px; font-weight:700; color:#7a8694; text-transform:uppercase; letter-spacing:0.06em; }
.order-list { display:flex; flex-direction:column; gap:2px; padding:0 8px 8px; }
.order-item { display:flex; align-items:center; gap:8px; padding:7px 8px; background:#fff; border:1px solid #e4e8ee; border-radius:6px; }
.order-seq { width:20px; height:20px; border-radius:50%; background:#3b7dd8; color:#fff; font-size:11px; font-weight:700; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.order-info { flex:1; min-width:0; }
.order-name { font-size:12px; font-weight:600; color:#1e2a36; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.order-dept { font-size:10.5px; color:#9aa3ae; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.order-btns { display:flex; gap:2px; flex-shrink:0; }
.ord-btn { width:22px; height:22px; border:1px solid #d0d5dd; border-radius:4px; background:#fff; cursor:pointer; display:flex; align-items:center; justify-content:center; color:#6e7d8e; padding:0; transition:all .12s; }
.ord-btn:hover:not(:disabled) { background:#f0f2f5; border-color:#b0bac5; }
.ord-btn:disabled { opacity:.35; cursor:not-allowed; }
.ord-remove:hover:not(:disabled) { background:#fff0f0; border-color:#e07070; color:#c0392b; }

.method-content { flex:1; min-height:0; min-width:0; display:flex; flex-direction:column; overflow:hidden; }
.mc-empty { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:12px; color:#9aa3ae; padding:40px; }
.mc-empty-title { font-size:15px; font-weight:600; color:#7a8694; margin:0; }
.mc-empty-sub { font-size:13px; color:#a0abb6; text-align:center; max-width:360px; margin:0; line-height:1.5; }

.tables-area { flex:1; min-height:0; overflow-y:auto; padding:20px 28px; display:flex; flex-direction:column; gap:24px; }
.method-block { flex-shrink:0; background:#fff; border:1px solid #d0d5dd; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.07); }
.method-table { width:100%; border-collapse:collapse; font-size:13.5px; color:#2f3e4d; table-layout:fixed; }
.th-task,.th-activities { padding:12px 20px; text-align:left; font-weight:700; font-size:12px; text-transform:uppercase; letter-spacing:0.06em; color:#fff; background:#2f3e4d; }
.th-task { width:40%; } .th-activities { width:60%; }
.task-row td { border-bottom:1px solid #e4e8ee; }
.task-row:last-child td { border-bottom:none; }
.td-task { padding:18px 20px; vertical-align:top; border-right:1px solid #e4e8ee; overflow:hidden; word-break:break-word; }
.td-activities { padding:18px 20px; vertical-align:top; overflow:hidden; word-break:break-word; }
.task-row:nth-child(even) .td-task,.task-row:nth-child(even) .td-activities { background:#f8fafc; }
.task-title { font-weight:700; font-size:14px; color:#1e2a36; margin-bottom:12px; line-height:1.45; }
.task-photos { display:flex; flex-direction:column; gap:10px; }
.photo-wrapper { display:block; border:1px solid #dde1e8; border-radius:6px; overflow:hidden; background:#f0f2f5; width:100%; }
.task-photo { display:block; width:100%; height:auto; max-height:300px; object-fit:contain; background:#f0f2f5; }
.activities-text { white-space:pre-line; font-size:13.5px; color:#2f3e4d; line-height:1.75; margin:0; }
.no-tasks { padding:22px 20px; color:#9aa3ae; font-size:13px; font-style:italic; text-align:center; }
.method-block-loading { border-style:dashed; border-color:#c8cdd5; }
.table-caption-placeholder { padding:12px 20px; font-size:13px; font-style:italic; color:#9aa3ae; text-align:center; background:#f8fafc; }
.loading-bar { height:80px; background:linear-gradient(90deg,#f0f2f5 25%,#e8ebef 50%,#f0f2f5 75%); background-size:400% 100%; animation:shimmer 1.4s infinite; }
@keyframes shimmer { 0%{background-position:100% 0} 100%{background-position:-100% 0} }

.mc-footer { flex-shrink:0; display:flex; justify-content:flex-end; padding:12px 28px; background:#fff; border-top:1px solid #eaecf0; }

/* ════════  4.0 RESULT LAYOUT  ════════ */
.result-root { font-family:Arial,sans-serif; display:flex; flex-direction:column; height:100%; background:#f0f2f5; overflow:hidden; border-top:1px solid #d0d5dd; }
.rc-body { flex:1; min-height:0; overflow-y:auto; padding:24px 32px; display:flex; flex-direction:column; gap:24px; }
.result-block { background:#fff; border:1px solid #d0d5dd; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.07); flex-shrink:0; }

/* Table 4.1 */
.result-table { width:100%; border-collapse:collapse; font-size:13px; color:#2f3e4d; font-family:Arial,sans-serif; }
.result-table th,.result-table td { border:1px solid #d0d5dd; padding:10px 14px; vertical-align:middle; }
.result-table th { border-color:#b0b8c4; }
.result-table td { border-color:#dde1e8; }
.result-table thead th { background:#2f3e4d; color:#fff; font-weight:700; font-size:12px; text-transform:uppercase; letter-spacing:0.05em; text-align:center; padding:12px 14px; }
.rt-no { width:54px; } .rt-process { width:18%; } .rt-jobtask { width:auto; } .rt-bodyparts { width:20%; }
.rt-td-no { text-align:center; color:#6e7d8e; font-weight:600; font-size:12.5px; background:#f8fafc; }
.rt-td-process { font-weight:600; color:#1e2a36; font-size:13px; vertical-align:middle; background:#f8fafc; text-align:center; }
.rt-td-jobtask { font-size:13px; color:#2f3e4d; line-height:1.5; }
.rt-td-bodyparts { font-size:12.5px; color:#4a5568; text-align:center; }

/* Table 4.2 */
.t42-scroll { overflow-x:auto; }
.t42-table { width:100%; border-collapse:collapse; font-size:12.5px; color:#2f3e4d; font-family:Arial,sans-serif; min-width:1100px; }
.t42-table th,.t42-table td { border:1px solid #b0b8c4; padding:8px 10px; vertical-align:middle; text-align:center; }
.t42-table thead th { background:#2f3e4d; color:#fff; font-weight:700; font-size:11px; text-transform:uppercase; letter-spacing:0.05em; }
.t42h-erf { background:#17496e !important; font-size:12px !important; }
.t42h-no    { width:44px; }
.t42h-dept  { width:110px; }
.t42h-desc  { width:18%; text-align:left !important; }
.t42h-sub   { font-size:10.5px !important; width:9%; }
.t42h-pain  { width:13%; }
.t42h-rec   { width:14%; }
.t42-sub-note { font-size:9px; font-weight:400; text-transform:none; letter-spacing:0; opacity:.85; display:block; }

.t42-td-no   { background:#f8fafc; color:#6e7d8e; font-weight:600; font-size:12px; }
.t42-td-dept { background:#f0f2f5; font-weight:600; color:#1e2a36; font-size:12px; vertical-align:middle; }
.t42-td-desc { text-align:left; font-size:12.5px; color:#2f3e4d; line-height:1.45; }
.t42-td-pain { font-size:12px; color:#2f3e4d; }
.t42-td-rec  { font-size:12px; color:#2f3e4d; text-align:left; }

/* Risk factor cells */
.rf-green { background:#1a9e50; }
.rf-red   { background:#d72626; color:#fff; font-weight:600; font-size:11.5px; line-height:1.35; }
.rf-loading { background:#f0f2f5; }
.rf-text  { display:block; white-space:pre-line; text-align:center; }
.rf-spinner { display:inline-block; width:12px; height:12px; border:2px solid #d0d5dd; border-top-color:#3b7dd8; border-radius:50%; animation:spin .7s linear infinite; }

/* Shared empty state */
.rc-empty { display:flex; flex-direction:column; align-items:center; justify-content:center; gap:12px; padding:40px; color:#9aa3ae; font-size:13px; }

/* Footer */
.rc-footer { flex-shrink:0; display:flex; align-items:center; justify-content:space-between; padding:12px 32px; background:#fff; border-top:1px solid #eaecf0; }

/* ════════  Tables 4.3+ per-task detail  ════════ */
.t43-task-header {
  padding: 11px 24px;
  background: #17496e;
  color: #fff;
  font-weight: 700;
  font-size: 13.5px;
  font-family: Arial, sans-serif;
  letter-spacing: 0.01em;
}

.t43-table { width:100%; border-collapse:collapse; font-size:13px; color:#2f3e4d; font-family:Arial,sans-serif; }
.t43-table th, .t43-table td { border:1px solid #b0b8c4; padding:10px 14px; vertical-align:top; }
.t43-table thead th { background:#2f3e4d; color:#fff; font-weight:700; font-size:12px; text-transform:uppercase; letter-spacing:0.05em; text-align:center; padding:12px 14px; }

.t43h-factor { width:20%; }
.t43h-desc   { width:50%; }
.t43h-photo  { width:30%; }

.t43-row:nth-child(even) .t43-td-factor,
.t43-row:nth-child(even) .t43-td-desc { background:#f8fafc; }

.t43-td-factor {
  font-weight:700;
  color:#1e2a36;
  font-size:13px;
  vertical-align:middle;
  background:#f4f7fb;
}

.t43-td-desc { font-size:13px; color:#2f3e4d; line-height:1.6; }

.t43-desc-list { margin:0 0 6px 0; padding-left:18px; }
.t43-desc-list li { margin-bottom:5px; line-height:1.55; color:#2f3e4d; }
.t43-part { color:#6e7d8e; font-size:12px; }

.t43-empty-desc { color:#a0abb6; font-style:italic; }

.t43-bp-note {
  margin-top:8px;
  padding:6px 10px;
  background:#fff8e1;
  border:1px solid #ffe082;
  border-radius:4px;
  font-size:12px;
  color:#6d4c00;
  line-height:1.5;
}

.t43-remarks-note {
  margin-top:6px;
  font-size:12px;
  color:#5a6676;
  font-style:italic;
}

.t43-td-photo { text-align:center; vertical-align:top; border-left:2px solid #b0b8c4; }
.t43-photos   { display:flex; flex-direction:column; gap:14px; }
.t43-photo-item { display:flex; flex-direction:column; gap:6px; }
.t43-photo-img  { width:100%; max-height:240px; object-fit:contain; border:1px solid #dde1e8; border-radius:6px; background:#f0f2f5; display:block; }
.t43-photo-meta { text-align:center; }
.t43-photo-label   { font-size:12px; font-weight:600; color:#2f3e4d; margin-top:4px; }
.t43-photo-caption { font-size:11.5px; color:#6e7d8e; line-height:1.4; }
.t43-no-photo { color:#a0abb6; font-size:12px; font-style:italic; padding:20px 0; }

/* ── Table 4.3+ description toolbar & edit controls ── */
.t43-desc-toolbar { display:flex; align-items:center; gap:6px; margin-bottom:7px; flex-wrap:wrap; }
.t43-btn-edit {
  display:inline-flex; align-items:center; gap:4px;
  padding:3px 9px; font-size:11.5px; font-weight:500; cursor:pointer;
  border:1px solid #c3cad3; border-radius:5px;
  background:#f4f6f9; color:#3a5068;
  font-family:Arial,sans-serif;
  transition:background .15s, border-color .15s;
}
.t43-btn-edit:hover { background:#e6edf5; border-color:#8fa3bc; }
.t43-btn-restore {
  display:inline-flex; align-items:center; gap:4px;
  padding:3px 9px; font-size:11.5px; font-weight:500; cursor:pointer;
  border:1px solid #e0c070; border-radius:5px;
  background:#fffbe6; color:#7a5c00;
  font-family:Arial,sans-serif;
  transition:background .15s;
}
.t43-btn-restore:hover:not(:disabled) { background:#fef3c7; }
.t43-btn-restore:disabled { opacity:.55; cursor:default; }
.t43-custom-badge {
  font-size:10.5px; font-weight:600; letter-spacing:.04em;
  padding:2px 7px; border-radius:10px;
  background:#e0f0ff; color:#1a6fbf;
  border:1px solid #b3d9f7;
}
/* Edit textarea + actions */
.t43-edit-hint { font-size:11.5px; color:#6e7d8e; margin-bottom:5px; }
.t43-desc-textarea {
  width:100%; box-sizing:border-box;
  border:1px solid #b0c4d8; border-radius:6px;
  padding:8px 10px; font-size:12.5px; line-height:1.55;
  font-family:Arial,sans-serif; color:#2f3e4d;
  resize:vertical; outline:none;
  transition:border-color .15s, box-shadow .15s;
}
.t43-desc-textarea:focus { border-color:#3a7bd5; box-shadow:0 0 0 3px rgba(58,123,213,.13); }
.t43-edit-actions { display:flex; gap:8px; margin-top:8px; }
.t43-btn-save {
  display:inline-flex; align-items:center; gap:5px;
  padding:5px 14px; font-size:12px; font-weight:600; cursor:pointer;
  border:none; border-radius:6px;
  background:#17496e; color:#fff;
  font-family:Arial,sans-serif;
  transition:background .15s;
}
.t43-btn-save:hover:not(:disabled) { background:#1e6091; }
.t43-btn-save:disabled { opacity:.6; cursor:default; }
.t43-btn-cancel {
  padding:5px 14px; font-size:12px; font-weight:500; cursor:pointer;
  border:1px solid #c3cad3; border-radius:6px;
  background:#f4f6f9; color:#3a5068;
  font-family:Arial,sans-serif;
  transition:background .15s;
}
.t43-btn-cancel:hover { background:#e6edf5; }
.t43-btn-spinner {
  display:inline-block; width:11px; height:11px;
  border:2px solid rgba(255,255,255,.4); border-top-color:#fff;
  border-radius:50%; animation:t43spin .7s linear infinite;
}
@keyframes t43spin { to { transform:rotate(360deg); } }

/* Scrollbars */
.sp-list-wrap::-webkit-scrollbar,.order-panel::-webkit-scrollbar,.tables-area::-webkit-scrollbar,.rc-body::-webkit-scrollbar,.t42-scroll::-webkit-scrollbar { width:5px; height:5px; }
.sp-list-wrap::-webkit-scrollbar-track,.order-panel::-webkit-scrollbar-track,.tables-area::-webkit-scrollbar-track,.rc-body::-webkit-scrollbar-track,.t42-scroll::-webkit-scrollbar-track { background:transparent; }
.sp-list-wrap::-webkit-scrollbar-thumb,.order-panel::-webkit-scrollbar-thumb,.tables-area::-webkit-scrollbar-thumb,.rc-body::-webkit-scrollbar-thumb,.t42-scroll::-webkit-scrollbar-thumb { background:#c8cdd5; border-radius:3px; }
</style>
