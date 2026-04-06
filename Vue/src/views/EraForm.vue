<script setup>
import { ref, computed } from 'vue'
import api from '../services/api'
import { useRouter } from 'vue-router'
import { onMounted, watch } from 'vue'
import { markStepCompleted, setCurrentAssessmentId } from '../services/eraProgress'

const router = useRouter()

const assessorName = ref('')
const startDate = ref('')
const endDate = ref('')
const department = ref('')
const workingHours = ref('')
const breaks = ref('')

const formattedDateRange = computed(() => {
  if (!startDate.value || !endDate.value) return ''
  const start = new Date(startDate.value)
  const end = new Date(endDate.value)
  const dayOptions = { day: 'numeric' }
  const monthYearOptions = { month: 'long', year: 'numeric' }
  const startDay = start.toLocaleDateString('en-GB', dayOptions)
  const endDay = end.toLocaleDateString('en-GB', dayOptions)
  const monthYear = end.toLocaleDateString('en-GB', monthYearOptions)
  return `${startDay}–${endDay} ${monthYear}`
})

const processes = ref([
  {
    name: '',
    tasks: [{ title: '', description: '', worker_activities: '', row_number: 1 }]
  }
])

const renumberTasks = (process) => {
  process.tasks.forEach((task, idx) => { task.row_number = idx + 1 })
}

const addProcess = () => {
  processes.value.push({
    name: '',
    tasks: [{ title: '', description: '', worker_activities: '', row_number: 1 }]
  })
}

const removeProcess = (processIndex) => {
  if (processes.value.length > 1) {
    processes.value.splice(processIndex, 1)
  } else {
    alert('At least one process is required.')
  }
}

const addTask = (processIndex) => {
  const process = processes.value[processIndex]
  process.tasks.push({
    title: '', description: '', worker_activities: '',
    row_number: process.tasks.length + 1
  })
}

const removeTask = (processIndex, taskIndex) => {
  const process = processes.value[processIndex]
  if (process.tasks.length > 1) {
    process.tasks.splice(taskIndex, 1)
    renumberTasks(process)
  } else {
    alert('Each process must have at least one task.')
  }
}

const insertBullet = (processIndex, taskIndex) => {
  const task = processes.value[processIndex].tasks[taskIndex]
  task.worker_activities += '\n• '
}

const insertNumber = (processIndex, taskIndex) => {
  const task = processes.value[processIndex].tasks[taskIndex]
  const lines = task.worker_activities.split('\n')
  const number = lines.filter(l => l.trim().match(/^\d+\./)).length + 1
  task.worker_activities += `\n${number}. `
}

const handleKeydown = (event, processIndex, taskIndex) => {
  if (event.key !== 'Enter') return
  const task = processes.value[processIndex].tasks[taskIndex]
  const textarea = event.target
  const cursorPos = textarea.selectionStart
  const textBeforeCursor = task.worker_activities.substring(0, cursorPos)
  const lines = textBeforeCursor.split('\n')
  const currentLine = lines[lines.length - 1]
  if (currentLine.trim().startsWith('• ')) {
    event.preventDefault()
    task.worker_activities += '\n• '
  }
  const numberMatch = currentLine.trim().match(/^(\d+)\.\s/)
  if (numberMatch) {
    event.preventDefault()
    const nextNumber = parseInt(numberMatch[1]) + 1
    task.worker_activities += `\n${nextNumber}. `
  }
}

const photoGroups = ref([])

const rebuildPhotoGroups = () => {
  const groups = []
  processes.value.forEach((process) => {
    process.tasks.forEach((task) => {
      groups.push({
        process_name: process.name || '',
        task_title: task.title || '',
        task_description: task.description || '',
        task_ref: task,
        photos: [],
        previews: []
      })
    })
  })
  photoGroups.value = groups
}

watch(processes, () => { rebuildPhotoGroups() }, { deep: true })
onMounted(() => { rebuildPhotoGroups() })

const photoKey = (file) => `${file.name}__${file.size}__${file.lastModified}`

const mergeUniquePhotos = (existingPhotos, incomingPhotos) => {
  const byKey = new Map()
  existingPhotos.forEach((file) => byKey.set(photoKey(file), file))
  incomingPhotos.forEach((file) => byKey.set(photoKey(file), file))
  return Array.from(byKey.values())
}

const handlePhotoChange = (event, groupIndex) => {
  const files = Array.from(event.target.files || [])
  if (!files.length) return

  const group = photoGroups.value[groupIndex]
  const mergedPhotos = mergeUniquePhotos(group.photos, files)

  group.previews.forEach((url) => URL.revokeObjectURL(url))
  group.photos = mergedPhotos
  group.previews = mergedPhotos.map((file) => URL.createObjectURL(file))

  // Allow selecting the same file again if needed.
  event.target.value = ''
}

const removeGroupPhoto = (groupIndex, photoIndex) => {
  const group = photoGroups.value[groupIndex]
  if (!group) return

  const previewUrl = group.previews[photoIndex]
  if (previewUrl) URL.revokeObjectURL(previewUrl)

  group.photos.splice(photoIndex, 1)
  group.previews.splice(photoIndex, 1)
}

const clearGroupPhotos = (groupIndex) => {
  const group = photoGroups.value[groupIndex]
  if (!group) return

  group.previews.forEach((url) => URL.revokeObjectURL(url))
  group.photos = []
  group.previews = []
}

const isSubmitting = ref(false)

const submitForm = async () => {
  if (isSubmitting.value) return
  isSubmitting.value = true
  const formData = new FormData()
  formData.append('assessor_name', assessorName.value)
  formData.append('assessment_date', formattedDateRange.value)
  formData.append('department', department.value)
  formData.append('working_hours', workingHours.value)
  formData.append('breaks', breaks.value)
  processes.value.forEach((process, pIdx) => {
    formData.append(`processes[${pIdx}][name]`, process.name)
    process.tasks.forEach((task, tIdx) => {
      formData.append(`processes[${pIdx}][tasks][${tIdx}][title]`, task.title)
      formData.append(`processes[${pIdx}][tasks][${tIdx}][description]`, task.description)
      formData.append(`processes[${pIdx}][tasks][${tIdx}][worker_activities]`, task.worker_activities)
      formData.append(`processes[${pIdx}][tasks][${tIdx}][row_number]`, task.row_number)
    })
  })
  photoGroups.value.forEach((group, groupIndex) => {
    formData.append(`photo_groups[${groupIndex}][title]`, group.title)
    group.photos.forEach((photo) => {
      formData.append(`photo_groups[${groupIndex}][photos][]`, photo)
    })
  })
  try {
    const response = await api.post('/era-assessments', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    const assessmentId = response.data.id
    setCurrentAssessmentId(assessmentId)
    markStepCompleted(assessmentId, 1)
    router.push(`/era-checklist/${assessmentId}`)
  } catch (error) {
    console.error(error.response?.data)
    alert('Error occurred while submitting. Please try again.')
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <div class="era-page">
    <!-- -- PAGE HEADER -- -->
    <div class="page-hero">
      <div class="hero-left">
        <div class="hero-tag">
          <span class="tag-dot"></span>
          Step 1 of 7
        </div>
        <h1 class="hero-title">Initial ERA Assessment</h1>
        <p class="hero-sub">Ergonomics Risk Assessment Checklist — fill in all required fields before proceeding to the next step.</p>
      </div>
      <div class="hero-steps">
        <div v-for="s in 7" :key="s" class="step-pip" :class="{ active: s === 1 }">
          <div class="pip-dot"></div>
          <div class="pip-label">Step {{ s }}</div>
        </div>
      </div>
    </div>

    <!-- -- FORM BODY -- -->
    <div class="form-body">

      <!-- -- SECTION 1: Assessment Info -- -->
      <div class="form-card" style="--delay:0.05s">
        <div class="card-header">
          <div class="card-num">01</div>
          <div class="card-heading">
            <h2>Assessment Information</h2>
            <p>Basic identification details for this ERA assessment</p>
          </div>
        </div>
        <div class="card-body">
          <div class="field-grid">
            <!-- Assessor Name -->
            <div class="field-group">
              <label class="field-label">Assessor Name <span class="req">*</span></label>
              <input v-model="assessorName" class="field-input" placeholder="Enter full name of assessor" />
            </div>

            <!-- Department -->
            <div class="field-group">
              <label class="field-label">Department <span class="req">*</span></label>
              <input v-model="department" class="field-input" placeholder="e.g. Logistic – BMW Chassis" />
            </div>

            <!-- Date Range -->
            <div class="field-group full">
              <label class="field-label">Assessment Date Range <span class="req">*</span></label>
              <div class="date-row">
                <div class="date-field">
                  <span class="date-prefix">From</span>
                  <input type="date" v-model="startDate" class="field-input date-in" />
                </div>
                <div class="date-sep-line">
                  <svg width="20" height="2" viewBox="0 0 20 2"><line x1="0" y1="1" x2="20" y2="1" stroke="#c8cdd5" stroke-width="1.5"/></svg>
                </div>
                <div class="date-field">
                  <span class="date-prefix">To</span>
                  <input type="date" v-model="endDate" class="field-input date-in" />
                </div>
                <div v-if="formattedDateRange" class="date-preview">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                  {{ formattedDateRange }}
                </div>
              </div>
            </div>

            <!-- Working Hours -->
            <div class="field-group">
              <label class="field-label">Working Hours</label>
              <input v-model="workingHours" class="field-input" placeholder="e.g. 8.00am – 5.30pm" />
            </div>

            <!-- Breaks -->
            <div class="field-group">
              <label class="field-label">Break Schedule</label>
              <input v-model="breaks" class="field-input" placeholder="e.g. 10.00am – 10.10am, 1.00pm – 2.00pm" />
            </div>
          </div>
        </div>
      </div>

      <!-- -- SECTION 2: Processes & Tasks -- -->
      <div class="form-card" style="--delay:0.1s">
        <div class="card-header">
          <div class="card-num">02</div>
          <div class="card-heading">
            <h2>Task Description</h2>
            <p>Define all work processes and associated tasks for this assessment</p>
          </div>
          <button class="add-process-btn" @click="addProcess">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add Process
          </button>
        </div>

        <div class="card-body">
          <div
            v-for="(process, pIdx) in processes"
            :key="pIdx"
            class="process-card"
            :style="`--pIdx: ${pIdx}`"
          >
            <!-- Process Header -->
            <div class="process-header">
              <div class="process-badge">Process {{ pIdx + 1 }}</div>
              <div class="process-name-wrap">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                <input
                  v-model="process.name"
                  class="process-name-input"
                  placeholder="Process / Area name (e.g. Welding Line, Assembly Station)"
                />
              </div>
              <button
                class="danger-ghost-btn"
                @click="removeProcess(pIdx)"
                :disabled="processes.length <= 1"
                title="Remove process"
              >
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                Remove
              </button>
            </div>

            <!-- Tasks Table -->
            <div class="tasks-wrap">
              <table class="tasks-table">
                <thead>
                  <tr>
                    <th class="col-no">#</th>
                    <th class="col-title">Task Title & Description</th>
                    <th class="col-activities">Worker's Activities</th>
                    <th class="col-action">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(task, tIdx) in process.tasks" :key="tIdx" class="task-row">
                    <td class="cell-no">
                      <span class="row-num">{{ task.row_number }}</span>
                    </td>
                    <td class="cell-title">
                      <input
                        v-model="task.title"
                        class="task-in title-in"
                        placeholder="Task title"
                      />
                      <input
                        v-model="task.description"
                        class="task-in desc-in"
                        placeholder="Short description"
                      />
                    </td>
                    <td class="cell-activities">
                      <div class="activity-editor">
                        <div class="fmt-btns">
                          <button type="button" class="fmt-btn" @click="insertBullet(pIdx, tIdx)">
                            <span>•</span> Bullet
                          </button>
                          <button type="button" class="fmt-btn" @click="insertNumber(pIdx, tIdx)">
                            <span>1.</span> Number
                          </button>
                        </div>
                        <textarea
                          v-model="task.worker_activities"
                          class="activity-textarea"
                          placeholder="Describe worker activities…"
                          @keydown="handleKeydown($event, pIdx, tIdx)"
                        ></textarea>
                      </div>
                    </td>
                    <td class="cell-action">
                      <button
                        class="remove-task-btn"
                        @click="removeTask(pIdx, tIdx)"
                        :disabled="process.tasks.length <= 1"
                      >
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="process-footer">
              <button class="add-task-btn" @click="addTask(pIdx)">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add Task to "{{ process.name || 'This Process' }}"
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- -- SECTION 3: Photos -- -->
      <div class="form-card" style="--delay:0.15s">
        <div class="card-header">
          <div class="card-num">03</div>
          <div class="card-heading">
            <h2>Site Photography</h2>
            <p>Upload photos per task to document current workplace conditions</p>
          </div>
        </div>

        <div class="card-body">
          <div v-if="photoGroups.length === 0" class="photos-empty">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
            <span>Photo groups will appear once you add tasks in Section 02</span>
          </div>

          <div v-else class="photo-groups-grid">
            <div
              v-for="(group, groupIndex) in photoGroups"
              :key="groupIndex"
              class="photo-group-card"
            >
              <div class="photo-group-header">
                <div class="photo-group-badge">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                </div>
                <div class="photo-group-meta">
                  <div class="photo-group-process">{{ group.process_name || '—' }}</div>
                  <div class="photo-group-task">
                    {{ group.task_title || 'Unnamed Task' }}
                    <span v-if="group.task_description" class="photo-group-desc"> — {{ group.task_description }}</span>
                  </div>
                </div>
              </div>

              <label class="upload-zone" :class="{ 'has-files': group.photos.length > 0 }">
                <input
                  type="file"
                  multiple
                  class="hidden-file"
                  @change="handlePhotoChange($event, groupIndex)"
                />
                <div v-if="group.photos.length === 0" class="upload-prompt">
                  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg>
                  <span class="upload-text">Click to upload photos</span>
                  <span class="upload-hint">JPG, PNG supported</span>
                </div>
                <div v-else class="upload-prompt">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                  <span class="upload-text uploaded">{{ group.photos.length }} photo{{ group.photos.length > 1 ? 's' : '' }} selected</span>
                  <span class="upload-hint">Click to add/change</span>
                </div>
              </label>

              <div v-if="group.previews && group.previews.length > 0" class="preview-wrap">
                <div class="preview-actions">
                  <button
                    type="button"
                    class="photo-clear-btn"
                    @click="clearGroupPhotos(groupIndex)"
                  >
                    Remove all
                  </button>
                </div>

                <div class="preview-strip">
                  <div
                    v-for="(src, pi) in group.previews"
                    :key="`${src}-${pi}`"
                    class="preview-thumb-wrap"
                  >
                    <img
                      :src="src"
                      class="preview-thumb"
                      :alt="`Preview ${pi + 1}`"
                    />
                    <button
                      type="button"
                      class="remove-photo-btn"
                      :aria-label="`Remove photo ${pi + 1}`"
                      @click="removeGroupPhoto(groupIndex, pi)"
                    >
                      &times;
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- -- SUBMIT -- -->
      <div class="submit-section" style="--delay:0.2s">
        <div class="submit-note">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          After submitting, you'll proceed to <strong>Step 2: Ergonomic Risk Factor Checklist</strong>
        </div>
        <button class="submit-btn" @click="submitForm" :disabled="isSubmitting">
          <span v-if="!isSubmitting">
            Submit & Continue to Step 2
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
          </span>
          <span v-else class="submitting-state">
            <span class="mini-spinner"></span>
            Submitting Assessment…
          </span>
        </button>
      </div>

    </div>
  </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&family=Figtree:wght@400;500;600&display=swap');

.era-page {
  --navy: #0b1a2a;
  --navy-mid: #17324f;
  --navy-light: #244b73;
  --accent: #e8a020;
  --accent-light: #fef3dc;
  --accent-dim: rgba(232,160,32,0.15);
  --teal: #0e7c72;
  --teal-light: #e0f4f2;
  --surface: #ffffff;
  --surface-2: #f2f6fb;
  --border: #d7e0eb;
  --border-strong: #bac8d7;
  --text: #0f1e2e;
  --text-mid: #344658;
  --text-soft: #66798d;
  --danger: #c0392b;
  --danger-bg: #fff5f4;
  --radius: 10px;
  --radius-sm: 6px;
  --shadow-card: 0 2px 8px rgba(15,30,46,0.08), 0 10px 24px rgba(15,30,46,0.08);
  --shadow-hover: 0 4px 14px rgba(15,30,46,0.12), 0 12px 28px rgba(15,30,46,0.12);
}

* { box-sizing: border-box; margin: 0; padding: 0; }

.era-page {
  font-family: 'Figtree', sans-serif;
  font-size: 14px;
  color: var(--text);
  display: flex;
  flex-direction: column;
  gap: 0;
  animation: fadeUp 0.4s ease both;
}

@keyframes fadeUp {
  from { opacity: 0; transform: translateY(10px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* -- PAGE HERO -- */
.page-hero {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 24px;
  padding: 28px 32px 24px;
  background: linear-gradient(135deg, #0b1a2a 0%, #17324f 58%, #224f7a 100%);
  border-radius: var(--radius) var(--radius) 0 0;
  position: relative;
  overflow: hidden;
}
.page-hero::before {
  content: '';
  position: absolute;
  top: -40px; right: -40px;
  width: 200px; height: 200px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(232,160,32,0.18) 0%, transparent 70%);
  pointer-events: none;
}
.page-hero::after {
  content: 'ERA';
  position: absolute;
  right: 24px;
  bottom: -16px;
  font-family: 'Sora', sans-serif;
  font-size: 80px;
  font-weight: 800;
  color: rgba(255,255,255,0.04);
  letter-spacing: -2px;
  pointer-events: none;
  user-select: none;
}

.hero-left { display: flex; flex-direction: column; gap: 8px; }
.hero-tag {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  border-radius: 20px;
  background: rgba(232,160,32,0.28);
  border: 1px solid rgba(232,160,32,0.55);
  font-size: 11px;
  font-weight: 600;
  color: var(--accent);
  letter-spacing: 0.06em;
  text-transform: uppercase;
  width: fit-content;
}
.tag-dot {
  width: 6px; height: 6px;
  border-radius: 50%;
  background: var(--accent);
  animation: pulse 2s ease-in-out infinite;
}
@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.4; }
}

.hero-title {
  font-family: 'Sora', sans-serif;
  font-size: 26px;
  font-weight: 700;
  color: #f7fbff;
  line-height: 1.2;
  text-shadow: 0 1px 2px rgba(0,0,0,0.2);
}
.hero-sub {
  font-size: 13px;
  color: rgba(231,241,251,0.96);
  line-height: 1.6;
  max-width: 520px;
  text-shadow: 0 1px 1px rgba(0,0,0,0.12);
}

/* Step pip indicators */
.hero-steps {
  display: flex;
  gap: 6px;
  align-items: center;
  flex-shrink: 0;
  padding-top: 4px;
}
.step-pip {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  opacity: 0.65;
  transition: opacity 0.2s;
}
.step-pip.active { opacity: 1; }
.pip-dot {
  width: 10px; height: 10px;
  border-radius: 50%;
  background: rgba(255,255,255,0.4);
  border: 2px solid rgba(255,255,255,0.25);
}
.step-pip.active .pip-dot {
  background: var(--accent);
  border-color: var(--accent);
  box-shadow: 0 0 8px var(--accent);
}
.pip-label {
  font-size: 9px;
  color: rgba(255,255,255,0.78);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  white-space: nowrap;
}
.step-pip.active .pip-label { color: var(--accent); }

/* -- FORM BODY -- */
.form-body {
  display: flex;
  flex-direction: column;
  gap: 4px;
  background: var(--surface-2);
  border-radius: 0 0 var(--radius) var(--radius);
  padding: 24px 24px 32px;
}

/* -- FORM CARD -- */
.form-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  box-shadow: var(--shadow-card);
  overflow: hidden;
  animation: fadeUp 0.4s ease both;
  animation-delay: var(--delay, 0s);
  margin-bottom: 16px;
}
.form-card:last-child { margin-bottom: 0; }

.card-header {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  padding: 18px 22px;
  border-bottom: 1px solid var(--border);
  background: linear-gradient(180deg, #ffffff 0%, #f9fbfe 100%);
}
.card-num {
  font-family: 'Sora', sans-serif;
  font-size: 28px;
  font-weight: 800;
  color: #8da0b5;
  line-height: 1;
  flex-shrink: 0;
  letter-spacing: -1px;
  padding-top: 2px;
}
.card-heading { flex: 1; }
.card-heading h2 {
  font-family: 'Sora', sans-serif;
  font-size: 15px;
  font-weight: 700;
  color: var(--navy);
  margin-bottom: 3px;
}
.card-heading p { font-size: 12.5px; color: var(--text-soft); line-height: 1.5; }

.card-body { padding: 22px; }

/* -- FIELDS -- */
.field-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}
.field-group { display: flex; flex-direction: column; gap: 6px; }
.field-group.full { grid-column: 1 / -1; }

.field-label {
  font-size: 12px;
  font-weight: 600;
  color: #2e4357;
  text-transform: uppercase;
  letter-spacing: 0.06em;
}
.req { color: #e05050; margin-left: 2px; }

.field-input {
  height: 40px;
  padding: 0 13px;
  border: 1.5px solid #c7d4e2;
  border-radius: var(--radius-sm);
  background: var(--surface);
  font-family: inherit;
  font-size: 13.5px;
  color: var(--text);
  outline: none;
  transition: border-color 0.18s, box-shadow 0.18s;
}
.field-input:focus {
  border-color: #1f4d78;
  box-shadow: 0 0 0 3px rgba(31,77,120,0.16);
}
.field-input::placeholder { color: var(--text-soft); }

/* Date row */
.date-row {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}
.date-field { display: flex; align-items: center; gap: 8px; }
.date-prefix {
  font-size: 12px;
  font-weight: 600;
  color: var(--text-soft);
  white-space: nowrap;
}
.date-in { width: 160px; }
.date-sep-line { opacity: 0.5; }
.date-preview {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  background: var(--accent-light);
  border: 1px solid rgba(232,160,32,0.3);
  border-radius: 20px;
  font-size: 12.5px;
  font-weight: 600;
  color: #7e4b00;
  white-space: nowrap;
}

/* -- ADD ACTION BUTTONS -- */
.add-process-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  font-size: 12.5px;
  font-weight: 700;
  font-family: 'Sora', sans-serif;
  border: 1px solid #173b5b;
  border-radius: var(--radius-sm);
  background: linear-gradient(180deg, #1f4e77 0%, #173b5b 100%);
  color: #fff;
  cursor: pointer;
  transition: all 0.18s;
  white-space: nowrap;
  flex-shrink: 0;
  box-shadow: 0 3px 10px rgba(23, 59, 91, 0.35);
}
.add-process-btn:hover {
  transform: translateY(-1px);
  background: linear-gradient(180deg, #266496 0%, #1b466d 100%);
  box-shadow: 0 5px 14px rgba(23, 59, 91, 0.45);
}

.danger-ghost-btn {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 5px 12px; font-size: 12px; font-weight: 600; font-family: inherit;
  border: 1.5px solid var(--border-strong); border-radius: var(--radius-sm);
  background: transparent; color: var(--text-mid); cursor: pointer;
  transition: all 0.15s; flex-shrink: 0;
}
.danger-ghost-btn:hover:not(:disabled) {
  border-color: var(--danger); color: var(--danger); background: var(--danger-bg);
}
.danger-ghost-btn:disabled { opacity: 0.3; cursor: not-allowed; }

/* -- PROCESS CARDS -- */
.process-card {
  border: 1.5px solid #cfd9e6;
  border-radius: var(--radius);
  overflow: hidden;
  margin-bottom: 16px;
  transition: box-shadow 0.2s;
}
.process-card:last-child { margin-bottom: 0; }
.process-card:hover { box-shadow: var(--shadow-hover); }

.process-header {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  background: var(--surface-2);
  border-bottom: 1px solid var(--border);
}
.process-badge {
  font-size: 11px; font-weight: 700; text-transform: uppercase;
  letter-spacing: 0.07em; color: var(--navy-mid);
  background: rgba(26,48,68,0.1); padding: 3px 8px; border-radius: 20px;
  white-space: nowrap; flex-shrink: 0;
}
.process-name-wrap {
  display: flex; align-items: center; gap: 8px;
  flex: 1; background: var(--surface);
  border: 1.5px solid var(--border); border-radius: var(--radius-sm);
  padding: 0 12px; height: 36px; color: var(--text-soft);
  transition: border-color 0.15s;
}
.process-name-wrap:focus-within { border-color: var(--navy-mid); color: var(--navy); }
.process-name-input {
  flex: 1; border: none; outline: none; background: transparent;
  font-family: inherit; font-size: 13.5px; font-weight: 600; color: var(--navy);
}
.process-name-input::placeholder { font-weight: 400; color: var(--text-soft); }

/* -- TASKS TABLE -- */
.tasks-wrap { overflow-x: auto; }
.tasks-table {
  width: 100%;
  min-width: 680px;
  border-collapse: collapse;
}
.tasks-table thead tr {
  background: linear-gradient(180deg, #112840 0%, #0d2135 100%);
}
.tasks-table th {
  padding: 9px 12px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.07em;
  color: rgba(255,255,255,0.95);
  text-align: left;
  border: none;
}
.col-no     { width: 48px; text-align: center; }
.col-title  { width: 220px; }
.col-activities { }
.col-action { width: 52px; text-align: center; }

.task-row { border-bottom: 1px solid var(--border); transition: background 0.12s; }
.task-row:last-child { border-bottom: none; }
.task-row:hover { background: #eef4fb; }
.tasks-table td { padding: 8px 12px; vertical-align: top; }

.cell-no { text-align: center; vertical-align: middle; }
.row-num {
  display: inline-flex; align-items: center; justify-content: center;
  width: 26px; height: 26px; border-radius: 50%;
  background: var(--surface-2); border: 1.5px solid var(--border);
  font-size: 12px; font-weight: 700; color: var(--text-mid);
}

.task-in {
  width: 100%; border: none; background: transparent; outline: none;
  font-family: inherit; font-size: 13px; color: var(--text);
  padding: 2px 0;
}
.task-in:focus { background: var(--surface-2); border-radius: 4px; padding: 2px 6px; }
.title-in { font-weight: 600; margin-bottom: 5px; display: block; }
.desc-in   { font-size: 12px; color: var(--text-mid); display: block; }
.task-in::placeholder { color: var(--text-soft); font-weight: 400; }

/* Activity editor */
.activity-editor { display: flex; flex-direction: column; gap: 5px; }
.fmt-btns { display: flex; gap: 4px; }
.fmt-btn {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 3px 8px; font-size: 11px; font-weight: 600;
  font-family: inherit; border: 1px solid var(--border-strong);
  border-radius: 4px; background: var(--surface); color: var(--text-mid);
  cursor: pointer; transition: all 0.12s;
}
.fmt-btn:hover { background: var(--navy); color: #fff; border-color: var(--navy); }
.fmt-btn span { font-size: 12px; }

.activity-textarea {
  width: 100%; min-height: 70px; max-height: 200px;
  border: 1.5px solid #c7d4e2; border-radius: var(--radius-sm);
  padding: 8px 10px; font-family: inherit; font-size: 12.5px;
  color: var(--text); outline: none; resize: vertical;
  background: var(--surface); line-height: 1.6;
  transition: border-color 0.15s;
}
.activity-textarea:focus {
  border-color: #1f4d78;
  box-shadow: 0 0 0 3px rgba(31,77,120,0.14);
}
.activity-textarea::placeholder { color: var(--text-soft); }

.cell-action { text-align: center; vertical-align: middle; }
.remove-task-btn {
  width: 28px; height: 28px; border-radius: var(--radius-sm);
  border: 1px solid var(--border-strong); background: var(--surface);
  color: var(--text-soft); cursor: pointer; display: inline-flex;
  align-items: center; justify-content: center; transition: all 0.12s;
}
.remove-task-btn:hover:not(:disabled) {
  background: var(--danger-bg); border-color: var(--danger); color: var(--danger);
}
.remove-task-btn:disabled { opacity: 0.25; cursor: not-allowed; }

.process-footer {
  padding: 10px 16px;
  background: var(--surface-2);
  border-top: 1px solid var(--border);
}
.add-task-btn {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 8px 16px; font-size: 12.5px; font-weight: 700; font-family: 'Sora', sans-serif;
  border: 1px solid #173b5b; border-radius: var(--radius-sm);
  background: linear-gradient(180deg, #1f4e77 0%, #173b5b 100%);
  color: #fff; cursor: pointer;
  transition: all 0.18s;
  box-shadow: 0 3px 10px rgba(23, 59, 91, 0.35);
}
.add-task-btn:hover {
  transform: translateY(-1px);
  background: linear-gradient(180deg, #266496 0%, #1b466d 100%);
  box-shadow: 0 5px 14px rgba(23, 59, 91, 0.45);
}
.add-task-btn:focus-visible {
  outline: none;
  box-shadow: 0 0 0 3px rgba(31, 78, 119, 0.25), 0 3px 10px rgba(23, 59, 91, 0.35);
}

/* -- PHOTOS SECTION -- */
.photos-empty {
  display: flex; align-items: center; gap: 10px;
  padding: 28px; justify-content: center;
  border: 2px dashed var(--border-strong); border-radius: var(--radius);
  color: var(--text-soft); font-size: 13px;
}

.photo-groups-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 14px;
}
.photo-group-card {
  border: 1.5px solid var(--border);
  border-radius: var(--radius);
  overflow: hidden;
  transition: box-shadow 0.2s;
}
.photo-group-card:hover { box-shadow: var(--shadow-hover); }

.photo-group-header {
  display: flex; align-items: flex-start; gap: 10px;
  padding: 12px 14px;
  background: var(--surface-2); border-bottom: 1px solid var(--border);
}
.photo-group-badge {
  width: 28px; height: 28px; border-radius: 6px;
  background: var(--navy); color: #fff;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0; margin-top: 2px;
}
.photo-group-meta { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.photo-group-process { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-soft); }
.photo-group-task { font-size: 12.5px; font-weight: 600; color: var(--navy); line-height: 1.4; }
.photo-group-desc { font-weight: 400; color: var(--text-mid); }

.upload-zone {
  display: flex; flex-direction: column; align-items: center;
  justify-content: center; gap: 4px; padding: 16px;
  cursor: pointer; border-bottom: 1px solid var(--border);
  background: var(--surface); transition: background 0.15s;
  min-height: 80px;
}
.upload-zone:hover { background: #f0f6ff; }
.upload-zone.has-files { background: #f0fdf4; }
.hidden-file { display: none; }
.upload-prompt { display: flex; flex-direction: column; align-items: center; gap: 4px; color: var(--text-soft); }
.upload-text { font-size: 12.5px; font-weight: 600; color: var(--text-mid); }
.upload-text.uploaded { color: var(--teal); }
.upload-hint { font-size: 11px; color: var(--text-soft); }

.preview-wrap {
  background: var(--surface);
}
.preview-actions {
  display: flex; justify-content: flex-end; padding: 8px 8px 0;
}
.photo-clear-btn {
  border: 1px solid var(--border-strong);
  background: #fff;
  color: var(--text-mid);
  border-radius: 999px;
  padding: 3px 9px;
  font-size: 10.5px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.12s;
}
.photo-clear-btn:hover {
  border-color: var(--danger);
  color: var(--danger);
  background: var(--danger-bg);
}
.preview-strip {
  display: flex; flex-wrap: wrap; gap: 4px; padding: 8px;
  background: var(--surface);
}
.preview-thumb-wrap {
  position: relative;
}
.preview-thumb {
  width: 72px; height: 56px; object-fit: cover;
  border-radius: 5px; border: 1px solid var(--border);
}
.remove-photo-btn {
  position: absolute;
  top: -6px;
  right: -6px;
  width: 18px;
  height: 18px;
  border: 1px solid #fff;
  border-radius: 999px;
  background: rgba(15, 30, 46, 0.88);
  color: #fff;
  font-size: 13px;
  line-height: 1;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.12s;
}
.remove-photo-btn:hover {
  background: var(--danger);
}

/* -- SUBMIT -- */
.submit-section {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 18px 22px;
  background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  box-shadow: var(--shadow-card);
  flex-wrap: wrap;
  animation: fadeUp 0.4s ease both;
  animation-delay: var(--delay, 0s);
}
.submit-note {
  display: flex; align-items: flex-start; gap: 8px;
  font-size: 12.5px; color: var(--text-mid); line-height: 1.5;
  flex: 1;
}
.submit-note svg { flex-shrink: 0; margin-top: 1px; color: var(--navy-mid); }

.submit-btn {
  display: inline-flex; align-items: center; gap: 10px;
  padding: 12px 28px; font-size: 14px; font-weight: 700;
  font-family: 'Sora', sans-serif; border: none; border-radius: var(--radius-sm);
  background: linear-gradient(180deg, #15385a 0%, #0e2740 100%);
  color: #fff; cursor: pointer;
  transition: all 0.2s; letter-spacing: 0.02em;
  box-shadow: 0 4px 12px rgba(15,30,46,0.28);
  white-space: nowrap; flex-shrink: 0;
}
.submit-btn:hover:not(:disabled) {
  background: linear-gradient(180deg, #1b466f 0%, #123150 100%);
  box-shadow: 0 4px 16px rgba(15,30,46,0.35);
  transform: translateY(-1px);
}
.submit-btn:active:not(:disabled) { transform: translateY(0); }
.submit-btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
.submit-btn span { display: inline-flex; align-items: center; gap: 10px; }

.submitting-state { display: inline-flex; align-items: center; gap: 10px; }
.mini-spinner {
  width: 16px; height: 16px;
  border: 2px solid rgba(255,255,255,0.3);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* -- RESPONSIVE -- */
@media (max-width: 768px) {
  .page-hero { flex-direction: column; padding: 20px 16px; border-radius: 0; }
  .hero-steps { display: none; }
  .hero-title { font-size: 20px; }
  .form-body { padding: 16px 12px 24px; }
  .field-grid { grid-template-columns: 1fr; }
  .card-header { flex-wrap: wrap; }
  .date-row { flex-direction: column; align-items: flex-start; }
  .date-in { width: 100%; }
  .submit-section { flex-direction: column; align-items: flex-start; }
  .photo-groups-grid { grid-template-columns: 1fr; }
}
</style>
