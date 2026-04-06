<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../services/api'

const route = useRoute()
const router = useRouter()

const files = ref([])
const selectedId = ref(null)
const loadingFiles = ref(false)
const loadingDetails = ref(false)
const saving = ref(false)
const listError = ref('')
const detailError = ref('')
const feedback = ref('')
const mode = ref('preview')
const viewMode = ref('grid') // 'grid' | 'list'
const searchQuery = ref('')
const detailOpen = ref(false)
const step1StartDate = ref('')
const step1EndDate = ref('')

const isEditMode = computed(() => mode.value === 'edit')
const selectedMeta = computed(() => files.value.find(item => Number(item.id) === Number(selectedId.value)))

const filteredFiles = computed(() => {
  if (!searchQuery.value.trim()) return files.value
  const q = searchQuery.value.toLowerCase()
  return files.value.filter(f =>
    f.assessor_name?.toLowerCase().includes(q) ||
    f.department?.toLowerCase().includes(q) ||
    f.assessment_date?.toLowerCase().includes(q)
  )
})

const splitActivityLines = value => {
  return String(value || '')
    .split(/\r?\n|•/)
    .map(line => line.replace(/^[\s\-•]+/, '').trim())
    .filter(Boolean)
}

const getPhotoGroupCaption = (group) => {
  const title = String(group?.task_title || group?.title || '').trim()
  const description = String(group?.task_description || group?.description || '').trim()

  if (title && description) return `${title} - ${description}`
  if (title) return title
  if (description) return description
  return 'Task photo'
}

const revokePhotoPreviewUrls = (photoGroups) => {
  ;(photoGroups || []).forEach(group => {
    ;(group.photos || []).forEach(photo => {
      if (photo?.__previewUrl) URL.revokeObjectURL(photo.__previewUrl)
    })
  })
}

const normalizePhotoGroups = groups => {
  return (groups || []).map(group => ({
    ...group,
    photos: (group.photos || []).map(photo => ({ ...photo })),
  }))
}

const addPhotosToGroup = (event, groupIndex) => {
  const files = Array.from(event.target.files || [])
  if (!files.length) return

  const group = step1.value.photo_groups?.[groupIndex]
  if (!group) return

  const newPhotos = files.map((file, fileIndex) => {
    const previewUrl = URL.createObjectURL(file)
    return {
      id: null,
      url: previewUrl,
      file_path: null,
      __newFile: file,
      __previewUrl: previewUrl,
      __tempId: `new-${Date.now()}-${groupIndex}-${fileIndex}`,
    }
  })

  group.photos = [...(group.photos || []), ...newPhotos]
  event.target.value = ''
}

const removePhotoFromGroup = (groupIndex, photoIndex) => {
  const group = step1.value.photo_groups?.[groupIndex]
  if (!group || !Array.isArray(group.photos)) return

  const photo = group.photos[photoIndex]
  if (!photo) return
  if (photo.__previewUrl) URL.revokeObjectURL(photo.__previewUrl)

  group.photos.splice(photoIndex, 1)
}

const MONTHS = ['january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december']

const pad2 = value => String(value).padStart(2, '0')
const toIsoDate = (year, monthIndex, day) => `${year}-${pad2(monthIndex + 1)}-${pad2(day)}`

const parseAssessmentDateRange = rawValue => {
  const value = String(rawValue || '').trim()
  if (!value) return { start: '', end: '' }

  const rangeMatch = value.match(/^(\d{1,2})\s*[–-]\s*(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})$/)
  if (rangeMatch) {
    const startDay = Number(rangeMatch[1])
    const endDay = Number(rangeMatch[2])
    const monthIndex = MONTHS.indexOf(String(rangeMatch[3]).toLowerCase())
    const year = Number(rangeMatch[4])
    if (monthIndex >= 0 && year >= 1000 && startDay >= 1 && endDay >= 1) {
      return {
        start: toIsoDate(year, monthIndex, startDay),
        end: toIsoDate(year, monthIndex, endDay),
      }
    }
  }

  const singleDate = value.match(/^(\d{4})-(\d{2})-(\d{2})$/)
  if (singleDate) {
    return { start: value, end: value }
  }

  return { start: '', end: '' }
}

const formatAssessmentDateRange = (start, end) => {
  if (!start || !end) return ''
  const startDate = new Date(`${start}T00:00:00`)
  const endDate = new Date(`${end}T00:00:00`)
  if (Number.isNaN(startDate.getTime()) || Number.isNaN(endDate.getTime())) return ''

  const startDay = startDate.toLocaleDateString('en-GB', { day: 'numeric' })
  const endDay = endDate.toLocaleDateString('en-GB', { day: 'numeric' })
  const monthYear = endDate.toLocaleDateString('en-GB', { month: 'long', year: 'numeric' })
  return `${startDay}–${endDay} ${monthYear}`
}

const step1 = ref({ assessment: {}, processes: [], photo_groups: [] })
const step2 = ref({ templates: [], tasks: [], answers: [] })
const step3 = ref({ rows: [], push_pull: { activities: [], task_not_applicable: {} }, carrying_summary: { rows: [], task_not_applicable: {} }, manual_summary: { rows: [], task_not_applicable: {} } })
const step4 = ref({ rows: [], task_not_applicable: {} })
const step5 = ref({ rows: [], task_not_applicable: {} })
const step6 = ref({ rows: [], task_not_applicable: {} })
const step7 = ref({ pain_parts: {} })

const BODY_PART_OPTIONS = ['Neck', 'Shoulder', 'Upper back', 'Lower back', 'Upper arm', 'Elbow', 'Lower Arm', 'Hand/Wrist', 'Thigh', 'Knee', 'Lower leg', 'Ankle/Foot']

const step1ProcessRows = computed(() => {
  const rows = []

  ;(step1.value.processes || []).forEach(process => {
    const processTasks = Array.isArray(process.tasks) && process.tasks.length > 0
      ? process.tasks
      : [{ id: `empty-${process.id}`, row_number: '', title: '', description: '', worker_activities: '' }]

    processTasks.forEach((task, index) => {
      rows.push({
        key: `${process.id}-${task.id ?? index}`,
        process,
        task,
        showProcessCell: index === 0,
        rowspan: processTasks.length,
      })
    })
  })

  return rows
})

const step1SummaryItems = computed(() => {
  const items = []
  const workingHours = String(step1.value.assessment?.working_hours || '').trim()
  const breaks = String(step1.value.assessment?.breaks || '').trim()

  if (workingHours) items.push(`Working Hours - ${workingHours}`)
  if (breaks) items.push(`Breaks - ${breaks}`)

  if (items.length === 0) {
    ;(step1.value.processes || []).forEach(process => {
      ;(process.tasks || []).forEach(task => {
        const text = String(task.description || '').trim()
        if (text) items.push(text)
      })
    })
  }

  return items.length > 0 ? items : ['No task description provided.']
})

const displayAssessmentDate = computed(() => {
  const formatted = formatAssessmentDateRange(step1StartDate.value, step1EndDate.value)
  return formatted || step1.value.assessment?.assessment_date || ''
})

const tasks = computed(() => step2.value.tasks || [])
const answerMap = computed(() => {
  const out = {}
  ;(step2.value.answers || []).forEach(answer => { out[`${answer.task_id}_${answer.checklist_item_id}`] = answer.answer })
  return out
})

const STEP7_FACTOR_CONFIG = [
  { key: 'awkward_posture', label: 'Awkward posture', totalScore: 13, threshold: 6 },
  { key: 'static_sustained', label: 'Static and sustained work posture', totalScore: 3, threshold: 1 },
  { key: 'forceful_exertion', label: 'Forceful exertion', totalScore: 1, threshold: 1 },
  { key: 'repetition', label: 'Repetition', totalScore: 5, threshold: 1 },
  { key: 'vibration', label: 'Vibration', totalScore: 4, threshold: 1 },
  { key: 'lighting', label: 'Lighting', totalScore: 1, threshold: 1 },
  { key: 'temperature', label: 'Temperature', totalScore: 1, threshold: 1 },
  { key: 'ventilation', label: 'Ventilation', totalScore: 1, threshold: 1 },
  { key: 'noise', label: 'Noise', totalScore: 2, threshold: 1 },
]

const STEP7_FORCEFUL_GROUPS = [
  { label: 'Lifting/Lowering', rowKeys: ['lifting_lowering', 'repetitive_lifting_lowering', 'twisted_posture_lifting_lowering', 'repetitive_with_twisted_posture'] },
  { label: 'Pushing/Pulling', rowKeys: ['pushing_pulling'] },
  { label: 'Handling Load in Seated', rowKeys: ['handling_seated_position'] },
  { label: 'Carrying', rowKeys: ['carrying'] },
  { label: 'Other', rowKeys: ['other_forceful_activity'] },
]

const STEP7_TASKS_PER_TABLE = 2
const step7HeaderClassForTask = index => (index % 2 === 0 ? 'task-head-a' : 'task-head-b')
const step7BadgeClass = needAdvanced => (needAdvanced ? 'badge-yes' : 'badge-no')
const toBool = value => value === true || value === 1 || value === '1'
const responseForTask = (responses, taskId) => (Array.isArray(responses) ? responses.find(r => Number(r.task_id) === Number(taskId)) || null : null)
const yesValue = resp => Boolean(resp && toBool(resp.answer) && !toBool(resp.not_applicable))

const countYesRows = (rows, taskId, allowedKeys = null) => {
  if (!Array.isArray(rows)) return 0
  const keySet = allowedKeys ? new Set(allowedKeys.map(String)) : null
  let count = 0

  rows.forEach(row => {
    if (keySet && !keySet.has(String(row.key))) return
    const resp = responseForTask(row.responses, taskId)
    if (yesValue(resp)) count += 1
  })

  return count
}

const checklistScore = (taskId, items) => {
  let score = 0
  ;(items || []).forEach(item => {
    if (answerMap.value[`${taskId}_${item.id}`] === true) score += 1
  })
  return score
}

const step7TaskGroups = computed(() => {
  const out = []
  for (let i = 0; i < tasks.value.length; i += STEP7_TASKS_PER_TABLE) {
    out.push({
      index: out.length,
      start: i + 1,
      end: Math.min(i + STEP7_TASKS_PER_TABLE, tasks.value.length),
      tasks: tasks.value.slice(i, i + STEP7_TASKS_PER_TABLE),
    })
  }
  return out
})

const step7SummaryRows = computed(() => {
  const templates = Array.isArray(step2.value.templates) ? step2.value.templates : []
  const awkwardTemplate = templates.find(t => String(t.name || '').toLowerCase().includes('awkward posture'))
  const staticTemplate = templates.find(t => String(t.name || '').toLowerCase().includes('static and sustained'))
  const awkwardItems = Array.isArray(awkwardTemplate?.items) ? awkwardTemplate.items : []
  const staticItems = Array.isArray(staticTemplate?.items) ? staticTemplate.items : []
  const forcefulManualRows = Array.isArray(step3.value.manual_summary?.rows) ? step3.value.manual_summary.rows : []
  const repetitiveRows = Array.isArray(step4.value.rows) ? step4.value.rows : []
  const vibrationRows = Array.isArray(step5.value.rows) ? step5.value.rows : []
  const environmentalRows = Array.isArray(step6.value.rows) ? step6.value.rows : []

  return STEP7_FACTOR_CONFIG.map(factor => {
    const taskResults = tasks.value.map(task => {
      const taskId = Number(task.id)
      const awkwardScore = checklistScore(taskId, awkwardItems)
      const staticScore = checklistScore(taskId, staticItems)
      const repetitionScore = countYesRows(repetitiveRows, taskId)
      const vibrationScore = countYesRows(vibrationRows, taskId)
      const lightingScore = countYesRows(environmentalRows, taskId, ['inadequate_lighting'])
      const temperatureScore = countYesRows(environmentalRows, taskId, ['extreme_temperature'])
      const ventilationScore = countYesRows(environmentalRows, taskId, ['inadequate_air_ventilation'])
      const noiseScore = countYesRows(environmentalRows, taskId, ['noise_above_pel', 'annoying_noise_more_than_8_hours'])

      let score = 0
      let details = null
      if (factor.key === 'awkward_posture') score = awkwardScore
      if (factor.key === 'static_sustained') score = staticScore
      if (factor.key === 'forceful_exertion') {
        details = {}
        STEP7_FORCEFUL_GROUPS.forEach(group => {
          details[group.label] = countYesRows(forcefulManualRows, taskId, group.rowKeys)
        })
        score = Object.values(details).reduce((sum, value) => sum + Number(value), 0)
      }
      if (factor.key === 'repetition') score = repetitionScore
      if (factor.key === 'vibration') score = vibrationScore
      if (factor.key === 'lighting') score = lightingScore
      if (factor.key === 'temperature') score = temperatureScore
      if (factor.key === 'ventilation') score = ventilationScore
      if (factor.key === 'noise') score = noiseScore

      return {
        taskId,
        score,
        details,
        needAdvanced: Number(score) >= Number(factor.threshold),
      }
    })

    return {
      ...factor,
      taskResultsById: Object.fromEntries(taskResults.map(result => [String(result.taskId), result])),
    }
  })
})

const STEP7_EMPTY_RESULT = Object.freeze({ taskId: null, score: 0, details: null, needAdvanced: false })
const getStep7TaskResult = (row, taskId) => row?.taskResultsById?.[String(taskId)] ?? STEP7_EMPTY_RESULT

const normalizePainPartKey = v => String(v || '').toLowerCase().replace(/[-_]/g, ' ').replace(/\s+/g, ' ').trim()
const setMode = next => { mode.value = next; feedback.value = '' }
const isTaskNA = (section, taskId) => Boolean(section.task_not_applicable?.[String(taskId)])
const requestedMode = () => (String(route.query.mode || '').toLowerCase() === 'edit' ? 'edit' : 'preview')

const safeGet = async url => {
  try {
    const response = await api.get(url)
    return response.data
  } catch (error) {
    if (error?.response?.status === 422) return null
    throw error
  }
}

const defaultStep2Answers = (assessmentId, templates, taskList) => {
  const out = []
  ;(templates || []).forEach(template => {
    ;(template.items || []).forEach(item => {
      ;(taskList || []).forEach(task => {
        out.push({ assessment_id: Number(assessmentId), task_id: Number(task.id), checklist_item_id: Number(item.id), answer: false })
      })
    })
  })
  return out
}

const normalizeSimple = apiData => ({ rows: Array.isArray(apiData?.rows) ? apiData.rows : [], task_not_applicable: apiData?.task_not_applicable || {} })

const normalizePainMap = (taskList, saved) => {
  const out = {}
  ;(taskList || []).forEach(task => {
    const taskKey = String(task.id)
    const map = {}
    BODY_PART_OPTIONS.forEach(part => { map[normalizePainPartKey(part)] = false })
    const savedParts = Array.isArray(saved?.[taskKey]) ? saved[taskKey] : []
    savedParts.forEach(part => {
      const key = normalizePainPartKey(part)
      if (Object.prototype.hasOwnProperty.call(map, key)) map[key] = true
    })
    out[taskKey] = map
  })
  return out
}

const setStep2Answer = (taskId, itemId, value) => {
  if (!isEditMode.value) return
  const target = step2.value.answers.find(a => Number(a.task_id) === Number(taskId) && Number(a.checklist_item_id) === Number(itemId))
  if (target) target.answer = value
}

const setRowAnswer = (row, taskId, value, section = null) => {
  if (!isEditMode.value) return
  if (section && isTaskNA(section, taskId)) return
  const target = (row.responses || row.answers || []).find(item => Number(item.task_id) === Number(taskId))
  if (target) target.answer = value
}

const rowAnswer = (row, taskId) => Boolean((row.responses || row.answers || []).find(item => Number(item.task_id) === Number(taskId))?.answer)

const setTaskNA = (section, taskId, checked) => {
  if (!isEditMode.value) return
  section.task_not_applicable[String(taskId)] = checked
  ;(section.rows || []).forEach(row => {
    const target = (row.responses || []).find(item => Number(item.task_id) === Number(taskId))
    if (!target) return
    target.not_applicable = checked
    if (checked) target.answer = false
  })
}

const isPushPullTaskNA = taskId => Boolean(step3.value.push_pull.task_not_applicable?.[String(taskId)])
const setPushPullTaskNA = (taskId, checked) => {
  if (!isEditMode.value) return
  step3.value.push_pull.task_not_applicable[String(taskId)] = checked
  ;(step3.value.push_pull.activities || []).forEach(activity => {
    const target = (activity.responses || []).find(item => Number(item.task_id) === Number(taskId))
    if (!target) return
    target.not_applicable = checked
    if (checked) target.answer = false
  })
}

const pushPullAnswer = (activity, taskId) => Boolean((activity.responses || []).find(item => Number(item.task_id) === Number(taskId))?.answer)
const setPushPullAnswer = (activity, taskId, value) => {
  if (!isEditMode.value || isPushPullTaskNA(taskId)) return
  const target = (activity.responses || []).find(item => Number(item.task_id) === Number(taskId))
  if (target) target.answer = value
}
const manualSummaryYesCount = taskId => (step3.value.manual_summary?.rows || []).filter(row => {
  if (isTaskNA(step3.value.manual_summary, taskId)) return false
  const target = (row.responses || []).find(item => Number(item.task_id) === Number(taskId))
  return Boolean(target?.answer)
}).length

const setPain = (taskId, part, checked) => {
  if (!isEditMode.value) return
  const taskKey = String(taskId)
  if (!step7.value.pain_parts[taskKey]) step7.value.pain_parts[taskKey] = {}
  step7.value.pain_parts[taskKey][normalizePainPartKey(part)] = Boolean(checked)
}

const painChecked = (taskId, part) => Boolean(step7.value.pain_parts?.[String(taskId)]?.[normalizePainPartKey(part)])

const flattenSummaryRows = section => {
  const out = []
  ;(section.rows || []).forEach(row => {
    ;(tasks.value || []).forEach(task => {
      const taskId = Number(task.id)
      const target = (row.responses || []).find(item => Number(item.task_id) === taskId)
      const na = isTaskNA(section, taskId) || Boolean(target?.not_applicable)
      out.push({ row_key: row.key, task_id: taskId, answer: na ? false : Boolean(target?.answer), not_applicable: na, remarks: row.remarks ?? '' })
    })
  })
  return out
}

const flattenPushPull = () => {
  const out = []
  ;(step3.value.push_pull.activities || []).forEach(activity => {
    ;(tasks.value || []).forEach(task => {
      const taskId = Number(task.id)
      const target = (activity.responses || []).find(item => Number(item.task_id) === taskId)
      const na = Boolean(step3.value.push_pull.task_not_applicable?.[String(taskId)]) || Boolean(target?.not_applicable)
      out.push({ activity_key: activity.key, task_id: taskId, answer: na ? false : Boolean(target?.answer), not_applicable: na })
    })
  })
  return out
}

const flattenPain = () => {
  const out = []
  ;(tasks.value || []).forEach(task => {
    const map = step7.value.pain_parts?.[String(task.id)] || {}
    BODY_PART_OPTIONS.forEach(part => {
      const key = normalizePainPartKey(part)
      if (Boolean(map[key])) out.push({ task_id: Number(task.id), body_part: key })
    })
  })
  return out
}

const loadDetails = async id => {
  loadingDetails.value = true
  detailError.value = ''
  try {
    const [assessmentData, checklistData, forcefulData, repetitiveData, vibrationData, environmentalData, summaryPainData] = await Promise.all([
      api.get(`/era-assessments/${id}`).then(r => r.data),
      safeGet(`/era-checklist/${id}`),
      safeGet(`/era-forceful-exertion/${id}`),
      safeGet(`/era-repetitive-motion/${id}`),
      safeGet(`/era-vibration/${id}`),
      safeGet(`/era-environmental-factors/${id}`),
      safeGet(`/era-summary-pain-parts/${id}`),
    ])
    revokePhotoPreviewUrls(step1.value.photo_groups)
    step1.value = {
      assessment: assessmentData?.assessment || {},
      processes: assessmentData?.processes || [],
      photo_groups: normalizePhotoGroups(assessmentData?.photo_groups || []),
    }
    const parsedRange = parseAssessmentDateRange(step1.value.assessment?.assessment_date)
    step1StartDate.value = parsedRange.start
    step1EndDate.value = parsedRange.end
    const taskList = checklistData?.tasks || []
    step2.value = { templates: checklistData?.templates || [], tasks: taskList, answers: (checklistData?.answers?.length > 0) ? checklistData.answers : defaultStep2Answers(id, checklistData?.templates || [], taskList) }
    step3.value = { rows: forcefulData?.rows || [], push_pull: forcefulData?.push_pull || { activities: [], task_not_applicable: {} }, carrying_summary: forcefulData?.carrying_summary || { rows: [], task_not_applicable: {} }, manual_summary: forcefulData?.manual_summary || { rows: [], task_not_applicable: {} } }
    step4.value = normalizeSimple(repetitiveData)
    step5.value = normalizeSimple(vibrationData)
    step6.value = normalizeSimple(environmentalData)
    step7.value = { pain_parts: normalizePainMap(taskList, summaryPainData?.pain_parts || {}) }
  } catch (error) {
    console.error(error)
    detailError.value = 'Unable to load selected file.'
  } finally {
    loadingDetails.value = false
  }
}

const selectFile = async (id, nextMode = mode.value) => {
  selectedId.value = Number(id)
  setMode(nextMode)
  detailOpen.value = true
  await loadDetails(Number(id))
}

const closeDetail = () => {
  if (!selectedId.value) {
    router.push({ name: 'era-assessment-files' })
    return
  }

  const query = {
    selected: String(selectedId.value),
    mode: mode.value,
  }
  if (String(route.query.view || '').toLowerCase() === 'list') query.view = 'list'

  router.push({
    name: 'era-assessment-files',
    query,
  })
}

const loadFiles = async () => {
  loadingFiles.value = true
  listError.value = ''
  try {
    const response = await api.get('/era-assessments')
    files.value = Array.isArray(response.data?.assessments) ? response.data.assessments : []
    if (files.value.length > 0 && selectedId.value) {
      const stillExists = files.value.some(item => Number(item.id) === Number(selectedId.value))
      if (!stillExists) { selectedId.value = null; detailOpen.value = false }
    }
  } catch (error) {
    console.error(error)
    listError.value = 'Unable to load ERA assessment files.'
  } finally {
    loadingFiles.value = false
  }
}

const saveAll = async () => {
  if (!selectedId.value || !isEditMode.value || saving.value) return
  saving.value = true
  feedback.value = ''
  try {
    const id = Number(selectedId.value)
    const formattedDate = formatAssessmentDateRange(step1StartDate.value, step1EndDate.value)
    const assessmentDateToSave = formattedDate || step1.value.assessment.assessment_date
    const formData = new FormData()
    formData.append('_method', 'PUT')
    formData.append('assessor_name', step1.value.assessment.assessor_name || '')
    formData.append('assessment_date', assessmentDateToSave || '')
    formData.append('department', step1.value.assessment.department || '')
    formData.append('working_hours', step1.value.assessment.working_hours || '')
    formData.append('breaks', step1.value.assessment.breaks || '')

    ;(step1.value.processes || []).forEach((process, pIdx) => {
      formData.append(`processes[${pIdx}][id]`, String(Number(process.id)))
      formData.append(`processes[${pIdx}][name]`, process.name || '')
      ;(process.tasks || []).forEach((task, tIdx) => {
        formData.append(`processes[${pIdx}][tasks][${tIdx}][id]`, String(Number(task.id)))
        formData.append(`processes[${pIdx}][tasks][${tIdx}][title]`, task.title || '')
        formData.append(`processes[${pIdx}][tasks][${tIdx}][description]`, task.description || '')
        formData.append(`processes[${pIdx}][tasks][${tIdx}][worker_activities]`, task.worker_activities || '')
        formData.append(`processes[${pIdx}][tasks][${tIdx}][row_number]`, String(Number(task.row_number)))
      })
    })

    ;(step1.value.photo_groups || []).forEach((group, gIdx) => {
      formData.append(`photo_groups[${gIdx}][id]`, String(Number(group.id)))
      formData.append(`photo_groups[${gIdx}][title]`, group.title || '')
      formData.append(`photo_groups[${gIdx}][description]`, group.description || '')

      const keptPhotoIds = (group.photos || [])
        .filter(photo => Number.isFinite(Number(photo.id)) && Number(photo.id) > 0)
        .map(photo => Number(photo.id))

      keptPhotoIds.forEach((photoId, kIdx) => {
        formData.append(`photo_groups[${gIdx}][keep_photo_ids][${kIdx}]`, String(photoId))
      })

      ;(group.photos || [])
        .filter(photo => photo.__newFile instanceof File)
        .forEach(photo => {
          formData.append(`photo_groups[${gIdx}][new_photos][]`, photo.__newFile)
        })
    })

    await api.post(`/era-assessments/${id}`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    await api.post('/era-checklist', { assessment_id: id, answers: step2.value.answers })
    await api.post('/era-forceful-exertion', { assessment_id: id, rows: step3.value.rows, push_pull: { responses: flattenPushPull() }, carrying_summary: { responses: flattenSummaryRows(step3.value.carrying_summary) }, manual_summary: { responses: flattenSummaryRows(step3.value.manual_summary) } })
    await api.post('/era-repetitive-motion', { assessment_id: id, task_not_applicable: step4.value.task_not_applicable, rows: step4.value.rows })
    await api.post('/era-vibration', { assessment_id: id, task_not_applicable: step5.value.task_not_applicable, rows: step5.value.rows })
    await api.post('/era-environmental-factors', { assessment_id: id, task_not_applicable: step6.value.task_not_applicable, rows: step6.value.rows })
    await api.post('/era-summary-pain-parts', { assessment_id: id, pain_parts: flattenPain() })
    feedback.value = 'Changes saved successfully.'
    setMode('preview')
    await loadDetails(id)
  } catch (error) {
    console.error(error.response?.data || error)
    feedback.value = 'Unable to save changes.'
  } finally {
    saving.value = false
  }
}

// Get initials for file icon
const getInitials = (name) => {
  if (!name) return 'ER'
  return name.split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase()
}

// Assign a color per file based on id
const FILE_COLORS = ['#3b7dd8','#2a7a52','#8b5cf6','#d97706','#dc2626','#0891b2','#7c3aed','#059669']
const fileColor = (id) => FILE_COLORS[Number(id) % FILE_COLORS.length]

const loadRouteAssessment = async () => {
  const id = Number(route.params.assessmentId)
  if (!Number.isFinite(id) || id <= 0) {
    detailError.value = 'Invalid assessment file id.'
    detailOpen.value = false
    return
  }

  await loadFiles()
  await selectFile(id, requestedMode())
}

watch(
  () => route.params.assessmentId,
  () => {
    loadRouteAssessment()
  }
)

watch(
  () => route.query.mode,
  nextMode => {
    setMode(String(nextMode).toLowerCase() === 'edit' ? 'edit' : 'preview')
  }
)

onMounted(loadRouteAssessment)
onUnmounted(() => {
  revokePhotoPreviewUrls(step1.value.photo_groups)
})
</script>

<template>
  <div class="explorer-root">

    <!-- ── TOP TOOLBAR ── -->
    <div class="toolbar">
      <div class="toolbar-left">
        <div class="address-bar">
          <span class="addr-part">ERA System</span>
          <span class="addr-sep">></span>
          <span class="addr-part">Assessment Files</span>
          <span class="addr-sep">></span>
          <span class="addr-part active">View Total Information</span>
        </div>
      </div>

      <div class="toolbar-right toolbar-right-single">
        <button type="button" class="close-page-btn" @click="closeDetail">Close</button>
      </div>
    </div>

    <div class="explorer-body" :class="{ 'panel-open': detailOpen }">

      <!-- FILE AREA -->
      <div class="file-area total-hidden">

        <!-- Loading / Error States -->
        <div v-if="loadingFiles" class="empty-state">
          <div class="spinner"></div>
          <span>Loading files...</span>
        </div>
        <div v-else-if="listError" class="empty-state error-state">
          <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <span>{{ listError }}</span>
        </div>
        <div v-else-if="filteredFiles.length === 0" class="empty-state">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
          <span>No assessment files found</span>
        </div>

        <!-- GRID VIEW -->
        <div v-else-if="viewMode === 'grid'" class="grid-view">
          <button
            v-for="file in filteredFiles"
            :key="file.id"
            type="button"
            class="file-icon-card"
            :class="{ selected: Number(file.id) === Number(selectedId) }"
            @click="selectFile(file.id)"
          >
            <div class="file-icon" :style="{ background: fileColor(file.id) }">
              <svg class="doc-bg" width="48" height="60" viewBox="0 0 48 60" fill="none">
                <rect width="48" height="60" rx="4" fill="rgba(255,255,255,0.12)"/>
                <path d="M30 2 L30 14 L42 14" stroke="rgba(255,255,255,0.3)" stroke-width="1.5" fill="none"/>
                <rect x="8" y="22" width="24" height="2" rx="1" fill="rgba(255,255,255,0.3)"/>
                <rect x="8" y="28" width="32" height="2" rx="1" fill="rgba(255,255,255,0.25)"/>
                <rect x="8" y="34" width="28" height="2" rx="1" fill="rgba(255,255,255,0.2)"/>
                <rect x="8" y="40" width="20" height="2" rx="1" fill="rgba(255,255,255,0.15)"/>
              </svg>
              <div class="file-initials">{{ getInitials(file.assessor_name) }}</div>
              <div class="era-badge">ERA</div>
            </div>
            <div class="file-name">{{ file.assessor_name || 'Unknown' }}</div>
            <div class="file-meta">{{ file.assessment_date }}</div>
            <div class="file-dept">{{ file.department }}</div>
          </button>
        </div>

        <!-- LIST VIEW -->
        <div v-else class="list-view">
          <div class="list-header">
            <span class="lh-name">Name</span>
            <span class="lh-date">Date</span>
            <span class="lh-dept">Department</span>
            <span class="lh-id">ID</span>
          </div>
          <button
            v-for="file in filteredFiles"
            :key="file.id"
            type="button"
            class="list-row"
            :class="{ selected: Number(file.id) === Number(selectedId) }"
            @click="selectFile(file.id)"
          >
            <span class="lr-icon" :style="{ background: fileColor(file.id) }">{{ getInitials(file.assessor_name) }}</span>
            <span class="lr-name">{{ file.assessor_name || 'Unknown' }}</span>
            <span class="lr-date">{{ file.assessment_date }}</span>
            <span class="lr-dept">{{ file.department }}</span>
            <span class="lr-id">#{{ file.id }}</span>
          </button>
        </div>

      </div>

      <!-- ── DETAIL PANEL ── -->
      <aside class="detail-panel" :class="{ open: detailOpen }">
        <div v-if="!detailOpen" class="detail-empty">
          <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
          <span>Select a file to view details</span>
        </div>

        <template v-else>
          <!-- Panel Header -->
          <div class="panel-header">
            <div class="panel-file-icon" :style="{ background: selectedMeta ? fileColor(selectedMeta.id) : '#3b7dd8' }">
              {{ getInitials(selectedMeta?.assessor_name) }}
            </div>
            <div class="panel-title-block">
              <div class="panel-title">{{ selectedMeta?.assessor_name }}</div>
              <div class="panel-subtitle">Assessment ID #{{ selectedMeta?.id }}</div>
            </div>
            <button type="button" class="close-btn" @click="closeDetail" title="Close">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>

          <!-- Panel Actions -->
          <div class="panel-actions">
            <button type="button" class="pa-btn" :class="{ 'pa-active': mode === 'preview' }" @click="setMode('preview')">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              Preview
            </button>
            <button type="button" class="pa-btn" :class="{ 'pa-active': mode === 'edit' }" @click="setMode('edit')">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              Edit
            </button>
            <button type="button" class="pa-btn pa-save" :disabled="!isEditMode || saving" @click="saveAll">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
              {{ saving ? 'Saving...' : 'Save All' }}
            </button>
          </div>

          <div v-if="feedback" class="feedback-bar" :class="{ success: feedback.includes('success'), error: feedback.includes('Unable') }">
            {{ feedback }}
          </div>

          <!-- Panel Content -->
          <div v-if="loadingDetails" class="panel-loading">
            <div class="spinner"></div>
            <span>Loading file data...</span>
          </div>
          <div v-else-if="detailError" class="panel-loading error-state">{{ detailError }}</div>

          <div v-else class="panel-content">

            <!-- Step 1 -->
            <div class="step-section step1-section">
              <div class="step1-title">INITIAL ERGONOMICS RISK ASSESSMENT CHECKLIST</div>

              <div class="table-wrap step1-meta-wrap">
                <table class="table step1-meta-table">
                  <tbody>
                    <tr>
                      <th>Assessor</th>
                      <td>
                        <input v-model="step1.assessment.assessor_name" :disabled="!isEditMode" class="cell-in step1-meta-input" />
                      </td>
                      <th>Date</th>
                      <td>
                        <div v-if="isEditMode" class="step1-date-edit">
                          <div class="step1-date-field">
                            <span class="step1-date-label">From</span>
                            <input v-model="step1StartDate" type="date" class="cell-in step1-meta-input step1-date-input" />
                          </div>
                          <div class="step1-date-field">
                            <span class="step1-date-label">To</span>
                            <input v-model="step1EndDate" type="date" class="cell-in step1-meta-input step1-date-input" />
                          </div>
                        </div>
                        <input v-else :value="displayAssessmentDate" disabled class="cell-in step1-meta-input" />
                      </td>
                    </tr>
                    <tr>
                      <th>Department</th>
                      <td colspan="3">
                        <input v-model="step1.assessment.department" :disabled="!isEditMode" class="cell-in step1-meta-input" />
                      </td>
                    </tr>
                    <tr>
                      <th>Task Description</th>
                      <td colspan="3">
                        <div v-if="isEditMode" class="step1-summary-edit">
                          <div class="step1-summary-field">
                            <label>Working Hours</label>
                            <input
                              v-model="step1.assessment.working_hours"
                              class="cell-in step1-meta-input"
                              placeholder="e.g. 8.00am - 5.30pm"
                            />
                          </div>
                          <div class="step1-summary-field">
                            <label>Breaks</label>
                            <input
                              v-model="step1.assessment.breaks"
                              class="cell-in step1-meta-input"
                              placeholder="e.g. 10.00am - 10.10am, 1.00pm - 2.00pm"
                            />
                          </div>
                        </div>
                        <ul v-else class="step1-bullet-list">
                          <li v-for="item in step1SummaryItems" :key="item">{{ item }}</li>
                        </ul>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <div class="table-wrap">
                <table class="table step1-process-table">
                  <thead>
                    <tr>
                      <th class="tc col-no">No</th>
                      <th class="col-process">Process or Location</th>
                      <th class="col-task">Task</th>
                      <th class="col-worker">Worker's Activities</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="row in step1ProcessRows" :key="row.key">
                      <td class="tc">{{ row.task.row_number }}</td>
                      <td v-if="row.showProcessCell" :rowspan="row.rowspan" class="process-cell">
                        <input v-model="row.process.name" :disabled="!isEditMode" class="cell-in" />
                      </td>
                      <td>
                        <input v-model="row.task.title" :disabled="!isEditMode" class="cell-in" />
                        <div class="step1-task-desc">
                          <input v-model="row.task.description" :disabled="!isEditMode" class="cell-in" />
                        </div>
                      </td>
                      <td>
                        <textarea v-if="isEditMode" v-model="row.task.worker_activities" :disabled="!isEditMode" class="cell-in ta"></textarea>
                        <ul v-else class="step1-bullet-list compact">
                          <li v-for="(line, idx) in splitActivityLines(row.task.worker_activities)" :key="`${row.key}-${idx}`">{{ line }}</li>
                          <li v-if="splitActivityLines(row.task.worker_activities).length === 0">-</li>
                        </ul>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <div v-if="step1.photo_groups?.length" class="step1-photos-block">
                <div class="step1-photos-title">Photos and descriptions</div>
                <div class="step1-photo-groups">
                  <div v-for="(group, groupIndex) in step1.photo_groups" :key="group.id" class="step1-photo-group">
                    <div v-if="isEditMode" class="step1-photo-controls">
                      <label class="step1-photo-add-btn">
                        <input
                          type="file"
                          multiple
                          accept="image/*"
                          class="step1-photo-file-input"
                          @change="addPhotosToGroup($event, groupIndex)"
                        />
                        Add photos
                      </label>
                    </div>
                    <div class="step1-photo-strip">
                      <div
                        v-for="(photo, photoIndex) in group.photos || []"
                        :key="photo.id || photo.__tempId || `${group.id}-${photoIndex}`"
                        class="step1-photo-item"
                      >
                        <a
                          v-if="!isEditMode"
                          :href="photo.url"
                          target="_blank"
                          rel="noopener noreferrer"
                          class="step1-photo-link"
                          title="Open full-size image"
                        >
                          <img
                            :src="photo.url"
                            class="step1-photo-thumb"
                            alt="Assessment photo"
                            loading="lazy"
                            decoding="async"
                          />
                        </a>
                        <div v-else class="step1-photo-edit-item">
                          <img
                            :src="photo.url"
                            class="step1-photo-thumb"
                            alt="Assessment photo"
                            loading="lazy"
                            decoding="async"
                          />
                          <button
                            type="button"
                            class="step1-photo-remove-btn"
                            :aria-label="`Remove photo ${photoIndex + 1}`"
                            @click="removePhotoFromGroup(groupIndex, photoIndex)"
                          >
                            &times;
                          </button>
                        </div>
                      </div>
                    </div>
                    <div class="step1-photo-caption">
                      {{ getPhotoGroupCaption(group) }}
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Step 2 -->
            <div class="step-section">
              <div class="step-label"><span class="step-num">2</span>Ergonomic Risk Factor</div>
              <div v-for="template in step2.templates" :key="template.id" class="process-block">
                <div class="process-name">{{ template.name }}</div>
                <div class="table-wrap">
                  <table class="table">
                    <thead>
                      <tr>
                        <th>Body Part</th><th>Physical Risk Factor</th><th>Max Exposure</th>
                        <th v-for="task in tasks" :key="`h2-${template.id}-${task.id}`" colspan="2">{{ task.title }}</th>
                      </tr>
                      <tr>
                        <th></th><th></th><th></th>
                        <template v-for="task in tasks" :key="`h2s-${template.id}-${task.id}`"><th>Yes</th><th>No</th></template>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="item in template.items" :key="item.id">
                        <td>{{ item.body_part }}</td><td>{{ item.description }}</td><td>{{ item.max_duration }}</td>
                        <template v-for="task in tasks" :key="`c2-${item.id}-${task.id}`">
                          <td class="tc"><input type="radio" :name="`s2-${item.id}-${task.id}`" :checked="answerMap[`${task.id}_${item.id}`] === true" :disabled="!isEditMode" @change="setStep2Answer(task.id, item.id, true)" /></td>
                          <td class="tc"><input type="radio" :name="`s2-${item.id}-${task.id}`" :checked="answerMap[`${task.id}_${item.id}`] === false" :disabled="!isEditMode" @change="setStep2Answer(task.id, item.id, false)" /></td>
                        </template>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

            <!-- Step 3 -->
            <div class="step-section">
              <div class="step-label"><span class="step-num">3</span>Forceful Exertion</div>
              <img src="/images/Forceful Exertion.png" class="ref-img" alt="Forceful Exertion" />
              <div class="table-wrap">
                <table class="table">
                  <thead>
                    <tr>
                      <th rowspan="2">Working Height</th><th rowspan="2">Recommended</th><th rowspan="2">Current</th>
                      <th v-for="task in tasks" :key="`h3-${task.id}`" colspan="2">{{ task.title }}</th>
                      <th rowspan="2">Remarks</th>
                    </tr>
                    <tr><template v-for="task in tasks" :key="`h3s-${task.id}`"><th>Yes</th><th>No</th></template></tr>
                  </thead>
                  <tbody>
                    <tr v-for="row in step3.rows" :key="row.key">
                      <td>{{ row.working_height }}</td>
                      <td><input v-model="row.recommended_weight" :disabled="!isEditMode" class="cell-in" /></td>
                      <td><input v-model="row.current_weight" :disabled="!isEditMode" class="cell-in" /></td>
                      <template v-for="task in tasks" :key="`c3-${row.key}-${task.id}`">
                        <td class="tc"><input type="radio" :name="`s3-${row.key}-${task.id}`" :checked="rowAnswer(row, task.id) === true" :disabled="!isEditMode" @change="setRowAnswer(row, task.id, true)" /></td>
                        <td class="tc"><input type="radio" :name="`s3-${row.key}-${task.id}`" :checked="rowAnswer(row, task.id) === false" :disabled="!isEditMode" @change="setRowAnswer(row, task.id, false)" /></td>
                      </template>
                      <td><input v-model="row.remarks" :disabled="!isEditMode" class="cell-in" /></td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <img src="/images/Seated Position.png" class="ref-img" alt="Seated Position" />

              <div class="table-wrap">
                <table class="table">
                  <thead>
                    <tr>
                      <th colspan="3">Summary of Carrying Activity</th>
                      <th :colspan="tasks.length">Exceed limit (Y/N)</th>
                      <th rowspan="2">Remarks</th>
                    </tr>
                    <tr>
                      <th>Factor</th>
                      <th>Condition</th>
                      <th>Outcome</th>
                      <th v-for="task in tasks" :key="`h3-car-${task.id}`">
                        <div>{{ task.title }}</div>
                        <label class="tiny">
                          <input
                            type="checkbox"
                            :checked="isTaskNA(step3.carrying_summary, task.id)"
                            :disabled="!isEditMode"
                            @change="setTaskNA(step3.carrying_summary, task.id, $event.target.checked)"
                          />
                          NA
                        </label>
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="row in step3.carrying_summary.rows" :key="`car-${row.key}`">
                      <td>{{ row.factor }}</td>
                      <td>{{ row.condition }}</td>
                      <td>{{ row.outcome }}</td>
                      <td v-for="task in tasks" :key="`c3-car-${row.key}-${task.id}`">
                        <label class="tiny">
                          <input
                            type="radio"
                            :name="`s3-car-${row.key}-${task.id}`"
                            :checked="rowAnswer(row, task.id) === true"
                            :disabled="!isEditMode || isTaskNA(step3.carrying_summary, task.id)"
                            @change="setRowAnswer(row, task.id, true, step3.carrying_summary)"
                          />
                          Yes
                        </label>
                        <label class="tiny">
                          <input
                            type="radio"
                            :name="`s3-car-${row.key}-${task.id}`"
                            :checked="rowAnswer(row, task.id) === false"
                            :disabled="!isEditMode || isTaskNA(step3.carrying_summary, task.id)"
                            @change="setRowAnswer(row, task.id, false, step3.carrying_summary)"
                          />
                          No
                        </label>
                      </td>
                      <td><input v-model="row.remarks" :disabled="!isEditMode" class="cell-in" /></td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <div class="table-wrap">
                <table class="table">
                  <thead>
                    <tr>
                      <th colspan="2">Summary of Forceful Exertion (Manual Handling)</th>
                      <th :colspan="tasks.length">Exceed limit (Y/N)</th>
                      <th rowspan="2">Remarks</th>
                    </tr>
                    <tr>
                      <th>Activity</th>
                      <th>Recommended weight</th>
                      <th v-for="task in tasks" :key="`h3-man-${task.id}`">
                        <div>{{ task.title }}</div>
                        <label class="tiny">
                          <input
                            type="checkbox"
                            :checked="isTaskNA(step3.manual_summary, task.id)"
                            :disabled="!isEditMode"
                            @change="setTaskNA(step3.manual_summary, task.id, $event.target.checked)"
                          />
                          NA
                        </label>
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="row in step3.manual_summary.rows" :key="`man-${row.key}`">
                      <td>{{ row.activity }}</td>
                      <td>{{ row.recommended_weight }}</td>
                      <td v-for="task in tasks" :key="`c3-man-${row.key}-${task.id}`">
                        <label class="tiny">
                          <input
                            type="radio"
                            :name="`s3-man-${row.key}-${task.id}`"
                            :checked="rowAnswer(row, task.id) === true"
                            :disabled="!isEditMode || isTaskNA(step3.manual_summary, task.id)"
                            @change="setRowAnswer(row, task.id, true, step3.manual_summary)"
                          />
                          Yes
                        </label>
                        <label class="tiny">
                          <input
                            type="radio"
                            :name="`s3-man-${row.key}-${task.id}`"
                            :checked="rowAnswer(row, task.id) === false"
                            :disabled="!isEditMode || isTaskNA(step3.manual_summary, task.id)"
                            @change="setRowAnswer(row, task.id, false, step3.manual_summary)"
                          />
                          No
                        </label>
                      </td>
                      <td><input v-model="row.remarks" :disabled="!isEditMode" class="cell-in" /></td>
                    </tr>
                    <tr>
                      <td colspan="2"><strong>Sub Total (Number of tick(s))</strong></td>
                      <td v-for="task in tasks" :key="`c3-man-sub-${task.id}`"><strong>{{ manualSummaryYesCount(task.id) }}</strong></td>
                      <td></td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Step 4 -->
            <div class="step-section">
              <div class="step-label"><span class="step-num">4</span>Repetitive Motion</div>
              <div class="table-wrap">
                <table class="table">
                  <thead>
                    <tr>
                      <th rowspan="2">Body Part</th><th rowspan="2">Risk Factor</th><th rowspan="2">Max Exposure</th>
                      <th v-for="task in tasks" :key="`h4-${task.id}`" colspan="2">
                        <div>{{ task.title }}</div>
                        <label class="tiny"><input type="checkbox" :checked="isTaskNA(step4, task.id)" :disabled="!isEditMode" @change="setTaskNA(step4, task.id, $event.target.checked)" /> NA</label>
                      </th>
                      <th rowspan="2">Remarks</th>
                    </tr>
                    <tr><template v-for="task in tasks" :key="`h4s-${task.id}`"><th>Yes</th><th>No</th></template></tr>
                  </thead>
                  <tbody>
                    <tr v-for="row in step4.rows" :key="row.key">
                      <td>{{ row.body_part }}</td><td>{{ row.physical_risk_factor }}</td><td>{{ row.max_exposure_duration }}</td>
                      <template v-for="task in tasks" :key="`c4-${row.key}-${task.id}`">
                        <td class="tc"><input type="radio" :name="`s4-${row.key}-${task.id}`" :checked="rowAnswer(row, task.id) === true" :disabled="!isEditMode || isTaskNA(step4, task.id)" @change="setRowAnswer(row, task.id, true, step4)" /></td>
                        <td class="tc"><input type="radio" :name="`s4-${row.key}-${task.id}`" :checked="rowAnswer(row, task.id) === false" :disabled="!isEditMode || isTaskNA(step4, task.id)" @change="setRowAnswer(row, task.id, false, step4)" /></td>
                      </template>
                      <td><input v-model="row.remarks" :disabled="!isEditMode" class="cell-in" /></td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Step 5 -->
            <div class="step-section">
              <div class="step-label"><span class="step-num">5</span>Vibration</div>
              <div class="table-wrap">
                <table class="table">
                  <thead>
                    <tr>
                      <th rowspan="2">Body Part</th><th rowspan="2">Risk Factor</th><th rowspan="2">Max Exposure</th>
                      <th v-for="task in tasks" :key="`h5-${task.id}`" colspan="2">
                        <div>{{ task.title }}</div>
                        <label class="tiny"><input type="checkbox" :checked="isTaskNA(step5, task.id)" :disabled="!isEditMode" @change="setTaskNA(step5, task.id, $event.target.checked)" /> NA</label>
                      </th>
                      <th rowspan="2">Remarks</th>
                    </tr>
                    <tr><template v-for="task in tasks" :key="`h5s-${task.id}`"><th>Yes</th><th>No</th></template></tr>
                  </thead>
                  <tbody>
                    <tr v-for="row in step5.rows" :key="row.key">
                      <td>{{ row.body_part }}</td><td>{{ row.physical_risk_factor }}</td><td>{{ row.max_exposure_duration }}</td>
                      <template v-for="task in tasks" :key="`c5-${row.key}-${task.id}`">
                        <td class="tc"><input type="radio" :name="`s5-${row.key}-${task.id}`" :checked="rowAnswer(row, task.id) === true" :disabled="!isEditMode || isTaskNA(step5, task.id)" @change="setRowAnswer(row, task.id, true, step5)" /></td>
                        <td class="tc"><input type="radio" :name="`s5-${row.key}-${task.id}`" :checked="rowAnswer(row, task.id) === false" :disabled="!isEditMode || isTaskNA(step5, task.id)" @change="setRowAnswer(row, task.id, false, step5)" /></td>
                      </template>
                      <td><input v-model="row.remarks" :disabled="!isEditMode" class="cell-in" /></td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Step 6 -->
            <div class="step-section">
              <div class="step-label"><span class="step-num">6</span>Environmental Factors</div>
              <div class="table-wrap">
                <table class="table">
                  <thead>
                    <tr>
                      <th rowspan="2">Risk Factor</th>
                      <th v-for="task in tasks" :key="`h6-${task.id}`" colspan="2">
                        <div>{{ task.title }}</div>
                        <label class="tiny"><input type="checkbox" :checked="isTaskNA(step6, task.id)" :disabled="!isEditMode" @change="setTaskNA(step6, task.id, $event.target.checked)" /> NA</label>
                      </th>
                      <th rowspan="2">Remarks</th>
                    </tr>
                    <tr><template v-for="task in tasks" :key="`h6s-${task.id}`"><th>Yes</th><th>No</th></template></tr>
                  </thead>
                  <tbody>
                    <tr v-for="row in step6.rows" :key="row.key">
                      <td>{{ row.physical_risk_factor }}</td>
                      <template v-for="task in tasks" :key="`c6-${row.key}-${task.id}`">
                        <td class="tc"><input type="radio" :name="`s6-${row.key}-${task.id}`" :checked="rowAnswer(row, task.id) === true" :disabled="!isEditMode || isTaskNA(step6, task.id)" @change="setRowAnswer(row, task.id, true, step6)" /></td>
                        <td class="tc"><input type="radio" :name="`s6-${row.key}-${task.id}`" :checked="rowAnswer(row, task.id) === false" :disabled="!isEditMode || isTaskNA(step6, task.id)" @change="setRowAnswer(row, task.id, false, step6)" /></td>
                      </template>
                      <td><input v-model="row.remarks" :disabled="!isEditMode" class="cell-in" /></td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Step 7 -->
            <div class="step-section step7-section">
              <div class="step-label"><span class="step-num">7</span>Initial ERA Summary</div>

              <div class="step7-stack">
                <section v-for="group in step7TaskGroups" :key="`s7-group-${group.index}`" class="step7-summary-section">
                  <div class="table-wrap step7-table-wrap">
                    <table class="table step7-table">
                      <thead>
                        <tr>
                          <th colspan="3" class="fixed-head-spacer"></th>
                          <template v-for="(task, taskIndex) in group.tasks" :key="`s7-task-head-${group.index}-${task.id}`">
                            <th colspan="3" :class="step7HeaderClassForTask((group.start - 1) + taskIndex)">{{ task.title }}</th>
                          </template>
                        </tr>
                        <tr>
                          <th class="letter-head">A</th>
                          <th class="letter-head">B</th>
                          <th class="letter-head">C</th>
                          <template v-for="task in group.tasks" :key="`s7-letter-${group.index}-${task.id}`">
                            <th class="letter-head">D</th>
                            <th class="letter-head">E</th>
                            <th class="letter-head">F</th>
                          </template>
                        </tr>
                        <tr>
                          <th class="col-risk">Risk Factor</th>
                          <th class="col-total">Total score</th>
                          <th class="col-threshold">Minimum Requirements for Advance ERA</th>
                          <template v-for="task in group.tasks" :key="`s7-subhead-${group.index}-${task.id}`">
                            <th>Result of Initial ERA</th>
                            <th>Any pain or discomfort due to risk factors as found in MSD assessment Refer Part 3.1 (Yes/No)</th>
                            <th>Need advanced ERA (Yes/No)</th>
                          </template>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="(row, rowIndex) in step7SummaryRows" :key="`s7-row-${group.index}-${row.key}`">
                          <td class="risk-name">{{ row.label }}</td>
                          <td class="total-cell">{{ row.totalScore }}</td>
                          <td class="threshold-cell">>= {{ row.threshold }}</td>

                          <template v-for="task in group.tasks" :key="`s7-cell-${group.index}-${row.key}-${task.id}`">
                            <td class="score-cell">
                              <div class="score-main">{{ getStep7TaskResult(row, task.id).score }}</div>
                              <div v-if="row.key === 'forceful_exertion' && getStep7TaskResult(row, task.id).details" class="score-breakdown">
                                <div
                                  v-for="(value, label) in getStep7TaskResult(row, task.id).details"
                                  :key="`s7-breakdown-${task.id}-${row.key}-${label}`"
                                  class="score-line"
                                >
                                  <span>{{ label }}</span>
                                  <strong>{{ value }}</strong>
                                </div>
                              </div>
                            </td>

                            <td v-if="rowIndex === 0" class="pain-merged-cell" :rowspan="step7SummaryRows.length">
                              <div class="pain-merged-title">If YES please tick which part of body</div>
                              <table class="pain-grid">
                                <tbody>
                                  <tr v-for="part in BODY_PART_OPTIONS" :key="`s7-pain-${task.id}-${part}`">
                                    <td class="pain-grid-part">{{ part }}</td>
                                    <td class="pain-grid-mark">
                                      <button
                                        type="button"
                                        class="pain-mark-btn"
                                        :class="{ 'pain-mark-btn-checked': painChecked(task.id, part) }"
                                        :disabled="!isEditMode"
                                        @click="setPain(task.id, part, !painChecked(task.id, part))"
                                      >
                                        {{ painChecked(task.id, part) ? 'X' : '' }}
                                      </button>
                                    </td>
                                  </tr>
                                </tbody>
                              </table>
                            </td>

                            <td class="verdict-cell">
                              <span class="verdict-badge" :class="step7BadgeClass(getStep7TaskResult(row, task.id).needAdvanced)">
                                {{ getStep7TaskResult(row, task.id).needAdvanced ? 'YES' : 'NO' }}
                              </span>
                            </td>
                          </template>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </section>
              </div>
            </div>

          </div>
        </template>
      </aside>
    </div>

    <!-- STATUS BAR -->
    <div class="status-bar">
      <span v-if="selectedMeta">Assessment #{{ selectedMeta.id }}</span>
      <span v-else>Assessment Details</span>
      <span v-if="selectedMeta">{{ selectedMeta.assessor_name }} - {{ selectedMeta.department }}</span>
    </div>
  </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&display=swap');

* { box-sizing: border-box; }

.explorer-root {
  font-family: 'IBM Plex Sans', sans-serif;
  display: flex;
  flex-direction: column;
  height: calc(100vh - 120px);
  background: #f0f2f5;
  border-radius: 10px;
  overflow: hidden;
  border: 1px solid #d0d5dd;
  box-shadow: 0 2px 12px rgba(0,0,0,0.08);
}

/* ── TOOLBAR ── */
.toolbar {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 8px 14px;
  background: #ffffff;
  border-bottom: 1px solid #e0e4ea;
  flex-shrink: 0;
}
.toolbar-left { flex: 1; }
.toolbar-right { display: flex; align-items: center; gap: 10px; }
.toolbar-right-single { margin-left: auto; }

.address-bar {
  display: flex;
  align-items: center;
  gap: 5px;
  background: #f3f5f8;
  border: 1px solid #dde1e8;
  border-radius: 6px;
  padding: 5px 10px;
  font-size: 12.5px;
  color: #5a6578;
}
.addr-sep { color: #b0b8c4; }
.addr-part.active { color: #2f3e4d; font-weight: 600; }
.close-page-btn {
  border: 1px solid #d0d5dd;
  border-radius: 6px;
  background: #fff;
  color: #2f3e4d;
  font-size: 12px;
  font-weight: 600;
  font-family: inherit;
  padding: 6px 12px;
  cursor: pointer;
}
.close-page-btn:hover { background: #f4f6f8; }

.search-box {
  display: flex;
  align-items: center;
  gap: 7px;
  background: #f3f5f8;
  border: 1px solid #dde1e8;
  border-radius: 6px;
  padding: 5px 10px;
  color: #8a93a0;
}
.search-input {
  border: none;
  background: transparent;
  outline: none;
  font-size: 12.5px;
  font-family: inherit;
  width: 100%;
  color: #2f3e4d;
}
.search-input::placeholder { color: #9aa3ae; }

.view-toggle {
  display: flex;
  background: #f0f2f5;
  border: 1px solid #dde1e8;
  border-radius: 6px;
  overflow: hidden;
}
.view-btn {
  padding: 5px 9px;
  border: none;
  background: transparent;
  cursor: pointer;
  color: #8a95a4;
  display: flex;
  align-items: center;
  transition: all 0.15s;
}
.view-btn.active { background: #2f3e4d; color: #fff; }
.view-btn:hover:not(.active) { background: #e4e8ee; }

.file-count { font-size: 12px; color: #8a95a4; white-space: nowrap; }

/* ── BODY ── */
.explorer-body {
  display: flex;
  flex: 1;
  overflow: hidden;
  transition: all 0.3s ease;
}

.file-area {
  flex: 1;
  overflow-y: auto;
  padding: 16px;
  min-width: 0;
}
.file-area.total-hidden { display: none; }

/* ── EMPTY / LOADING ── */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  height: 100%;
  min-height: 200px;
  color: #9aa3ae;
  font-size: 13px;
}
.error-state { color: #c0392b; }

.spinner {
  width: 28px;
  height: 28px;
  border: 3px solid #e0e4ea;
  border-top-color: #3b7dd8;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── GRID VIEW ── */
.grid-view {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
  gap: 6px;
  align-content: start;
}

.file-icon-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 12px 8px 10px;
  background: transparent;
  border: 2px solid transparent;
  border-radius: 8px;
  cursor: pointer;
  transition: none;
  text-align: center;
  font-family: inherit;
}
.file-icon-card:hover {
  background: rgba(59, 125, 216, 0.08);
  border-color: rgba(59, 125, 216, 0.2);
}
.file-icon-card.selected {
  background: rgba(59, 125, 216, 0.12);
  border-color: #3b7dd8;
}

.file-icon {
  position: relative;
  width: 60px;
  height: 72px;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 3px 10px rgba(0,0,0,0.18);
  flex-shrink: 0;
  overflow: hidden;
}
.doc-bg {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
}
.file-initials {
  position: relative;
  z-index: 1;
  color: #fff;
  font-size: 15px;
  font-weight: 700;
  letter-spacing: 0.5px;
  text-shadow: 0 1px 3px rgba(0,0,0,0.3);
  margin-top: 8px;
}
.era-badge {
  position: absolute;
  bottom: 4px;
  right: 4px;
  background: rgba(0,0,0,0.35);
  color: #fff;
  font-size: 8px;
  font-weight: 700;
  padding: 1px 4px;
  border-radius: 3px;
  letter-spacing: 0.5px;
}

.file-name {
  font-size: 11.5px;
  font-weight: 600;
  color: #2f3e4d;
  line-height: 1.3;
  word-break: break-word;
  max-width: 100%;
}
.file-meta { font-size: 10.5px; color: #8a95a4; }
.file-dept { font-size: 10px; color: #aab0b8; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }

/* ── LIST VIEW ── */
.list-view {
  display: flex;
  flex-direction: column;
  gap: 1px;
}
.list-header {
  display: grid;
  grid-template-columns: 1fr 160px 200px 60px;
  padding: 6px 12px;
  font-size: 11.5px;
  font-weight: 700;
  color: #7a8694;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  background: #e8ebef;
  border-radius: 6px;
  margin-bottom: 4px;
}
.list-row {
  display: grid;
  grid-template-columns: auto 1fr 160px 200px 60px;
  gap: 0 10px;
  align-items: center;
  padding: 7px 12px;
  background: #fff;
  border: 1px solid #eaecf0;
  border-radius: 6px;
  cursor: pointer;
  font-family: inherit;
  font-size: 12.5px;
  color: #2f3e4d;
  transition: all 0.12s;
  text-align: left;
}
.list-row:hover { background: #f0f6ff; border-color: #c5d9f5; }
.list-row.selected { background: #e8f0fd; border-color: #3b7dd8; }

.lr-icon {
  width: 28px; height: 28px;
  border-radius: 5px;
  display: flex; align-items: center; justify-content: center;
  color: #fff; font-size: 10px; font-weight: 700;
  flex-shrink: 0;
}
.lr-name { font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.lr-date, .lr-dept { color: #6e7d8e; font-size: 12px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.lr-id { color: #a0abb6; font-size: 11.5px; }
.lh-name,.lh-date,.lh-dept,.lh-id { overflow: hidden; }

/* ── DETAIL PANEL ── */
.detail-panel {
  width: 0;
  overflow: hidden;
  border-left: 0 solid #dde1e8;
  background: #fff;
  display: flex;
  flex-direction: column;
  transition: width 0.28s cubic-bezier(0.4,0,0.2,1), border-left-width 0.28s;
  flex-shrink: 0;
}
.detail-panel.open {
  width: 100%;
  border-left-width: 0;
}

.detail-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 10px;
  height: 100%;
  color: #b0bac5;
  font-size: 13px;
}

/* Panel Header */
.panel-header {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  border-bottom: 1px solid #eaecf0;
  background: #f8fafc;
  flex-shrink: 0;
}
.panel-file-icon {
  width: 40px; height: 40px;
  border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  color: #fff; font-size: 14px; font-weight: 700;
  flex-shrink: 0;
  box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}
.panel-title-block { flex: 1; min-width: 0; }
.panel-title { font-size: 14px; font-weight: 700; color: #1e2a36; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.panel-subtitle { font-size: 11.5px; color: #8a95a4; }
.close-btn {
  width: 28px; height: 28px;
  border: 1px solid #dde1e8; border-radius: 6px;
  background: #fff; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  color: #7a8694; transition: all 0.12s;
  flex-shrink: 0;
}
.close-btn:hover { background: #f0f2f5; color: #2f3e4d; }

/* Panel Actions */
.panel-actions {
  display: flex;
  gap: 6px;
  padding: 10px 16px;
  border-bottom: 1px solid #eaecf0;
  flex-shrink: 0;
}
.pa-btn {
  display: flex; align-items: center; gap: 5px;
  padding: 6px 12px;
  font-size: 12px; font-weight: 600; font-family: inherit;
  border: 1px solid #d0d5dd; border-radius: 5px;
  background: #fff; color: #4a5568; cursor: pointer;
  transition: all 0.12s;
}
.pa-btn:hover { background: #f4f6f8; }
.pa-btn.pa-active { background: #2f3e4d; border-color: #2f3e4d; color: #fff; }
.pa-btn.pa-save { background: #2a7a52; border-color: #2a7a52; color: #fff; margin-left: auto; }
.pa-btn.pa-save:disabled { opacity: 0.6; cursor: not-allowed; }

.feedback-bar {
  padding: 8px 16px;
  font-size: 12px;
  font-weight: 600;
  flex-shrink: 0;
}
.feedback-bar.success { background: #ecfdf5; color: #065f46; border-bottom: 1px solid #a7f3d0; }
.feedback-bar.error { background: #fff5f5; color: #b91c1c; border-bottom: 1px solid #fecaca; }

.panel-loading {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  gap: 10px; flex: 1; color: #8a95a4; font-size: 13px;
}

/* Panel scrollable content */
.panel-content {
  flex: 1;
  overflow-y: auto;
  padding: 14px 16px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

/* Step sections */
.step-section {
  display: flex;
  flex-direction: column;
  gap: 10px;
  background: #ffffff;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  padding: 12px;
}
.step-label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  font-weight: 700;
  color: #111827;
  padding-bottom: 8px;
  border-bottom: 1px solid #d1d5db;
  letter-spacing: 0.02em;
  text-transform: uppercase;
}
.step-num {
  width: 22px; height: 22px;
  background: #111827; color: #fff;
  border-radius: 4px;
  display: inline-flex; align-items: center; justify-content: center;
  font-size: 11px; font-weight: 700;
  flex-shrink: 0;
}

.info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}
.info-item { display: flex; flex-direction: column; gap: 3px; }
.info-item.full { grid-column: 1/-1; }
.info-key { font-size: 10.5px; font-weight: 700; color: #8a95a4; text-transform: uppercase; letter-spacing: 0.06em; }
.info-val {
  padding: 6px 8px;
  font-size: 12.5px; font-family: inherit;
  border: 1px solid #e0e4ea; border-radius: 5px;
  background: #f8fafc; color: #1e2a36;
  outline: none; width: 100%;
}
.info-val:disabled { background: #f3f5f7; color: #5a6578; border-color: transparent; }
.info-val.editable { background: #fff; border-color: #94b3e8; }
.info-val.editable:focus { border-color: #3b7dd8; box-shadow: 0 0 0 3px rgba(59,125,216,0.1); }

.step1-section {
  border: 1px solid #9ca3af;
  background: #f9fafb;
}
.step1-title {
  text-align: center;
  font-size: 22px;
  font-weight: 800;
  text-decoration: underline;
  text-transform: uppercase;
  color: #111827;
  margin-bottom: 4px;
}
.step1-meta-wrap { border-color: #6b7280; }
.step1-meta-table { min-width: 780px; }
.step1-meta-table th {
  width: 140px;
  background: #e5e7eb;
  color: #111827;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.step1-meta-table td {
  background: #ffffff;
}
.step1-meta-input {
  font-weight: 600;
  min-height: 24px;
}
.step1-date-edit {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}
.step1-date-field {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.step1-date-label {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #6b7280;
}
.step1-date-input {
  border: 1px solid #cbd5e1;
  border-radius: 4px;
  background: #ffffff;
  padding: 4px 6px;
}
.step1-summary-edit {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}
.step1-summary-field {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.step1-summary-field label {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #6b7280;
}
.step1-process-table .col-no { width: 58px; }
.step1-process-table .col-process { width: 170px; }
.step1-process-table .col-task { width: 240px; }
.step1-process-table .col-worker { width: auto; }
.step1-process-table .process-cell {
  font-weight: 700;
  color: #111827;
  vertical-align: top;
}
.step1-task-desc {
  border-top: 1px dashed #cbd5e1;
  margin-top: 4px;
  padding-top: 4px;
}
.step1-bullet-list {
  margin: 0;
  padding-left: 18px;
  line-height: 1.45;
}
.step1-bullet-list.compact {
  padding-left: 16px;
  margin-top: 2px;
}
.step1-bullet-list li { margin: 1px 0; }
.step1-photos-block {
  border: 1px solid #9ca3af;
  background: #ffffff;
  border-radius: 6px;
  overflow: hidden;
}
.step1-photos-title {
  padding: 8px 10px;
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  border-bottom: 1px solid #d1d5db;
  background: #f3f4f6;
}
.step1-photo-groups {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 12px;
}
.step1-photo-group {
  border: 1px solid #d1d5db;
  border-radius: 6px;
  overflow: hidden;
  background: #fff;
}
.step1-photo-controls {
  display: flex;
  justify-content: flex-end;
  padding: 8px;
  border-bottom: 1px solid #e5e7eb;
  background: #f8fafc;
}
.step1-photo-file-input {
  display: none;
}
.step1-photo-add-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid #9ca3af;
  background: #fff;
  color: #374151;
  border-radius: 999px;
  padding: 3px 10px;
  font-size: 11px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.12s;
}
.step1-photo-add-btn:hover {
  border-color: #2563eb;
  color: #2563eb;
  background: #eff6ff;
}
.step1-photo-strip {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  align-items: flex-start;
  gap: 8px;
  padding: 8px;
  background: #f3f4f6;
}
.step1-photo-link {
  display: inline-flex;
  align-items: flex-start;
  justify-content: center;
  max-width: 100%;
  text-decoration: none;
}
.step1-photo-item {
  position: relative;
  display: inline-flex;
}
.step1-photo-edit-item {
  position: relative;
  display: inline-flex;
}
.step1-photo-thumb {
  width: auto;
  height: auto;
  max-width: min(100%, 1200px);
  max-height: none;
  object-fit: contain;
  image-rendering: auto;
  display: block;
  border: 1px solid #cbd5e1;
  background: #fff;
}
.step1-photo-remove-btn {
  position: absolute;
  top: -6px;
  right: -6px;
  width: 18px;
  height: 18px;
  border: 1px solid #ffffff;
  border-radius: 999px;
  background: rgba(17, 24, 39, 0.9);
  color: #fff;
  font-size: 14px;
  line-height: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: background 0.12s;
}
.step1-photo-remove-btn:hover {
  background: #dc2626;
}
.step1-photo-caption {
  padding: 8px 10px;
  font-size: 13px;
  font-weight: 700;
  text-align: center;
}

.process-block { display: flex; flex-direction: column; gap: 4px; }
.process-name { font-size: 12px; font-weight: 700; color: #4a5568; }

.table-wrap { overflow-x: auto; border: 1px solid #9ca3af; border-radius: 6px; background: #fff; }
.table { width: 100%; min-width: 960px; border-collapse: collapse; font-size: 12px; }
.table th, .table td { border: 1px solid #9ca3af; padding: 6px 8px; vertical-align: middle; }
.table th { background: #111827; font-weight: 700; color: #ffffff; font-size: 11.5px; }
.table tr:hover td { background: #f8faff; }
.tc { text-align: center; }

.step7-section {
  background: #ffffff;
}
.step7-stack {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.step7-summary-section {
  margin: 0;
}
.step7-table-wrap {
  border: 1px solid #6b7280;
}
.step7-table {
  min-width: 860px;
  table-layout: fixed;
  font-size: 11px;
}
.step7-table th,
.step7-table td {
  border: 1px solid #2b313a;
  padding: 4px 5px;
  vertical-align: top;
  word-break: break-word;
}
.step7-table th {
  background: #f3f4f6;
  color: #111827;
  text-transform: none;
  letter-spacing: 0;
  font-size: 10.5px;
}
.step7-table .fixed-head-spacer {
  background: #f3f4f6;
}
.step7-table .task-head-a {
  background: #dc2626 !important;
  color: #fff !important;
  font-size: 13px;
}
.step7-table .task-head-b {
  background: #0b67ad !important;
  color: #fff !important;
  font-size: 13px;
}
.step7-table .letter-head {
  font-weight: 800;
  font-size: 11px;
  text-align: center;
}
.step7-table .col-risk { width: 140px; }
.step7-table .col-total { width: 56px; text-align: center; }
.step7-table .col-threshold { width: 94px; text-align: center; }
.step7-table .risk-name {
  font-weight: 700;
  background: #fafafa;
}
.step7-table .total-cell,
.step7-table .threshold-cell {
  text-align: center;
  font-weight: 700;
}
.step7-table .score-cell {
  text-align: center;
}
.step7-table .score-main {
  font-size: 14px;
  line-height: 1.1;
  font-weight: 700;
}
.step7-table .score-breakdown {
  margin-top: 5px;
  border: 1px solid #d1d5db;
  border-radius: 4px;
  overflow: hidden;
  text-align: left;
  background: #fff;
}
.step7-table .score-line {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 6px;
  padding: 2px 4px;
  font-size: 10px;
  border-bottom: 1px solid #e5e7eb;
}
.step7-table .score-line:last-child {
  border-bottom: 0;
}
.step7-table .pain-merged-cell {
  background: #f7f2de;
  width: 170px;
  min-width: 170px;
}
.step7-table .pain-merged-title {
  font-size: 10px;
  font-weight: 700;
  line-height: 1.3;
  margin-bottom: 4px;
}
.step7-table .pain-grid {
  width: 100%;
  border-collapse: collapse;
  table-layout: fixed;
  background: #fff;
}
.step7-table .pain-grid td {
  border: 1px solid #2b313a;
  padding: 1px 3px;
  font-size: 10px;
}
.step7-table .pain-grid-mark {
  width: 20px;
  padding: 0 !important;
}
.step7-table .pain-mark-btn {
  width: 100%;
  min-height: 16px;
  border: 0;
  background: transparent;
  font-weight: 800;
  font-size: 11px;
  cursor: pointer;
}
.step7-table .pain-mark-btn:disabled {
  cursor: default;
}
.step7-table .pain-mark-btn-checked {
  background: #edf2f7;
}
.step7-table .verdict-cell {
  text-align: center;
}
.step7-table .verdict-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 44px;
  padding: 2px 7px;
  border-radius: 4px;
  color: #fff;
  font-weight: 800;
  font-size: 10px;
}
.step7-table .badge-no {
  background: #06b34f;
}
.step7-table .badge-yes {
  background: #dc2626;
}

@media (max-width: 900px) {
  .step1-date-edit,
  .step1-summary-edit {
    grid-template-columns: 1fr;
  }
}

.cell-in {
  width: 100%; border: none; background: transparent;
  font-family: inherit; font-size: 12px; outline: none;
  padding: 2px 4px; color: #1e2a36;
}
.cell-in:focus { background: #f0f6ff; border-radius: 3px; }
.cell-in:disabled { color: #5a6578; }
.cell-in.ta { min-height: 48px; resize: vertical; }

.tiny { display: inline-flex; align-items: center; gap: 4px; font-size: 10.5px; font-weight: 500; }

.ref-img { width: 100%; border: 1px solid #e0e4ea; border-radius: 6px; display: block; }

/* ── STATUS BAR ── */
.status-bar {
  display: flex;
  justify-content: space-between;
  padding: 5px 14px;
  background: #e8ebef;
  border-top: 1px solid #d0d5dd;
  font-size: 11.5px;
  color: #7a8694;
  flex-shrink: 0;
}

/* Scrollbar styling */
.file-area::-webkit-scrollbar,
.panel-content::-webkit-scrollbar { width: 6px; }
.file-area::-webkit-scrollbar-track,
.panel-content::-webkit-scrollbar-track { background: transparent; }
.file-area::-webkit-scrollbar-thumb,
.panel-content::-webkit-scrollbar-thumb { background: #c8cdd5; border-radius: 3px; }
</style>

