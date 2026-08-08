const STORAGE_KEY = 'era_step_progress'
const CURRENT_ASSESSMENT_KEY = 'era_assessment_id'
const BOSS_ID_KEY = 'boss_questionnaire_id'

const STEP_KEYS = ['step1', 'step2', 'step3', 'step4', 'step5', 'step6', 'step7']

const defaultProgress = () => ({
  step1: false,
  step2: false,
  step3: false,
  step4: false,
  step5: false,
  step6: false,
  step7: false,
})

const safeParse = value => {
  if (!value) return {}

  try {
    const parsed = JSON.parse(value)
    return parsed && typeof parsed === 'object' ? parsed : {}
  } catch {
    return {}
  }
}

const sanitizeProgress = raw => {
  const out = defaultProgress()
  STEP_KEYS.forEach(stepKey => {
    if (typeof raw?.[stepKey] === 'boolean') {
      out[stepKey] = raw[stepKey]
    }
  })
  return out
}

const readStore = () => safeParse(localStorage.getItem(STORAGE_KEY))

const writeStore = store => {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(store))
}

const resetStoredProgress = assessmentId => {
  if (!assessmentId) return

  const id = String(assessmentId)
  const store = readStore()
  store[id] = defaultProgress()
  writeStore(store)
}

export const getCurrentAssessmentId = () => localStorage.getItem(CURRENT_ASSESSMENT_KEY) ?? ''

export const setCurrentAssessmentId = assessmentId => {
  if (!assessmentId) return
  localStorage.setItem(CURRENT_ASSESSMENT_KEY, String(assessmentId))
}

export const clearCurrentAssessmentId = () => {
  localStorage.removeItem(CURRENT_ASSESSMENT_KEY)
}

export const getAssessmentProgress = assessmentId => {
  if (!assessmentId) return defaultProgress()
  const store = readStore()
  return sanitizeProgress(store[String(assessmentId)])
}

export const markStepCompleted = (assessmentId, stepNumber) => {
  if (!assessmentId || stepNumber < 1 || stepNumber > 7) return

  const stepKey = `step${stepNumber}`
  const id = String(assessmentId)
  const store = readStore()
  const current = sanitizeProgress(store[id])

  current[stepKey] = true
  store[id] = current

  writeStore(store)
  setCurrentAssessmentId(id)
  window.dispatchEvent(new Event('era-progress-updated'))
}

export const resetAssessmentProgress = (assessmentId, { emitEvent = true } = {}) => {
  resetStoredProgress(assessmentId)
  if (emitEvent) {
    window.dispatchEvent(new Event('era-progress-updated'))
  }
}

export const getBossQuestionnaireId = () => localStorage.getItem(BOSS_ID_KEY) ?? ''

export const setBossQuestionnaireId = id => {
  if (!id) return
  localStorage.setItem(BOSS_ID_KEY, String(id))
  window.dispatchEvent(new Event('era-progress-updated'))
}

export const clearBossQuestionnaireId = () => {
  localStorage.removeItem(BOSS_ID_KEY)
}

export const resetEraProgress = (assessmentId = '', { emitEvent = true } = {}) => {
  resetStoredProgress(assessmentId)
  clearCurrentAssessmentId()
  clearBossQuestionnaireId()
  if (emitEvent) {
    window.dispatchEvent(new Event('era-progress-updated'))
  }
}
