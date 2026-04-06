<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../services/api'

const route = useRoute()
const router = useRouter()

const files = ref([])
const selectedId = ref(null)
const loadingFiles = ref(false)
const listError = ref('')
const mode = ref('preview')
const viewMode = ref('grid') // grid | list
const searchQuery = ref('')
const detailOpen = ref(false)

const selectedMeta = computed(() => files.value.find(item => Number(item.id) === Number(selectedId.value)))

const filteredFiles = computed(() => {
  if (!searchQuery.value.trim()) return files.value
  const q = searchQuery.value.toLowerCase()
  return files.value.filter(file =>
    file.assessor_name?.toLowerCase().includes(q) ||
    file.department?.toLowerCase().includes(q) ||
    file.assessment_date?.toLowerCase().includes(q)
  )
})

const normalizeMode = value => (String(value).toLowerCase() === 'edit' ? 'edit' : 'preview')

const setMode = next => {
  mode.value = normalizeMode(next)
  syncListRouteQuery()
}

const setViewMode = next => {
  const normalized = String(next).toLowerCase()
  if (normalized !== 'grid' && normalized !== 'list') return
  viewMode.value = normalized
  syncListRouteQuery()
}

const selectFile = id => {
  selectedId.value = Number(id)
  detailOpen.value = true
  syncListRouteQuery()
}

const closeDetail = () => {
  selectedId.value = null
  detailOpen.value = false
  syncListRouteQuery()
}

const openTotalInformation = () => {
  if (!selectedId.value) return
  router.push({
    name: 'era-assessment-files-total-information',
    params: { assessmentId: Number(selectedId.value) },
    query: {
      mode: mode.value,
      view: viewMode.value,
    },
  })
}

const syncPanelStateFromRoute = () => {
  mode.value = normalizeMode(route.query.mode)

  const queryView = String(route.query.view || '').toLowerCase()
  if (queryView === 'grid' || queryView === 'list') {
    viewMode.value = queryView
  }

  const querySelected = Number(route.query.selected)
  if (!Number.isFinite(querySelected) || querySelected <= 0) {
    selectedId.value = null
    detailOpen.value = false
    return
  }

  const exists = files.value.some(file => Number(file.id) === querySelected)
  if (!exists) {
    selectedId.value = null
    detailOpen.value = false
    return
  }

  selectedId.value = querySelected
  detailOpen.value = true
}

const syncListRouteQuery = () => {
  if (route.name !== 'era-assessment-files') return

  const nextQuery = {}
  if (selectedId.value) nextQuery.selected = String(selectedId.value)
  if (mode.value === 'edit') nextQuery.mode = 'edit'
  if (viewMode.value === 'list') nextQuery.view = 'list'

  const currentQuery = {}
  if (route.query.selected) currentQuery.selected = String(route.query.selected)
  if (route.query.mode) currentQuery.mode = String(route.query.mode)
  if (route.query.view) currentQuery.view = String(route.query.view)

  if (JSON.stringify(nextQuery) === JSON.stringify(currentQuery)) return

  router.replace({
    name: 'era-assessment-files',
    query: nextQuery,
  })
}

const loadFiles = async () => {
  loadingFiles.value = true
  listError.value = ''

  try {
    const response = await api.get('/era-assessments')
    files.value = Array.isArray(response.data?.assessments) ? response.data.assessments : []
    syncPanelStateFromRoute()
  } catch (error) {
    console.error(error)
    listError.value = 'Unable to load ERA assessment files.'
  } finally {
    loadingFiles.value = false
  }
}

const getInitials = name => {
  if (!name) return 'ER'
  return name
    .split(' ')
    .slice(0, 2)
    .map(word => word[0])
    .join('')
    .toUpperCase()
}

const FILE_COLORS = ['#3b7dd8', '#2a7a52', '#8b5cf6', '#d97706', '#dc2626', '#0891b2', '#7c3aed', '#059669']
const fileColor = id => FILE_COLORS[Number(id) % FILE_COLORS.length]

onMounted(loadFiles)

watch(
  () => route.fullPath,
  () => {
    if (route.name !== 'era-assessment-files') return
    syncPanelStateFromRoute()
  }
)
</script>

<template>
  <div class="explorer-root">
    <div class="toolbar">
      <div class="toolbar-left">
        <div class="address-bar">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
            <polyline points="9 22 9 12 15 12 15 22" />
          </svg>
          <span class="addr-sep">></span>
          <span class="addr-part">ERA System</span>
          <span class="addr-sep">></span>
          <span class="addr-part active">Assessment Files</span>
        </div>
      </div>

      <div class="toolbar-center">
        <div class="search-box">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8" />
            <line x1="21" y1="21" x2="16.65" y2="16.65" />
          </svg>
          <input v-model="searchQuery" placeholder="Search files..." class="search-input" />
        </div>
      </div>

      <div class="toolbar-right">
        <div class="view-toggle">
          <button type="button" class="view-btn" :class="{ active: viewMode === 'grid' }" @click="setViewMode('grid')" title="Grid view">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
              <rect x="3" y="3" width="7" height="7" rx="1" />
              <rect x="14" y="3" width="7" height="7" rx="1" />
              <rect x="3" y="14" width="7" height="7" rx="1" />
              <rect x="14" y="14" width="7" height="7" rx="1" />
            </svg>
          </button>
          <button type="button" class="view-btn" :class="{ active: viewMode === 'list' }" @click="setViewMode('list')" title="List view">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="8" y1="6" x2="21" y2="6" />
              <line x1="8" y1="12" x2="21" y2="12" />
              <line x1="8" y1="18" x2="21" y2="18" />
              <line x1="3" y1="6" x2="3.01" y2="6" />
              <line x1="3" y1="12" x2="3.01" y2="12" />
              <line x1="3" y1="18" x2="3.01" y2="18" />
            </svg>
          </button>
        </div>
        <div class="file-count">{{ filteredFiles.length }} item{{ filteredFiles.length !== 1 ? 's' : '' }}</div>
      </div>
    </div>

    <div class="explorer-body">
      <div class="file-area">
        <div v-if="loadingFiles" class="empty-state">
          <div class="spinner"></div>
          <span>Loading files...</span>
        </div>

        <div v-else-if="listError" class="empty-state error-state">
          <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <circle cx="12" cy="12" r="10" />
            <line x1="12" y1="8" x2="12" y2="12" />
            <line x1="12" y1="16" x2="12.01" y2="16" />
          </svg>
          <span>{{ listError }}</span>
        </div>

        <div v-else-if="filteredFiles.length === 0" class="empty-state">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
            <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z" />
          </svg>
          <span>No assessment files found</span>
        </div>

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
                <rect width="48" height="60" rx="4" fill="rgba(255,255,255,0.12)" />
                <path d="M30 2 L30 14 L42 14" stroke="rgba(255,255,255,0.3)" stroke-width="1.5" fill="none" />
                <rect x="8" y="22" width="24" height="2" rx="1" fill="rgba(255,255,255,0.3)" />
                <rect x="8" y="28" width="32" height="2" rx="1" fill="rgba(255,255,255,0.25)" />
                <rect x="8" y="34" width="28" height="2" rx="1" fill="rgba(255,255,255,0.2)" />
                <rect x="8" y="40" width="20" height="2" rx="1" fill="rgba(255,255,255,0.15)" />
              </svg>
              <div class="file-initials">{{ getInitials(file.assessor_name) }}</div>
              <div class="era-badge">ERA</div>
            </div>
            <div class="file-name">{{ file.assessor_name || 'Unknown' }}</div>
            <div class="file-meta">{{ file.assessment_date }}</div>
            <div class="file-dept">{{ file.department }}</div>
          </button>
        </div>

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

      <aside class="detail-panel" :class="{ open: detailOpen }">
        <div v-if="!detailOpen" class="detail-empty">
          <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
            <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z" />
          </svg>
          <span>Select a file to continue</span>
        </div>

        <template v-else>
          <div class="panel-header">
            <div class="panel-file-icon" :style="{ background: selectedMeta ? fileColor(selectedMeta.id) : '#3b7dd8' }">
              {{ getInitials(selectedMeta?.assessor_name) }}
            </div>
            <div class="panel-title-block">
              <div class="panel-title">{{ selectedMeta?.assessor_name }}</div>
              <div class="panel-subtitle">Assessment ID #{{ selectedMeta?.id }}</div>
            </div>
            <button type="button" class="close-btn" @click="closeDetail" title="Close">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <line x1="18" y1="6" x2="6" y2="18" />
                <line x1="6" y1="6" x2="18" y2="18" />
              </svg>
            </button>
          </div>

          <div class="panel-actions">
            <button type="button" class="pa-btn" :class="{ 'pa-active': mode === 'preview' }" @click="setMode('preview')">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                <circle cx="12" cy="12" r="3" />
              </svg>
              Preview
            </button>
            <button type="button" class="pa-btn" :class="{ 'pa-active': mode === 'edit' }" @click="setMode('edit')">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
              </svg>
              Edit
            </button>
            <button type="button" class="pa-btn pa-open" :disabled="!selectedId" @click="openTotalInformation">
              View Total Information
            </button>
          </div>

          <div class="panel-content">
            <div class="summary-grid">
              <div class="summary-item">
                <span class="summary-label">Assessor</span>
                <span class="summary-value">{{ selectedMeta?.assessor_name || '-' }}</span>
              </div>
              <div class="summary-item">
                <span class="summary-label">Date</span>
                <span class="summary-value">{{ selectedMeta?.assessment_date || '-' }}</span>
              </div>
              <div class="summary-item full">
                <span class="summary-label">Department</span>
                <span class="summary-value">{{ selectedMeta?.department || '-' }}</span>
              </div>
              <div class="summary-item full">
                <span class="summary-label">Mode</span>
                <span class="summary-value">{{ mode === 'edit' ? 'Edit' : 'Preview' }}</span>
              </div>
            </div>

            <p class="summary-note">
              The full assessment details now open on a dedicated wide page for better readability.
              Click "View Total Information" to continue.
            </p>
          </div>
        </template>
      </aside>
    </div>

    <div class="status-bar">
      <span>{{ filteredFiles.length }} item(s)</span>
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
.toolbar-center { flex: 1; max-width: 360px; }
.toolbar-right { display: flex; align-items: center; gap: 10px; }

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
}

.view-btn.active { background: #2f3e4d; color: #fff; }
.view-btn:hover:not(.active) { background: #e4e8ee; }

.file-count { font-size: 12px; color: #8a95a4; white-space: nowrap; }

.explorer-body {
  display: flex;
  flex: 1;
  overflow: hidden;
}

.file-area {
  flex: 1;
  overflow-y: auto;
  padding: 16px;
  min-width: 0;
}

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

.file-dept {
  font-size: 10px;
  color: #aab0b8;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 100%;
}

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
  text-align: left;
}

.list-row:hover { background: #f0f6ff; border-color: #c5d9f5; }
.list-row.selected { background: #e8f0fd; border-color: #3b7dd8; }

.lr-icon {
  width: 28px;
  height: 28px;
  border-radius: 5px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 10px;
  font-weight: 700;
  flex-shrink: 0;
}

.lr-name { font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.lr-date, .lr-dept { color: #6e7d8e; font-size: 12px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.lr-id { color: #a0abb6; font-size: 11.5px; }
.lh-name, .lh-date, .lh-dept, .lh-id { overflow: hidden; }

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
  width: 480px;
  border-left-width: 1px;
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
  width: 40px;
  height: 40px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 14px;
  font-weight: 700;
  flex-shrink: 0;
  box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

.panel-title-block { flex: 1; min-width: 0; }
.panel-title { font-size: 14px; font-weight: 700; color: #1e2a36; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.panel-subtitle { font-size: 11.5px; color: #8a95a4; }

.close-btn {
  width: 28px;
  height: 28px;
  border: 1px solid #dde1e8;
  border-radius: 6px;
  background: #fff;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #7a8694;
  flex-shrink: 0;
}

.close-btn:hover { background: #f0f2f5; color: #2f3e4d; }

.panel-actions {
  display: flex;
  gap: 6px;
  padding: 10px 16px;
  border-bottom: 1px solid #eaecf0;
  flex-shrink: 0;
}

.pa-btn {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 6px 12px;
  font-size: 12px;
  font-weight: 600;
  font-family: inherit;
  border: 1px solid #d0d5dd;
  border-radius: 5px;
  background: #fff;
  color: #4a5568;
  cursor: pointer;
}

.pa-btn:hover { background: #f4f6f8; }
.pa-btn.pa-active { background: #2f3e4d; border-color: #2f3e4d; color: #fff; }
.pa-btn.pa-open { margin-left: auto; background: #2a7a52; border-color: #2a7a52; color: #fff; }
.pa-btn:disabled { opacity: 0.6; cursor: not-allowed; }

.panel-content {
  flex: 1;
  overflow-y: auto;
  padding: 14px 16px;
}

.summary-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.summary-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
  background: #f8fafc;
  border: 1px solid #e0e4ea;
  border-radius: 8px;
  padding: 10px;
}

.summary-item.full { grid-column: 1 / -1; }

.summary-label {
  font-size: 11px;
  font-weight: 700;
  color: #8a95a4;
  text-transform: uppercase;
  letter-spacing: 0.06em;
}

.summary-value {
  font-size: 13px;
  color: #1e2a36;
  font-weight: 600;
}

.summary-note {
  margin-top: 14px;
  border-radius: 8px;
  border: 1px solid #d0e3f9;
  background: #eff6ff;
  color: #1e3a5f;
  padding: 10px 12px;
  font-size: 12.5px;
  line-height: 1.45;
}

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

.file-area::-webkit-scrollbar,
.panel-content::-webkit-scrollbar { width: 6px; }

.file-area::-webkit-scrollbar-track,
.panel-content::-webkit-scrollbar-track { background: transparent; }

.file-area::-webkit-scrollbar-thumb,
.panel-content::-webkit-scrollbar-thumb { background: #c8cdd5; border-radius: 3px; }
</style>
