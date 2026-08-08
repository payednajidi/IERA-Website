<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import api from '../services/api'
import { setBossQuestionnaireId, clearBossQuestionnaireId } from '../services/eraProgress'
import { saveBossEraTransfer, clearBossEraTransfer, flagBossToEraRedirect, saveBossGroupId, getBossGroupId, clearBossGroupId } from '../services/bossData'

const router = useRouter()

// ── Constants ────────────────────────────────────────────────────────────────

const BODY_REGIONS = [
  { key: 'neck',       label: 'Neck',        letter: 'A' },
  { key: 'shoulder',   label: 'Shoulder',    letter: 'B' },
  { key: 'upper_back', label: 'Upper Back',  letter: 'C' },
  { key: 'lower_back', label: 'Lower Back',  letter: 'D' },
  { key: 'upper_arm',  label: 'Upper Arm',   letter: 'E' },
  { key: 'elbow',      label: 'Elbow',       letter: 'F' },
  { key: 'lower_arm',  label: 'Lower Arm',   letter: 'G' },
  { key: 'hand_wrist', label: 'Hand / Wrist', letter: 'H' },
  { key: 'thigh',      label: 'Thigh',       letter: 'I' },
  { key: 'knee',       label: 'Knee',        letter: 'J' },
  { key: 'lower_leg',  label: 'Lower Leg',   letter: 'K' },
  { key: 'ankle_foot', label: 'Ankle / Foot', letter: 'L' },
]

const SEVERITIES = [
  { key: 'no_very_mild', label: 'No / Very Mild Discomfort', color: 'sev-mild' },
  { key: 'discomfort',   label: 'Discomfort',                color: 'sev-mild' },
  { key: 'pain_able',    label: 'Pain (able to continue work)',    color: 'sev-pain' },
  { key: 'pain_unable',  label: 'Pain (unable to continue work)',  color: 'sev-pain' },
]

const FREQUENCIES = [
  { key: 'once_a_year',       short: '1×/yr',   full: 'Once a year' },
  { key: 'few_times_a_year',  short: 'Few/yr',  full: 'A few times a year' },
  { key: 'once_a_month',      short: '1×/mo',   full: 'Once a month' },
  { key: 'few_times_a_month', short: 'Few/mo',  full: 'A few times a month' },
  { key: 'once_a_week',       short: '1×/wk',   full: 'Once a week' },
  { key: 'few_times_a_week',  short: 'Few/wk',  full: 'A few times a week' },
  { key: 'everyday',          short: 'Daily',   full: 'Everyday' },
]

// Hotspot positions as percentages of the 1024×1024 body-diagram.png image.
// cx/cy = centre of each white badge box in the image.
// Each entry maps to a clickable overlay div positioned over the badge.
// cx/cy = centre of each white badge box as % of the 1024×1024 image.
// Calibrated precisely from the badge pixel coordinates in body_diagram.png.
const HOTSPOTS = [
  { regionKey: 'neck',       cx: 50,   cy: 14   },   // A
  { regionKey: 'shoulder',   cx: 39,   cy: 22   },   // B left
  { regionKey: 'shoulder',   cx: 59,   cy: 22   },   // B right
  { regionKey: 'upper_back', cx: 49.5, cy: 24.8 },   // C
  { regionKey: 'upper_arm',  cx: 34.5, cy: 30   },   // E left
  { regionKey: 'upper_arm',  cx: 65,   cy: 30   },   // E right
  { regionKey: 'lower_back', cx: 49.5, cy: 35.5 },   // D
  { regionKey: 'elbow',      cx: 30.5, cy: 37.5 },   // F left
  { regionKey: 'elbow',      cx: 68,   cy: 37.5 },   // F right
  { regionKey: 'lower_arm',  cx: 28,   cy: 44   },   // G left
  { regionKey: 'lower_arm',  cx: 71,   cy: 44   },   // G right
  { regionKey: 'hand_wrist', cx: 23,   cy: 50   },   // H left
  { regionKey: 'hand_wrist', cx: 75,   cy: 50   },   // H right
  { regionKey: 'thigh',      cx: 42.5, cy: 59   },   // I left
  { regionKey: 'thigh',      cx: 56,   cy: 59   },   // I right
  { regionKey: 'knee',       cx: 40.5, cy: 71   },   // J left
  { regionKey: 'knee',       cx: 58,   cy: 71   },   // J right
  { regionKey: 'lower_leg',  cx: 39.5, cy: 81.5 },   // K left
  { regionKey: 'lower_leg',  cx: 59,   cy: 81.6 },   // K right
  { regionKey: 'ankle_foot', cx: 34,   cy: 94   },   // L left
  { regionKey: 'ankle_foot', cx: 64,   cy: 94   },   // L right
]

// ── Reactive State ────────────────────────────────────────────────────────────

// ── Stage management ─────────────────────────────────────────────────────────
// 'form' = filling in | 'submitted' = post-submission options
const stage = ref('form')
const showRetainPanel = ref(false)   // shows the checkbox retention panel
const lastSubmitted = ref(null)      // snapshot of the last submission


// ── Retainable fields definition ─────────────────────────────────────────────
// Each entry describes one retainable piece of worker information.
const RETAIN_FIELDS = [
  { key: 'name',       label: 'Name' },
  { key: 'staff_id',   label: 'Staff ID' },
  { key: 'company',    label: 'Company' },
  { key: 'department', label: 'Department' },
  { key: 'process',    label: 'Process' },
  { key: 'job_task',   label: 'Job Task' },
  { key: 'date',       label: 'Date Range' },   // maps to date_start + date_end
]

// Default: personal & process info pre-selected; date/job_task not
const retainSelections = ref({
  name: true, staff_id: true, company: true, department: true,
  process: true, job_task: false, date: false,
})

const allRetainSelected = computed(() => Object.values(retainSelections.value).every(v => v))
const someRetainSelected = computed(() => Object.values(retainSelections.value).some(v => v))

const toggleSelectAll = (checked) => {
  Object.keys(retainSelections.value).forEach(k => { retainSelections.value[k] = checked })
}

// ── Header fields ────────────────────────────────────────────────────────────
const headerFields = ref({
  name: '', staff_id: '', date_start: '', date_end: '',
  department: '', company: '', process: '', job_task: '',
})

// Human-readable date range (e.g. "21–23 August 2026" or cross-month)
const formattedDateRange = computed(() => {
  const { date_start, date_end } = headerFields.value
  if (!date_start && !date_end) return ''
  const fmt = (iso, opts) => new Date(iso + 'T00:00:00').toLocaleDateString('en-GB', opts)
  if (date_start && date_end) {
    const sMonthYear = fmt(date_start, { month: 'long', year: 'numeric' })
    const eMonthYear = fmt(date_end,   { month: 'long', year: 'numeric' })
    if (sMonthYear === eMonthYear) {
      return `${fmt(date_start, { day: 'numeric' })}–${fmt(date_end, { day: 'numeric' })} ${eMonthYear}`
    }
    return `${fmt(date_start, { day: 'numeric', month: 'long', year: 'numeric' })} – ${fmt(date_end, { day: 'numeric', month: 'long', year: 'numeric' })}`
  }
  if (date_start) return fmt(date_start, { day: 'numeric', month: 'long', year: 'numeric' })
  return fmt(date_end, { day: 'numeric', month: 'long', year: 'numeric' })
})

const initRegionState = () =>
  Object.fromEntries(BODY_REGIONS.map(r => [r.key, { selected: false, due_to_work: null, responses: [] }]))

const regionState = ref(initRegionState())
const activeRegionKey = ref(null)
const isSubmitting = ref(false)

const activeRegion = computed(() => BODY_REGIONS.find(r => r.key === activeRegionKey.value) ?? null)

const selectedRegions = computed(() => BODY_REGIONS.filter(r => regionState.value[r.key].selected))

// ── Interactions ──────────────────────────────────────────────────────────────

// Clicking a badge only opens the panel — does not auto-mark as affected
const clickRegion = key => {
  activeRegionKey.value = key
}

const markAffected = key => {
  regionState.value[key].selected = true
}

const unmarkAffected = key => {
  regionState.value[key].selected = false
}

const deselectRegion = key => {
  regionState.value[key].selected = false
  if (activeRegionKey.value === key) activeRegionKey.value = null
}

const cellKey = (severity, frequency) => `${severity}|${frequency}`

const toggleCell = (regionKey, severity, frequency) => {
  const responses = regionState.value[regionKey].responses
  const k = cellKey(severity, frequency)
  const idx = responses.indexOf(k)
  if (idx !== -1) responses.splice(idx, 1)
  else responses.push(k)
}

const isCellChecked = (regionKey, severity, frequency) =>
  regionState.value[regionKey].responses.includes(cellKey(severity, frequency))

const setDueToWork = (regionKey, value) => {
  const cur = regionState.value[regionKey].due_to_work
  regionState.value[regionKey].due_to_work = cur === value ? null : value
}

// ── Submit ────────────────────────────────────────────────────────────────────

const submitForm = async () => {
  if (!headerFields.value.name.trim()) { alert("Please enter the worker's name."); return }
  if (isSubmitting.value) return
  isSubmitting.value = true
  try {
    const payload = { ...headerFields.value }
    BODY_REGIONS.forEach(r => {
      const s = regionState.value[r.key]
      payload[`${r.key}_selected`]    = s.selected
      payload[`${r.key}_due_to_work`] = s.due_to_work
      payload[`${r.key}_responses`]   = s.responses.map(k => {
        const [severity, frequency] = k.split('|')
        return { severity, frequency }
      })
    })
    // Include the current session's BOSS group ID so that successive BOSS forms
    // submitted in the same session are linked to the same group.
    const currentGroupId = getBossGroupId()
    if (currentGroupId) payload.boss_group_id = Number(currentGroupId)

    const res = await api.post('/boss-questionnaires', payload)
    setBossQuestionnaireId(res.data.id)
    // Persist the group ID returned by the backend (new group on first save, same group on repeat).
    saveBossGroupId(res.data.boss_group_id)

    // Save transfer data for ERA auto-fill (used as fallback if group API is unreachable)
    saveBossEraTransfer({
      name:       headerFields.value.name,
      department: headerFields.value.department,
      process:    headerFields.value.process,
      jobTask:    headerFields.value.job_task,
      date_start: headerFields.value.date_start,
      date_end:   headerFields.value.date_end,
    })
    // Snapshot includes formatted date for display in the submitted summary
    lastSubmitted.value = {
      ...headerFields.value,
      formattedDate: formattedDateRange.value,
    }
    showRetainPanel.value = false
    stage.value = 'submitted'
  } catch (err) {
    console.error(err.response?.data ?? err)
    // A stale boss_group_id in localStorage (e.g. from a previous session whose
    // database was reset) could be causing the failure. Clear it so the retry
    // creates a fresh group rather than referencing a non-existent one.
    clearBossGroupId()
    alert('Error saving BOSS Questionnaire. Please try again.')
  } finally {
    isSubmitting.value = false
  }
}

// ── Post-submission actions ───────────────────────────────────────────────────

const goToEra = () => {
  flagBossToEraRedirect()
  router.push('/era-form')
}

const startNewBossForm = () => {
  showRetainPanel.value = true
}

const cancelRetain = () => {
  showRetainPanel.value = false
}

// Applies the checkbox selections and resets to the form stage
const applyRetainAndStart = () => {
  const r = retainSelections.value
  const s = lastSubmitted.value ?? {}
  headerFields.value = {
    name:       r.name       ? (s.name       ?? '') : '',
    staff_id:   r.staff_id   ? (s.staff_id   ?? '') : '',
    company:    r.company    ? (s.company    ?? '') : '',
    department: r.department ? (s.department ?? '') : '',
    process:    r.process    ? (s.process    ?? '') : '',
    job_task:   r.job_task   ? (s.job_task   ?? '') : '',
    date_start: r.date       ? (s.date_start ?? '') : '',
    date_end:   r.date       ? (s.date_end   ?? '') : '',
  }
  regionState.value = initRegionState()
  activeRegionKey.value = null
  showRetainPanel.value = false
  stage.value = 'form'
}

const clearAllInfo = () => {
  headerFields.value = {
    name: '', staff_id: '', date_start: '', date_end: '',
    department: '', company: '', process: '', job_task: '',
  }
  regionState.value = initRegionState()
  activeRegionKey.value = null
  showRetainPanel.value = false
  stage.value = 'form'
  // Clear the group so the next submission starts a brand-new group.
  clearBossGroupId()
}

// ── Reset session ─────────────────────────────────────────────────────────────
const showResetDialog = ref(false)

const confirmReset = () => {
  // Wipe every piece of BOSS-related local/session storage
  clearBossGroupId()
  clearBossQuestionnaireId()
  clearBossEraTransfer()
  sessionStorage.removeItem('boss_to_era_redirect')

  // Reset all in-memory form state
  headerFields.value = {
    name: '', staff_id: '', date_start: '', date_end: '',
    department: '', company: '', process: '', job_task: '',
  }
  regionState.value = initRegionState()
  activeRegionKey.value = null
  showRetainPanel.value = false
  lastSubmitted.value = null
  stage.value = 'form'
  showResetDialog.value = false
}

const cancelReset = () => {
  showResetDialog.value = false
}
</script>

<template>
  <div class="boss-page">

    <!-- Hero -->
    <div class="page-hero">
      <div class="hero-left">
        <div class="hero-tag">MUSCULOSKELETAL QUESTIONS - BOSS</div>
        <h1 class="hero-title">BOSS Questionnaire</h1>
        <p class="hero-sub">Body Symptom Survey — complete before proceeding to the ERA Assessment.</p>
      </div>
      <div class="hero-steps">
        <div class="step-pill active">BOSS</div>
        <div class="step-arrow">→</div>
        <div class="step-pill">ERA Steps 1–7</div>
      </div>
    </div>

    <!-- ─── INFO BANNER ─── -->
    <div class="info-banner">
      <div class="info-banner-icon">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/>
          <polyline points="13 2 13 9 20 9"/>
        </svg>
      </div>
      <div class="info-banner-text">
        <strong>Data Synchronisation Notice</strong>
        <p>
          The <em>name, department, process,</em> and <em>job task</em> you enter here will be automatically transferred to the ERA Assessment to reduce duplicate data entry.
          Please verify all information is accurate before submitting — it will be used to pre-fill the ERA Assessment fields.
        </p>
      </div>
    </div>

    <!-- Instructions -->
    <div class="instructions-bar">
      <div class="inst-step"><span class="inst-num">1</span> Click a body region on the diagram to select it</div>
      <div class="inst-sep">·</div>
      <div class="inst-step"><span class="inst-num">2</span> Mark severity <span class="inst-badge red">red rows</span></div>
      <div class="inst-sep">·</div>
      <div class="inst-step"><span class="inst-num">3</span> Mark frequency <span class="inst-badge blue">blue rows</span></div>
      <div class="inst-sep">·</div>
      <div class="inst-step"><span class="inst-num">4</span> Set <strong>Y / N</strong> for "Due to work?"</div>
    </div>

    <div v-if="stage === 'form'" class="page-body">

      <!-- Worker Information -->
      <div class="form-card">
        <div class="card-header">
          <div class="card-num">01</div>
          <div><div class="card-title">Worker Information</div><div class="card-desc">Fill in the worker's details</div></div>
        </div>
        <div class="card-body header-grid">
          <div class="field-group">
            <label class="field-label">Name <span class="req">*</span></label>
            <input v-model="headerFields.name" type="text" class="field-input" placeholder="Full name" />
          </div>
          <div class="field-group">
            <label class="field-label">Staff ID</label>
            <input v-model="headerFields.staff_id" type="text" class="field-input" placeholder="Staff ID" />
          </div>
          <div class="field-group date-range-group">
            <label class="field-label">Date Range</label>
            <div class="date-range-row">
              <div class="date-half">
                <span class="date-prefix">From</span>
                <input v-model="headerFields.date_start" type="date" class="field-input date-in" />
              </div>
              <span class="date-sep">–</span>
              <div class="date-half">
                <span class="date-prefix">To</span>
                <input v-model="headerFields.date_end" type="date" class="field-input date-in" />
              </div>
            </div>
            <div v-if="formattedDateRange" class="date-preview">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
              {{ formattedDateRange }}
            </div>
          </div>
          <div class="field-group">
            <label class="field-label">Department</label>
            <input v-model="headerFields.department" type="text" class="field-input" placeholder="Department" />
          </div>
          <div class="field-group">
            <label class="field-label">Company</label>
            <input v-model="headerFields.company" type="text" class="field-input" placeholder="Company" />
          </div>
          <div class="field-group">
            <label class="field-label">
              Process
              <span class="field-hint"> — Higher-level work area or production process</span>
            </label>
            <input v-model="headerFields.process" type="text" class="field-input" placeholder="e.g. Welding Line, Assembly Station" />
            <span class="sync-tag">↔ Syncs to ERA → Process Name</span>
          </div>
          <div class="field-group field-full">
            <label class="field-label">
              Job Task
              <span class="field-hint"> — Specific task performed within the process above</span>
            </label>
            <input v-model="headerFields.job_task" type="text" class="field-input" placeholder="e.g. Spot Welding of Door Panel, Loading of Engine Block" />
            <span class="sync-tag">↔ Syncs to ERA → Task Title (under the process above)</span>
          </div>
        </div>
      </div>

      <!-- Body Symptom Survey -->
      <div class="form-card">
        <div class="card-header">
          <div class="card-num">02</div>
          <div><div class="card-title">Body Symptom Survey</div><div class="card-desc">Click body regions on the diagram, then fill in severity and frequency details</div></div>
        </div>
        <div class="card-body survey-body">

          <!-- Left: body diagram -->
          <div class="diagram-col">
            <div class="diagram-hint">Click a label badge to select a body region</div>

            <div class="diagram-container">
              <img
                src="/images/body_diagram.png"
                alt="Body diagram — back view"
                class="body-image"
                draggable="false"
              />
              <!-- Clickable hotspot overlays positioned over each badge in the image -->
              <div
                v-for="(hs, idx) in HOTSPOTS"
                :key="idx"
                class="hotspot"
                :class="{
                  'hotspot--selected': regionState[hs.regionKey].selected,
                  'hotspot--active':   activeRegionKey === hs.regionKey,
                }"
                :style="{ left: hs.cx + '%', top: hs.cy + '%' }"
                @click="clickRegion(hs.regionKey)"
                :title="BODY_REGIONS.find(r => r.key === hs.regionKey)?.label"
              />
            </div>

            <!-- Affected regions summary -->
            <div class="affected-summary">
              <div class="affected-summary-title">
                Affected regions
                <span class="affected-count" v-if="selectedRegions.length">({{ selectedRegions.length }})</span>
              </div>
              <div v-if="selectedRegions.length" class="selected-chips">
                <div
                  v-for="r in selectedRegions"
                  :key="r.key"
                  class="chip"
                  :class="{ 'chip--active': activeRegionKey === r.key }"
                  @click="clickRegion(r.key)"
                  :title="'Click to view ' + r.label"
                >
                  <span class="chip-letter">{{ r.letter }}</span>
                  <span class="chip-label">{{ r.label }}</span>
                </div>
              </div>
              <div v-else class="no-selection-hint">None marked yet — click a badge on the diagram, then click <strong>Mark as Affected</strong></div>
            </div>
          </div>

          <!-- Right: detail panel -->
          <div class="detail-col">
            <!-- Placeholder when nothing is active -->
            <div v-if="!activeRegion" class="detail-placeholder">
              <div class="placeholder-icon">⬅</div>
              <div class="placeholder-title">Click a badge to inspect a region</div>
              <div class="placeholder-sub">Click any letter badge (A–L) on the body diagram. Then use <strong>Mark as Affected</strong> to record pain or discomfort for that region.</div>
            </div>

            <!-- Region detail table -->
            <div v-else class="region-detail">
              <div class="detail-header">
                <div class="detail-letter">{{ activeRegion.letter }}</div>
                <div class="detail-info">
                  <div class="detail-name">{{ activeRegion.label }}</div>
                  <div class="detail-sub">Mark severity and frequency for this region</div>
                </div>
                <!-- Affected toggle — two clear states -->
                <div class="affected-toggle">
                  <template v-if="!regionState[activeRegion.key].selected">
                    <button class="btn-mark-affected" @click="markAffected(activeRegion.key)">
                      + Mark as Affected
                    </button>
                  </template>
                  <template v-else>
                    <span class="affected-badge">✓ Affected</span>
                    <button class="btn-unmark" @click="unmarkAffected(activeRegion.key)">
                      Unmark
                    </button>
                  </template>
                </div>
              </div>

              <!-- Severity × Frequency table -->
              <div class="table-wrap">
                <table class="symptom-table">
                  <thead>
                    <tr>
                      <th class="th-severity">Severity Level</th>
                      <th
                        v-for="freq in FREQUENCIES"
                        :key="freq.key"
                        class="th-freq"
                        :title="freq.full"
                      >{{ freq.short }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="(sev, si) in SEVERITIES"
                      :key="sev.key"
                      :class="sev.color"
                    >
                      <td class="td-severity">{{ sev.label }}</td>
                      <td
                        v-for="freq in FREQUENCIES"
                        :key="freq.key"
                        class="td-cell"
                        @click="toggleCell(activeRegion.key, sev.key, freq.key)"
                      >
                        <span
                          class="cell-dot"
                          :class="{ 'cell-dot--checked': isCellChecked(activeRegion.key, sev.key, freq.key) }"
                        />
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <!-- Due to work -->
              <div class="dtw-row">
                <span class="dtw-label">Due to work?</span>
                <div class="dtw-group">
                  <button
                    type="button"
                    class="dtw-btn dtw-y"
                    :class="{ active: regionState[activeRegion.key].due_to_work === true }"
                    @click="setDueToWork(activeRegion.key, true)"
                  >
                    <span class="dtw-icon">✓</span> Yes
                  </button>
                  <button
                    type="button"
                    class="dtw-btn dtw-n"
                    :class="{ active: regionState[activeRegion.key].due_to_work === false }"
                    @click="setDueToWork(activeRegion.key, false)"
                  >
                    <span class="dtw-icon">✗</span> No
                  </button>
                </div>
                <div class="dtw-hint">Click any badge on the diagram to view another region</div>
              </div>

              <!-- Frequency legend -->
              <div class="freq-legend">
                <span class="legend-title">Frequency key:</span>
                <span v-for="f in FREQUENCIES" :key="f.key" class="legend-item" :title="f.full">
                  <strong>{{ f.short }}</strong> = {{ f.full }}
                </span>
              </div>
            </div>
          </div>

        </div><!-- /survey-body -->

        <!-- Notes -->
        <div class="notes-section">
          <div class="notes-title">Note:</div>
          <ul class="notes-list">
            <li>'Pain' may include aches, stiffness, numbness, tingling or burning sensations</li>
            <li>Discomfort — may have to rest, move or stretch at a certain point</li>
            <li>Pain (able to continue work) — may or may not require massage, oil or medications</li>
            <li>Pain (unable to continue work) — may or may not cause medical leave</li>
          </ul>
        </div>

      </div><!-- /form-card -->

      <!-- Submit -->
      <div class="submit-row">
        <div class="submit-left">
          <div class="submit-summary" v-if="selectedRegions.length">
            {{ selectedRegions.length }} body region{{ selectedRegions.length > 1 ? 's' : '' }} marked:
            <strong>{{ selectedRegions.map(r => r.label).join(', ') }}</strong>
          </div>
          <div class="submit-confirm-note">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 8 12 12 14 14"/></svg>
            By submitting, you confirm the information above is accurate and ready to be carried forward into the ERA Assessment.
          </div>
        </div>
        <button type="button" class="btn-reset-session" @click="showResetDialog = true" title="Clear all locally stored BOSS data and start a new session">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
            <polyline points="1 4 1 10 7 10"/>
            <path d="M3.51 15a9 9 0 1 0 .49-4.95"/>
          </svg>
          Reset Session
        </button>
        <button type="button" class="btn-submit" :disabled="isSubmitting" @click="submitForm">
          <span v-if="isSubmitting">Saving…</span>
          <span v-else>Submit BOSS Questionnaire →</span>
        </button>
      </div>

    </div><!-- /page-body (form stage) -->

    <!-- ─── SUBMITTED STAGE ─── -->
    <div v-else-if="stage === 'submitted'" class="submitted-body">

      <!-- Success header -->
      <div class="success-header">
        <div class="success-icon">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"/>
            <polyline points="9 12 11 14 15 10"/>
          </svg>
        </div>
        <div class="success-text">
          <h2>BOSS Questionnaire Submitted</h2>
          <p>Your information has been recorded and is ready to be transferred to the ERA Assessment.</p>
        </div>
      </div>

      <!-- Submitted data summary -->
      <div class="summary-card" v-if="lastSubmitted">
        <div class="summary-title">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
            <polyline points="14 2 14 8 20 8"/>
          </svg>
          Submitted Details
        </div>
        <div class="summary-grid">
          <div class="summary-row"><span class="summary-label">Name</span><span class="summary-value">{{ lastSubmitted.name }}</span></div>
          <div class="summary-row" v-if="lastSubmitted.staff_id"><span class="summary-label">Staff ID</span><span class="summary-value">{{ lastSubmitted.staff_id }}</span></div>
          <div class="summary-row" v-if="lastSubmitted.formattedDate"><span class="summary-label">Date Range</span><span class="summary-value">{{ lastSubmitted.formattedDate }}</span></div>
          <div class="summary-row"><span class="summary-label">Department</span><span class="summary-value">{{ lastSubmitted.department }}</span></div>
          <div class="summary-row" v-if="lastSubmitted.company"><span class="summary-label">Company</span><span class="summary-value">{{ lastSubmitted.company }}</span></div>
          <div class="summary-row" v-if="lastSubmitted.process"><span class="summary-label">Process</span><span class="summary-value">{{ lastSubmitted.process }}</span></div>
          <div class="summary-row" v-if="lastSubmitted.job_task"><span class="summary-label">Job Task</span><span class="summary-value">{{ lastSubmitted.job_task }}</span></div>
        </div>
      </div>

      <!-- Transfer notice -->
      <div class="transfer-notice">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/>
          <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>
        </svg>
        <span>
          Your <strong>name, department, process,</strong> and <strong>job task</strong> will be automatically pre-filled in the ERA Assessment — you do not need to enter them again.
        </span>
      </div>

      <!-- Action cards -->
      <div class="action-cards">

        <button class="action-card action-era" @click="goToEra">
          <div class="action-card-icon action-era-icon">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
              <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
            </svg>
          </div>
          <div class="action-card-body">
            <div class="action-card-title">Proceed to ERA Assessment</div>
            <div class="action-card-desc">Continue to the Ergonomics Risk Assessment with your information already pre-filled. Recommended next step.</div>
          </div>
          <svg class="action-arrow" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
          </svg>
        </button>

        <button
          class="action-card action-new"
          :class="{ 'action-new--active': showRetainPanel }"
          @click="startNewBossForm"
        >
          <div class="action-card-icon action-new-icon">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
              <polyline points="14 2 14 8 20 8"/>
              <line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/>
            </svg>
          </div>
          <div class="action-card-body">
            <div class="action-card-title">Add Another Worker to This Group</div>
            <div class="action-card-desc">
              Submit another BOSS questionnaire for the <strong>same ERA Assessment group</strong>.
              Choose which fields to carry forward.
            </div>
          </div>
          <svg class="action-arrow" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
          </svg>
        </button>

      </div><!-- /action-cards -->

      <!-- ─── RETENTION PANEL ─── -->
      <Transition name="slide-down">
        <div v-if="showRetainPanel" class="retain-panel">

          <div class="retain-panel-header">
            <div class="retain-panel-icon">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
              </svg>
            </div>
            <div>
              <div class="retain-panel-title">Select Fields to Carry Forward</div>
              <div class="retain-panel-sub">Checked fields will be pre-filled in the new form. Unchecked fields will be cleared.</div>
            </div>
          </div>

          <!-- Select All row -->
          <div class="retain-select-all-row">
            <label class="retain-checkbox-label retain-select-all">
              <input
                type="checkbox"
                class="retain-cb"
                :checked="allRetainSelected"
                :indeterminate="someRetainSelected && !allRetainSelected"
                @change="toggleSelectAll($event.target.checked)"
              />
              <span class="cb-box" :class="{ 'cb-indeterminate': someRetainSelected && !allRetainSelected }">
                <svg v-if="allRetainSelected" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5"><polyline points="20 6 9 17 4 12"/></svg>
                <span v-else-if="someRetainSelected && !allRetainSelected" class="indeterminate-dash"></span>
              </span>
              <span class="retain-label-text retain-label-all">Select All</span>
            </label>
            <span class="retain-select-all-hint">{{ Object.values(retainSelections).filter(v => v).length }} of {{ RETAIN_FIELDS.length }} selected</span>
          </div>

          <!-- Field checkboxes -->
          <div class="retain-fields-grid">
            <label
              v-for="field in RETAIN_FIELDS"
              :key="field.key"
              class="retain-field-row"
              :class="{ 'retain-field-row--checked': retainSelections[field.key] }"
            >
              <input type="checkbox" class="retain-cb" v-model="retainSelections[field.key]" />
              <span class="cb-box">
                <svg v-if="retainSelections[field.key]" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5"><polyline points="20 6 9 17 4 12"/></svg>
              </span>
              <div class="retain-field-info">
                <span class="retain-field-label">{{ field.label }}</span>
                <span class="retain-field-value">
                  <template v-if="field.key === 'date'">
                    {{ lastSubmitted?.formattedDate || '—' }}
                  </template>
                  <template v-else>
                    {{ lastSubmitted?.[field.key] || '—' }}
                  </template>
                </span>
              </div>
            </label>
          </div>

          <!-- Actions -->
          <div class="retain-actions">
            <button class="retain-btn-cancel" @click="cancelRetain">
              ← Back to Options
            </button>
            <div class="retain-actions-right">
              <button class="retain-btn-clear" @click="clearAllInfo">
                Clear All & Start Fresh
              </button>
              <button class="retain-btn-confirm" @click="applyRetainAndStart" :disabled="!someRetainSelected && false">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                Apply &amp; Create New Form
              </button>
            </div>
          </div>

        </div>
      </Transition>

    </div><!-- /submitted stage -->

    <!-- ─── RESET CONFIRMATION DIALOG ─── -->
    <Teleport to="body">
      <Transition name="reset-fade">
        <div v-if="showResetDialog" class="reset-overlay" @click.self="cancelReset" role="dialog" aria-modal="true" aria-labelledby="reset-title">
          <div class="reset-dialog">

            <!-- Icon -->
            <div class="reset-dialog-icon">
              <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                <line x1="12" y1="9" x2="12" y2="13"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
              </svg>
            </div>

            <!-- Text -->
            <h3 id="reset-title" class="reset-dialog-title">Reset BOSS Session?</h3>
            <p class="reset-dialog-msg">
              This will <strong>permanently remove</strong> all locally stored BOSS data for the current session, including:
            </p>
            <ul class="reset-dialog-list">
              <li>Current BOSS Group ID</li>
              <li>Last submitted questionnaire reference</li>
              <li>All unsaved form data and body-symptom selections</li>
              <li>Any pending ERA transfer data</li>
            </ul>
            <p class="reset-dialog-note">
              <strong>This action cannot be undone.</strong> After confirming, you will be redirected to a fresh BOSS Questionnaire to begin a new BOSS Group.
            </p>

            <!-- Actions -->
            <div class="reset-dialog-actions">
              <button type="button" class="reset-btn-cancel" @click="cancelReset">
                Cancel
              </button>
              <button type="button" class="reset-btn-confirm" @click="confirmReset">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                  <polyline points="1 4 1 10 7 10"/>
                  <path d="M3.51 15a9 9 0 1 0 .49-4.95"/>
                </svg>
                Yes, Reset Session
              </button>
            </div>

          </div>
        </div>
      </Transition>
    </Teleport>

  </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&family=Figtree:wght@400;500;600&display=swap');

.boss-page {
  --navy: #0b1a2a;
  --navy-mid: #17324f;
  --navy-light: #244b73;
  --accent: #e8a020;
  --accent-light: #fef3dc;
  --accent-dim: rgba(232,160,32,0.15);
  --surface: #ffffff;
  --surface-2: #f2f6fb;
  --border: #d7e0eb;
  --border-strong: #bac8d7;
  --text: #0f1e2e;
  --text-mid: #344658;
  --text-soft: #66798d;
  --danger: #c0392b;
  --green: #1a7a3e;
  --green-bg: #e8f7ee;
  --red-row: #fff0f0;
  --blue-row: #f0f6ff;
  --radius: 10px;
  --radius-sm: 6px;
  --shadow-card: 0 2px 8px rgba(15,30,46,0.08), 0 10px 24px rgba(15,30,46,0.08);

  font-family: Arial, sans-serif;
  font-size: 14px;
  color: var(--text);
  display: flex; flex-direction: column; gap: 0;
  animation: fadeUp 0.4s ease both;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
@keyframes fadeUp {
  from { opacity: 0; transform: translateY(10px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* ── Hero ─────────────────────────────────────────────────────────────────── */
.page-hero {
  display: flex; align-items: flex-start; justify-content: space-between;
  gap: 24px; padding: 28px 32px 24px;
  background: linear-gradient(135deg, #0b1a2a 0%, #17324f 58%, #224f7a 100%);
  border-radius: var(--radius) var(--radius) 0 0;
  position: relative; overflow: hidden;
}
.page-hero::before {
  content: ''; position: absolute; top: -40px; right: -40px;
  width: 200px; height: 200px; border-radius: 50%;
  background: radial-gradient(circle, rgba(232,160,32,0.18) 0%, transparent 70%);
  pointer-events: none;
}
.page-hero::after {
  content: 'BOSS'; position: absolute; right: 24px; bottom: -16px;
  font-family: Arial, sans-serif; font-size: 72px; font-weight: 800;
  color: rgba(255,255,255,0.04); letter-spacing: -2px;
  pointer-events: none; user-select: none;
}
.hero-left { display: flex; flex-direction: column; gap: 8px; }
.hero-tag {
  display: inline-flex; align-items: center;
  background: var(--accent-dim); color: var(--accent);
  border: 1px solid rgba(232,160,32,0.3); border-radius: 20px;
  padding: 3px 12px; font-size: 11px; font-weight: 600;
  letter-spacing: 0.05em; width: fit-content;
}
.hero-title {
  font-family: Arial, sans-serif; font-size: 26px; font-weight: 800;
  color: #fff; line-height: 1.2;
}
.hero-sub { font-size: 13px; color: rgba(255,255,255,0.65); max-width: 480px; }
.hero-steps { display: flex; align-items: center; gap: 8px; flex-shrink: 0; margin-top: 4px; }
.step-pill {
  padding: 5px 14px; border-radius: 20px;
  border: 1px solid rgba(255,255,255,0.2);
  color: rgba(255,255,255,0.6); font-size: 12px; font-weight: 600;
}
.step-pill.active { background: var(--accent); color: var(--navy); border-color: var(--accent); }
.step-arrow { color: rgba(255,255,255,0.4); font-size: 16px; }

/* ── Instructions ─────────────────────────────────────────────────────────── */
.instructions-bar {
  display: flex; flex-wrap: wrap; gap: 6px 16px; align-items: center;
  background: var(--accent-light); border-bottom: 1px solid rgba(232,160,32,0.25);
  padding: 10px 24px; font-size: 12px; color: var(--text-mid);
}
.inst-sep { color: var(--border-strong); font-size: 16px; }
.inst-num {
  display: inline-flex; align-items: center; justify-content: center;
  width: 18px; height: 18px; border-radius: 50%;
  background: var(--navy); color: #fff; font-size: 10px; font-weight: 700;
  margin-right: 4px;
}
.inst-badge {
  display: inline-block; padding: 1px 6px; border-radius: 3px;
  font-size: 10px; font-weight: 600;
}
.inst-badge.red  { background: #fde8e8; color: #a00; border: 1px solid #f5c6c6; }
.inst-badge.blue { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }

/* ── Page body ────────────────────────────────────────────────────────────── */
.page-body {
  display: flex; flex-direction: column; gap: 20px;
  padding: 20px 24px 32px;
  background: var(--surface-2);
  border-radius: 0 0 var(--radius) var(--radius);
}

/* ── Cards ────────────────────────────────────────────────────────────────── */
.form-card {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--radius); box-shadow: var(--shadow-card); overflow: hidden;
}
.card-header {
  display: flex; align-items: center; gap: 14px;
  padding: 14px 20px;
  background: linear-gradient(90deg, #f8fafc 0%, var(--surface) 100%);
  border-bottom: 1px solid var(--border);
}
.card-num {
  width: 32px; height: 32px; border-radius: 50%;
  background: var(--navy); color: #fff; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  font-family: Arial, sans-serif; font-size: 13px; font-weight: 700;
}
.card-title { font-size: 15px; font-weight: 600; color: var(--navy); }
.card-desc  { font-size: 12px; color: var(--text-soft); margin-top: 2px; }
.card-body  { padding: 20px; }

/* ── Header grid ──────────────────────────────────────────────────────────── */
.header-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
.date-range-group { grid-column: 1 / -1; }
.field-group { display: flex; flex-direction: column; gap: 5px; }
.field-label { font-size: 12px; font-weight: 600; color: var(--text-mid); }
.req { color: var(--danger); }
.field-input {
  padding: 8px 10px; border: 1px solid var(--border);
  border-radius: var(--radius-sm); font-family: inherit;
  font-size: 13px; color: var(--text); background: var(--surface);
  transition: border-color 0.15s;
}
.field-input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-dim); }

/* ── Survey layout ────────────────────────────────────────────────────────── */
.survey-body {
  display: grid;
  grid-template-columns: 480px 1fr;
  gap: 24px;
  align-items: start;
}

/* ── Diagram column ───────────────────────────────────────────────────────── */
.diagram-col {
  display: flex; flex-direction: column; align-items: center; gap: 12px;
  position: sticky; top: 20px;
}
.diagram-hint {
  font-size: 11px; font-weight: 600; color: var(--text-soft);
  text-transform: uppercase; letter-spacing: 0.05em;
  text-align: center;
}

/* ── Image + hotspot overlay ──────────────────────────────────────────────── */
.diagram-container {
  position: relative;
  width: 100%;
  max-width: 480px;
  border-radius: 12px;
  overflow: hidden;
  background: #000;
  box-shadow: 0 4px 24px rgba(0,0,0,0.35);
}
.body-image {
  display: block;
  width: 100%;
  height: auto;
  user-select: none;
  pointer-events: none;
}

/* Invisible clickable div positioned over each badge in the image */
.hotspot {
  position: absolute;
  width: 5.5%;
  height: 3.8%;
  transform: translate(-50%, -50%);
  cursor: pointer;
  border-radius: 3px;
  border: 2px solid transparent;
  transition: background 0.15s, border-color 0.15s, box-shadow 0.15s;
}
.hotspot:hover {
  background: rgba(232, 160, 32, 0.35);
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(232, 160, 32, 0.25);
}
.hotspot--selected {
  background: rgba(232, 160, 32, 0.25);
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(232, 160, 32, 0.4), 0 0 12px rgba(232, 160, 32, 0.3);
}
.hotspot--active {
  background: rgba(232, 160, 32, 0.4);
  border-color: #fff;
  box-shadow: 0 0 0 3px var(--accent), 0 0 16px rgba(232, 160, 32, 0.5);
}

/* Affected summary below diagram */
.affected-summary {
  width: 100%;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  padding: 10px 12px;
}
.affected-summary-title {
  font-size: 11px; font-weight: 700; color: var(--text-mid);
  text-transform: uppercase; letter-spacing: 0.05em;
  margin-bottom: 8px;
}
.affected-count {
  font-weight: 700; color: var(--green);
}
.selected-chips {
  display: flex; flex-wrap: wrap; gap: 6px;
  width: 100%;
}
.chip {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 4px 10px 4px 6px; border-radius: 20px;
  background: var(--surface-2); border: 1px solid var(--border-strong);
  font-size: 12px; color: var(--text-mid); cursor: pointer;
  transition: all 0.12s;
}
.chip:hover { border-color: var(--accent); background: var(--accent-light); }
.chip--active { border-color: var(--green); background: var(--green-bg); font-weight: 600; }
.chip-letter {
  width: 18px; height: 18px; border-radius: 50%;
  background: var(--navy); color: #fff;
  font-family: Arial, sans-serif; font-size: 10px; font-weight: 700;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.chip--active .chip-letter { background: var(--green); color: #fff; }
.chip-label { font-size: 11px; }
.no-selection-hint { font-size: 12px; color: var(--text-soft); line-height: 1.5; }

/* ── Detail column ────────────────────────────────────────────────────────── */
.detail-col { min-height: 400px; }

.detail-placeholder {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  gap: 12px; height: 380px;
  border: 2px dashed var(--border-strong); border-radius: var(--radius);
  color: var(--text-soft); text-align: center; padding: 32px;
}
.placeholder-icon { font-size: 36px; opacity: 0.4; }
.placeholder-title { font-size: 16px; font-weight: 600; color: var(--text-mid); }
.placeholder-sub { font-size: 13px; max-width: 280px; line-height: 1.5; }

/* ── Region detail ────────────────────────────────────────────────────────── */
.region-detail {
  border: 1px solid var(--border); border-radius: var(--radius);
  overflow: hidden; animation: fadeUp 0.2s ease both;
}

.detail-header {
  display: flex; align-items: center; gap: 14px;
  padding: 14px 18px;
  background: var(--navy);
}
.detail-letter {
  width: 38px; height: 38px; border-radius: 50%;
  background: var(--accent); color: var(--navy);
  font-family: Arial, sans-serif; font-size: 18px; font-weight: 800;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.detail-info { flex: 1; }
.detail-name { font-size: 17px; font-weight: 700; color: #fff; font-family: Arial, sans-serif; }
.detail-sub  { font-size: 12px; color: rgba(255,255,255,0.6); margin-top: 2px; }
/* Affected toggle — inside detail header */
.affected-toggle {
  display: flex; align-items: center; gap: 8px; flex-shrink: 0;
}
.btn-mark-affected {
  padding: 7px 16px; border-radius: 20px;
  border: 1.5px solid var(--accent);
  background: transparent; color: var(--accent);
  font-family: inherit; font-size: 12px; font-weight: 700;
  cursor: pointer; transition: all 0.15s; white-space: nowrap;
}
.btn-mark-affected:hover {
  background: var(--accent); color: var(--navy);
}
.affected-badge {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 5px 12px; border-radius: 20px;
  background: #1a7a3e; color: #fff;
  font-size: 12px; font-weight: 700;
  white-space: nowrap;
}
.btn-unmark {
  padding: 5px 10px; border-radius: 20px;
  border: 1px solid rgba(255,100,100,0.5);
  background: transparent; color: rgba(255,150,150,0.9);
  font-family: inherit; font-size: 11px; font-weight: 600;
  cursor: pointer; transition: all 0.12s; white-space: nowrap;
}
.btn-unmark:hover { background: rgba(192,57,43,0.3); border-color: #e07070; color: #ff9999; }

/* ── Symptom table ────────────────────────────────────────────────────────── */
.table-wrap { overflow-x: auto; padding: 16px; }

.symptom-table {
  width: 100%; border-collapse: collapse; font-size: 13px;
}
.th-severity {
  padding: 8px 14px; text-align: left; font-size: 12px; font-weight: 600;
  color: var(--text-mid); background: #f0f4f8; border: 1px solid var(--border);
  min-width: 200px;
}
.th-freq {
  padding: 8px 6px; text-align: center; font-size: 12px; font-weight: 700;
  color: var(--navy-mid); background: #f0f4f8; border: 1px solid var(--border);
  min-width: 56px; white-space: nowrap;
}
.td-severity {
  padding: 10px 14px; font-size: 13px; font-weight: 500;
  color: var(--text); border: 1px solid var(--border); background: inherit;
  min-width: 200px;
}
.td-cell {
  text-align: center; border: 1px solid var(--border);
  padding: 6px; cursor: pointer; transition: background 0.1s;
}
.td-cell:hover { filter: brightness(0.92); }

.sev-mild { background: #fff5f5; }
.sev-pain { background: #f0f5ff; }

.cell-dot {
  display: inline-block; width: 18px; height: 18px; border-radius: 4px;
  border: 1.5px solid var(--border-strong); background: var(--surface);
  transition: all 0.12s;
}
.cell-dot--checked {
  background: var(--navy); border-color: var(--navy);
  position: relative;
}
.cell-dot--checked::after {
  content: '✓';
  position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
  color: #fff; font-size: 11px; font-weight: 700;
}

/* ── Due to work row ──────────────────────────────────────────────────────── */
.dtw-row {
  display: flex; align-items: center; gap: 16px; flex-wrap: wrap;
  padding: 14px 16px; border-top: 1px solid var(--border);
  background: #fafbfc;
}
.dtw-label { font-size: 13px; font-weight: 600; color: var(--text-mid); }
.dtw-group { display: flex; gap: 8px; }
.dtw-btn {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 7px 18px; border-radius: var(--radius-sm);
  border: 1.5px solid var(--border-strong);
  background: var(--surface); color: var(--text-soft);
  font-family: inherit; font-size: 13px; font-weight: 600;
  cursor: pointer; transition: all 0.15s;
}
.dtw-btn:hover { border-color: var(--accent); color: var(--accent); }
.dtw-btn.dtw-y.active { background: var(--green-bg); border-color: var(--green); color: var(--green); }
.dtw-btn.dtw-n.active { background: #fff0f0; border-color: var(--danger); color: var(--danger); }
.dtw-icon { font-size: 12px; }
.dtw-hint {
  margin-left: auto; font-size: 11px; color: var(--text-soft);
  font-style: italic;
}

/* ── Frequency legend ─────────────────────────────────────────────────────── */
.freq-legend {
  display: flex; flex-wrap: wrap; gap: 6px 14px; align-items: center;
  padding: 10px 16px; border-top: 1px solid var(--border);
  background: var(--surface-2); font-size: 11px; color: var(--text-soft);
}
.legend-title { font-weight: 600; color: var(--text-mid); flex-shrink: 0; }
.legend-item strong { color: var(--navy); }

/* ── Notes ────────────────────────────────────────────────────────────────── */
.notes-section {
  padding: 14px 20px; border-top: 1px solid var(--border);
  background: var(--surface-2); font-size: 12px; color: var(--text-soft);
}
.notes-title { font-weight: 600; color: var(--text-mid); margin-bottom: 6px; }
.notes-list { padding-left: 18px; display: flex; flex-direction: column; gap: 3px; }

/* ── Submit ───────────────────────────────────────────────────────────────── */
.submit-row {
  display: flex; align-items: center; justify-content: space-between;
  gap: 16px; flex-wrap: wrap;
}
.submit-summary { font-size: 13px; color: var(--text-mid); }
.btn-submit {
  padding: 12px 28px;
  background: var(--accent); color: var(--navy);
  border: none; border-radius: var(--radius-sm);
  font-family: Arial, sans-serif; font-size: 14px; font-weight: 700;
  cursor: pointer; transition: all 0.15s;
  box-shadow: 0 2px 8px rgba(232,160,32,0.3);
  margin-left: auto;
}
.btn-submit:hover:not(:disabled) {
  background: #d4900e; box-shadow: 0 4px 14px rgba(232,160,32,0.4);
  transform: translateY(-1px);
}
.btn-submit:disabled { opacity: 0.6; cursor: not-allowed; }

/* ── Reset Session button ─────────────────────────────────────────────────── */
.btn-reset-session {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 10px 20px;
  background: transparent;
  color: #c0392b;
  border: 1.5px solid #e07070;
  border-radius: var(--radius-sm);
  font-family: Arial, sans-serif;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
}
.btn-reset-session:hover {
  background: #fff5f4;
  border-color: #c0392b;
}

/* ── Reset Confirmation Dialog ────────────────────────────────────────────── */
.reset-overlay {
  position: fixed;
  inset: 0;
  z-index: 9999;
  background: rgba(11, 26, 42, 0.55);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  backdrop-filter: blur(2px);
}

.reset-dialog {
  background: #fff;
  border-radius: 12px;
  padding: 32px 28px 24px;
  max-width: 460px;
  width: 100%;
  box-shadow: 0 20px 60px rgba(11,26,42,0.25);
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.reset-dialog-icon {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: #fff5f4;
  border: 2px solid #f0c2c2;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #c0392b;
  flex-shrink: 0;
  align-self: center;
}

.reset-dialog-title {
  font-family: Arial, sans-serif;
  font-size: 18px;
  font-weight: 700;
  color: #0f1e2e;
  margin: 0;
  text-align: center;
}

.reset-dialog-msg {
  font-size: 13.5px;
  color: #344658;
  line-height: 1.55;
  margin: 0;
}

.reset-dialog-list {
  margin: 0;
  padding-left: 20px;
  display: flex;
  flex-direction: column;
  gap: 5px;
  font-size: 13px;
  color: #344658;
  line-height: 1.5;
}

.reset-dialog-note {
  font-size: 13px;
  color: #c0392b;
  background: #fff5f4;
  border: 1px solid #f0c2c2;
  border-radius: 6px;
  padding: 10px 14px;
  margin: 0;
  line-height: 1.5;
}

.reset-dialog-actions {
  display: flex;
  gap: 10px;
  justify-content: flex-end;
  margin-top: 4px;
}

.reset-btn-cancel {
  padding: 10px 20px;
  background: #f2f6fb;
  color: #344658;
  border: 1px solid #d7e0eb;
  border-radius: 6px;
  font-family: Arial, sans-serif;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s;
}
.reset-btn-cancel:hover { background: #e5ecf5; }

.reset-btn-confirm {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 10px 20px;
  background: #c0392b;
  color: #fff;
  border: none;
  border-radius: 6px;
  font-family: Arial, sans-serif;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  transition: background 0.15s;
}
.reset-btn-confirm:hover { background: #a93226; }

/* ── Reset dialog transition ─────────────────────────────────────────────── */
.reset-fade-enter-active,
.reset-fade-leave-active { transition: opacity 0.2s ease; }
.reset-fade-enter-from,
.reset-fade-leave-to { opacity: 0; }

/* ── Responsive ───────────────────────────────────────────────────────────── */
@media (max-width: 1100px) {
  .survey-body { grid-template-columns: 1fr; }
  .diagram-col { position: static; }
  .header-grid { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 600px) {
  .header-grid { grid-template-columns: 1fr; }
  .instructions-bar { flex-direction: column; align-items: flex-start; gap: 6px; }
  .date-range-row { flex-direction: column; gap: 8px; }
  .date-half { width: 100%; }
  .date-sep { display: none; }
  .retain-fields-grid { grid-template-columns: 1fr; }
  .retain-actions { flex-direction: column; align-items: flex-start; }
  .retain-actions-right { width: 100%; justify-content: flex-end; }
}

/* ── Info Banner ──────────────────────────────────────────────────────────── */
.info-banner {
  display: flex;
  gap: 12px;
  padding: 14px 24px;
  background: #fffbea;
  border-bottom: 1px solid rgba(232,160,32,0.3);
  align-items: flex-start;
}
.info-banner-icon { flex-shrink: 0; color: #b07c00; margin-top: 2px; }
.info-banner-text strong { font-size: 12.5px; font-weight: 700; color: #7a5500; display: block; margin-bottom: 3px; }
.info-banner-text p { font-size: 12px; color: #614200; line-height: 1.6; }
.info-banner-text em { font-style: italic; }

/* ── Field extras ─────────────────────────────────────────────────────────── */
.field-hint { font-size: 11px; font-weight: 400; color: var(--text-soft); }
.field-full { grid-column: 1 / -1; }

/* ── Date range picker ────────────────────────────────────────────────────── */
.date-range-group { grid-column: 1 / -1; }
.date-range-row {
  display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
}
.date-half {
  display: flex; align-items: center; gap: 7px; flex: 1; min-width: 140px;
}
.date-prefix {
  font-size: 11.5px; font-weight: 600; color: var(--text-soft); white-space: nowrap; flex-shrink: 0;
}
.date-in { flex: 1; }
.date-sep {
  font-size: 18px; color: var(--border-strong); flex-shrink: 0; font-weight: 300;
  align-self: center;
}
.date-preview {
  margin-top: 6px;
  display: inline-flex; align-items: center; gap: 6px;
  padding: 5px 12px;
  background: var(--accent-light);
  border: 1px solid rgba(232,160,32,0.35);
  border-radius: 20px;
  font-size: 12px; font-weight: 600; color: #7e4b00;
  width: fit-content;
}

.sync-tag {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  margin-top: 4px;
  padding: 3px 9px;
  background: #e0f4f2;
  border: 1px solid rgba(14,124,114,0.25);
  border-radius: 20px;
  font-size: 10.5px;
  font-weight: 600;
  color: #0e7c72;
  width: fit-content;
}

/* ── Submit row extras ────────────────────────────────────────────────────── */
.submit-left { display: flex; flex-direction: column; gap: 6px; flex: 1; }
.submit-confirm-note {
  display: flex; align-items: flex-start; gap: 6px;
  font-size: 11.5px; color: var(--text-soft); line-height: 1.55;
}
.submit-confirm-note svg { flex-shrink: 0; margin-top: 1px; }

/* ── Submitted stage ──────────────────────────────────────────────────────── */
.submitted-body {
  display: flex; flex-direction: column; gap: 16px;
  padding: 20px 24px 32px;
  background: var(--surface-2);
  border-radius: 0 0 var(--radius) var(--radius);
}

.success-header {
  display: flex; align-items: center; gap: 16px;
  padding: 20px 24px;
  background: #f0fdf4; border: 1.5px solid #86efac;
  border-radius: var(--radius); box-shadow: var(--shadow-card);
}
.success-icon {
  width: 52px; height: 52px; border-radius: 50%;
  background: linear-gradient(135deg, #1a6e44 0%, #1d8c55 100%);
  color: #fff; display: flex; align-items: center; justify-content: center;
  flex-shrink: 0; box-shadow: 0 4px 12px rgba(26,110,68,0.3);
}
.success-text h2 { font-family: Arial, sans-serif; font-size: 16px; font-weight: 700; color: #14532d; margin-bottom: 4px; }
.success-text p  { font-size: 13px; color: #166534; line-height: 1.5; }

.summary-card {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--radius); box-shadow: var(--shadow-card); overflow: hidden;
}
.summary-title {
  display: flex; align-items: center; gap: 7px;
  padding: 11px 18px; font-size: 11.5px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.06em; color: var(--navy-mid);
  background: var(--surface-2); border-bottom: 1px solid var(--border);
}
.summary-grid { padding: 4px 0; }
.summary-row {
  display: flex; align-items: baseline; gap: 12px;
  padding: 9px 18px; border-bottom: 1px solid var(--border);
}
.summary-row:last-child { border-bottom: none; }
.summary-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-soft); min-width: 100px; flex-shrink: 0; }
.summary-value { font-size: 13.5px; font-weight: 500; color: var(--navy); }

.transfer-notice {
  display: flex; align-items: flex-start; gap: 8px;
  padding: 12px 16px; background: #e0f4f2;
  border: 1px solid rgba(14,124,114,0.3); border-radius: var(--radius-sm);
  font-size: 12.5px; color: #064e49; line-height: 1.6;
}
.transfer-notice svg { flex-shrink: 0; color: #0e7c72; margin-top: 2px; }

/* ── Action cards ─────────────────────────────────────────────────────────── */
.action-cards { display: flex; flex-direction: column; gap: 10px; }
.action-card {
  display: flex; align-items: center; gap: 14px;
  padding: 18px 20px; border-radius: var(--radius);
  border: 1.5px solid var(--border); background: var(--surface);
  cursor: pointer; text-align: left; transition: all 0.2s;
  box-shadow: var(--shadow-card); width: 100%;
}
.action-card:hover { box-shadow: 0 4px 14px rgba(15,30,46,0.12), 0 12px 28px rgba(15,30,46,0.12); transform: translateY(-2px); }
.action-card-icon {
  width: 48px; height: 48px; border-radius: 10px;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.action-era-icon  { background: linear-gradient(135deg, #1f4e77 0%, #173b5b 100%); color: #fff; box-shadow: 0 4px 10px rgba(23,59,91,0.3); }
.action-new-icon  { background: linear-gradient(135deg, #1a6e44 0%, #154f29 100%); color: #fff; box-shadow: 0 4px 10px rgba(26,110,68,0.3); }
.action-card-body { flex: 1; }
.action-card-title { font-family: Arial, sans-serif; font-size: 14px; font-weight: 700; color: var(--navy); margin-bottom: 3px; }
.action-card-desc  { font-size: 12px; color: var(--text-soft); line-height: 1.5; }
.action-arrow { color: var(--text-soft); flex-shrink: 0; }
.action-era:hover { border-color: #1f4e77; }
.action-era:hover .action-arrow { color: #1f4e77; }
.action-new:hover  { border-color: #1a6e44; }
.action-new:hover .action-arrow { color: #1a6e44; }
.action-new--active { border-color: #1a6e44 !important; background: #f0fdf4; }

/* ── Retention panel ──────────────────────────────────────────────────────── */
.retain-panel {
  background: var(--surface);
  border: 1.5px solid #1a6e44;
  border-radius: var(--radius);
  box-shadow: 0 4px 20px rgba(26,110,68,0.15);
  overflow: hidden;
}

.retain-panel-header {
  display: flex; align-items: flex-start; gap: 12px;
  padding: 16px 20px;
  background: linear-gradient(135deg, #0e2d1a 0%, #1a5c35 100%);
}
.retain-panel-icon {
  width: 34px; height: 34px; border-radius: 8px;
  background: rgba(255,255,255,0.15); color: #fff;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.retain-panel-title {
  font-family: Arial, sans-serif; font-size: 14px; font-weight: 700;
  color: #f0fff5; margin-bottom: 3px;
}
.retain-panel-sub { font-size: 12px; color: rgba(230,255,240,0.8); line-height: 1.5; }

/* Select-all row */
.retain-select-all-row {
  display: flex; align-items: center; justify-content: space-between;
  padding: 12px 20px; background: #f6fef9;
  border-bottom: 1px solid #d1fae5;
}
.retain-select-all-hint { font-size: 11.5px; color: #1a6e44; font-weight: 600; }

/* Field grid */
.retain-fields-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 1px;
  background: var(--border);
  border-top: 1px solid var(--border);
}
.retain-field-row {
  display: flex; align-items: flex-start; gap: 10px;
  padding: 12px 20px;
  background: var(--surface);
  cursor: pointer;
  transition: background 0.12s;
}
.retain-field-row:hover { background: #f6fef9; }
.retain-field-row--checked { background: #f0fdf4; }

/* Custom checkboxes */
.retain-cb { display: none; }
.cb-box {
  width: 18px; height: 18px; border-radius: 4px; flex-shrink: 0;
  border: 2px solid var(--border-strong); background: var(--surface);
  display: flex; align-items: center; justify-content: center;
  transition: all 0.15s; margin-top: 1px;
}
.retain-field-row--checked .cb-box,
.retain-select-all .cb-box {
  background: #1a6e44; border-color: #1a6e44; color: #fff;
}
.retain-select-all .cb-box { width: 18px; height: 18px; border-radius: 4px; flex-shrink: 0; border: 2px solid var(--border-strong); background: var(--surface); display: flex; align-items: center; justify-content: center; transition: all 0.15s; }
input[type="checkbox"]:checked ~ .cb-box { background: #1a6e44; border-color: #1a6e44; color: #fff; }
.cb-indeterminate { background: #1a6e44 !important; border-color: #1a6e44 !important; }
.indeterminate-dash {
  width: 8px; height: 2px; background: #fff; border-radius: 1px; display: block;
}

.retain-checkbox-label {
  display: flex; align-items: center; gap: 10px; cursor: pointer;
}
.retain-label-text { font-size: 13px; color: var(--text-mid); }
.retain-label-all  { font-size: 13px; font-weight: 700; color: var(--navy); }

.retain-field-info { display: flex; flex-direction: column; gap: 2px; flex: 1; min-width: 0; }
.retain-field-label {
  font-size: 11px; font-weight: 700; text-transform: uppercase;
  letter-spacing: 0.06em; color: var(--text-soft);
}
.retain-field-value {
  font-size: 13px; font-weight: 500; color: var(--navy);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}

/* Retain actions bar */
.retain-actions {
  display: flex; align-items: center; justify-content: space-between;
  gap: 12px; flex-wrap: wrap;
  padding: 14px 20px;
  background: #f6fef9;
  border-top: 1px solid #d1fae5;
}
.retain-actions-right { display: flex; gap: 8px; flex-wrap: wrap; }
.retain-btn-cancel {
  background: none; border: none; cursor: pointer;
  font-size: 12.5px; font-weight: 600; color: var(--text-soft);
  padding: 8px 0; display: flex; align-items: center; gap: 5px;
  transition: color 0.15s;
}
.retain-btn-cancel:hover { color: var(--navy); }
.retain-btn-clear {
  padding: 9px 16px; font-size: 12.5px; font-weight: 600; font-family: inherit;
  border: 1.5px solid var(--danger); border-radius: var(--radius-sm);
  background: transparent; color: var(--danger); cursor: pointer; transition: all 0.15s;
}
.retain-btn-clear:hover { background: #fff5f4; }
.retain-btn-confirm {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 10px 20px; font-size: 13px; font-weight: 700;
  font-family: Arial, sans-serif;
  background: linear-gradient(180deg, #1e6e3a 0%, #154f29 100%);
  color: #fff; border: none; border-radius: var(--radius-sm);
  cursor: pointer; transition: all 0.18s;
  box-shadow: 0 3px 8px rgba(21,79,41,0.3);
}
.retain-btn-confirm:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(21,79,41,0.4); }

/* ── Slide-down transition ────────────────────────────────────────────────── */
.slide-down-enter-active { transition: all 0.28s ease; }
.slide-down-leave-active { transition: all 0.2s ease; }
.slide-down-enter-from, .slide-down-leave-to {
  opacity: 0; transform: translateY(-10px);
}
</style>
