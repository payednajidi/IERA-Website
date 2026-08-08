<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../services/api'

const route  = useRoute()
const router = useRouter()

const files        = ref([])
const selectedId   = ref(null)
const loadingFiles = ref(false)
const listError    = ref('')
const mode         = ref('preview')
const viewMode     = ref('grid')
const searchQuery  = ref('')
const detailOpen   = ref(false)

// ── Computed ──────────────────────────────────────────────────────────────────
const selectedMeta = computed(() =>
  files.value.find(item => Number(item.id) === Number(selectedId.value))
)

const filteredFiles = computed(() => {
  if (!searchQuery.value.trim()) return files.value
  const q = searchQuery.value.toLowerCase()
  return files.value.filter(f =>
    f.assessor_name?.toLowerCase().includes(q) ||
    f.department?.toLowerCase().includes(q) ||
    f.assessment_date?.toLowerCase().includes(q) ||
    (f.process_names || []).some(p => p.toLowerCase().includes(q))
  )
})

const uniqueDepartmentCount = computed(() =>
  new Set(files.value.map(f => f.department).filter(Boolean)).size
)

const totalProcessCount = computed(() =>
  files.value.reduce((sum, f) => sum + (f.processes_count || 0), 0)
)

const latestAssessmentDate = computed(() => {
  if (!files.value.length) return '—'
  return [...files.value]
    .sort((a, b) => new Date(b.created_at) - new Date(a.created_at))[0]?.assessment_date || '—'
})

// ── Helpers ───────────────────────────────────────────────────────────────────
const normalizeMode = v => (String(v).toLowerCase() === 'edit' ? 'edit' : 'preview')

const getInitials = name => {
  if (!name) return 'ER'
  return name.split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase()
}

const CARD_COLORS = ['#3b7dd8','#2a7a52','#8b5cf6','#d97706','#dc2626','#0891b2','#7c3aed','#059669']
const fileColor = id => CARD_COLORS[Number(id) % CARD_COLORS.length]

const DEPT_PALETTES = [
  { bg:'#eff6ff', color:'#1d4ed8', border:'#bfdbfe' },
  { bg:'#f0fdf4', color:'#15803d', border:'#bbf7d0' },
  { bg:'#faf5ff', color:'#7e22ce', border:'#e9d5ff' },
  { bg:'#fff7ed', color:'#c2410c', border:'#fed7aa' },
  { bg:'#fef2f2', color:'#b91c1c', border:'#fecaca' },
  { bg:'#f0f9ff', color:'#0369a1', border:'#bae6fd' },
]

const deptPaletteMap = computed(() => {
  const map = {}
  ;[...new Set(files.value.map(f => f.department).filter(Boolean))].forEach((d, i) => {
    map[d] = DEPT_PALETTES[i % DEPT_PALETTES.length]
  })
  return map
})

const deptPalette = dept => deptPaletteMap.value[dept] || DEPT_PALETTES[0]

const formatRelDate = dateStr => {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  if (isNaN(d)) return ''
  const days = Math.floor((Date.now() - d) / 86400000)
  if (days === 0) return 'Today'
  if (days === 1) return 'Yesterday'
  if (days < 7)   return `${days}d ago`
  if (days < 30)  return `${Math.floor(days / 7)}w ago`
  if (days < 365) return `${Math.floor(days / 30)}mo ago`
  return `${Math.floor(days / 365)}y ago`
}

// ── Actions ───────────────────────────────────────────────────────────────────
const setMode     = next => { mode.value = normalizeMode(next); syncListRouteQuery() }
const setViewMode = next => {
  const n = String(next).toLowerCase()
  if (n !== 'grid' && n !== 'list') return
  viewMode.value = n; syncListRouteQuery()
}
const selectFile  = id => { selectedId.value = Number(id); detailOpen.value = true; syncListRouteQuery() }
const closeDetail = () => { selectedId.value = null; detailOpen.value = false; syncListRouteQuery() }

const openTotalInformation = () => {
  if (!selectedId.value) return
  router.push({
    name: 'era-assessment-files-total-information',
    params: { assessmentId: Number(selectedId.value) },
    query: { mode: mode.value, view: viewMode.value },
  })
}

const quickOpen = id => {
  router.push({
    name: 'era-assessment-files-total-information',
    params: { assessmentId: Number(id) },
    query: { mode: mode.value, view: viewMode.value },
  })
}

// ── Route sync ────────────────────────────────────────────────────────────────
const syncPanelStateFromRoute = () => {
  mode.value = normalizeMode(route.query.mode)
  const qv = String(route.query.view || '').toLowerCase()
  if (qv === 'grid' || qv === 'list') viewMode.value = qv
  const qs = Number(route.query.selected)
  if (!Number.isFinite(qs) || qs <= 0) { selectedId.value = null; detailOpen.value = false; return }
  if (!files.value.some(f => Number(f.id) === qs)) { selectedId.value = null; detailOpen.value = false; return }
  selectedId.value = qs; detailOpen.value = true
}

const syncListRouteQuery = () => {
  if (route.name !== 'era-assessment-files') return
  const nq = {}
  if (selectedId.value)     nq.selected = String(selectedId.value)
  if (mode.value === 'edit') nq.mode    = 'edit'
  if (viewMode.value === 'list') nq.view= 'list'
  const cq = {}
  if (route.query.selected) cq.selected = String(route.query.selected)
  if (route.query.mode)     cq.mode     = String(route.query.mode)
  if (route.query.view)     cq.view     = String(route.query.view)
  if (JSON.stringify(nq) === JSON.stringify(cq)) return
  router.replace({ name: 'era-assessment-files', query: nq })
}

// ── Data ──────────────────────────────────────────────────────────────────────
const loadFiles = async () => {
  loadingFiles.value = true
  listError.value = ''
  try {
    const res = await api.get('/era-assessments')
    files.value = Array.isArray(res.data?.assessments) ? res.data.assessments : []
    syncPanelStateFromRoute()
  } catch {
    listError.value = 'Unable to load ERA assessment files.'
  } finally {
    loadingFiles.value = false
  }
}

onMounted(loadFiles)
watch(() => route.fullPath, () => {
  if (route.name !== 'era-assessment-files') return
  syncPanelStateFromRoute()
})
</script>

<template>
  <div class="era-root">

    <!-- ══ Stats + Toolbar bar ══ -->
    <div class="top-bar">
      <div class="stats-group">
        <div class="stat">
          <span class="stat-val">{{ files.length }}</span>
          <span class="stat-lbl">Files</span>
        </div>
        <div class="stat-div"></div>
        <div class="stat">
          <span class="stat-val">{{ uniqueDepartmentCount }}</span>
          <span class="stat-lbl">Departments</span>
        </div>
        <div class="stat-div"></div>
        <div class="stat">
          <span class="stat-val">{{ totalProcessCount }}</span>
          <span class="stat-lbl">Processes</span>
        </div>
        <div class="stat-div"></div>
        <div class="stat">
          <span class="stat-val stat-date">{{ latestAssessmentDate }}</span>
          <span class="stat-lbl">Latest Assessment</span>
        </div>
      </div>

      <div class="toolbar-right">
        <div class="search-wrap">
          <svg class="search-ico" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
          </svg>
          <input v-model="searchQuery" placeholder="Search files, departments, processes…" class="search-input" />
        </div>

        <div class="view-btns">
          <button type="button" class="vb" :class="{ 'vb-on': viewMode==='grid' }" @click="setViewMode('grid')" title="Grid">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor">
              <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
              <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
            </svg>
          </button>
          <button type="button" class="vb" :class="{ 'vb-on': viewMode==='list' }" @click="setViewMode('list')" title="List">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/>
              <line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>
            </svg>
          </button>
        </div>

        <span class="item-count">{{ filteredFiles.length }} item{{ filteredFiles.length !== 1 ? 's' : '' }}</span>
      </div>
    </div>

    <!-- ══ Body ══ -->
    <div class="era-body">
      <div class="file-area">

        <div v-if="loadingFiles" class="blank-state">
          <div class="spinner"></div>
          <span>Loading files…</span>
        </div>

        <div v-else-if="listError" class="blank-state err">
          <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
          </svg>
          <span>{{ listError }}</span>
        </div>

        <div v-else-if="filteredFiles.length === 0" class="blank-state">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
            <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
          </svg>
          <span>No assessment files found</span>
        </div>

        <!-- ── GRID VIEW ── -->
        <div v-else-if="viewMode === 'grid'" class="card-grid">
          <div
            v-for="file in filteredFiles"
            :key="file.id"
            class="file-card"
            :class="{ 'card-sel': Number(file.id) === Number(selectedId) }"
            @click="selectFile(file.id)"
          >
            <div class="card-hd" :style="{ background: fileColor(file.id) }">
              <div class="card-avatar">{{ getInitials(file.assessor_name) }}</div>
              <div class="card-hd-right">
                <span class="card-era-badge">ERA</span>
                <span class="card-num">#{{ file.id }}</span>
              </div>
            </div>

            <div class="card-bd">
              <div class="card-name">{{ file.assessor_name || 'Unknown Assessor' }}</div>

              <div class="card-dept-row">
                <span
                  class="card-dept"
                  :style="{
                    background:  deptPalette(file.department).bg,
                    color:       deptPalette(file.department).color,
                    borderColor: deptPalette(file.department).border,
                  }"
                >{{ file.department || 'No department' }}</span>
              </div>

              <div class="card-meta-rows">
                <div class="meta-row">
                  <svg class="meta-ic" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/>
                    <line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                  </svg>
                  <span>{{ file.assessment_date || '—' }}</span>
                </div>
                <div v-if="file.working_hours" class="meta-row">
                  <svg class="meta-ic" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                  </svg>
                  <span>{{ file.working_hours }}</span>
                </div>
              </div>

              <div class="card-procs">
                <div class="procs-head">
                  <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <circle cx="12" cy="12" r="3"/>
                    <path d="M12 1v4M12 19v4M4.22 4.22l2.83 2.83M16.95 16.95l2.83 2.83M1 12h4M19 12h4M4.22 19.78l2.83-2.83M16.95 7.05l2.83-2.83"/>
                  </svg>
                  <span>{{ file.processes_count || 0 }} Process{{ (file.processes_count || 0) !== 1 ? 'es' : '' }}</span>
                </div>
                <div class="chip-row">
                  <span v-for="name in (file.process_names || []).slice(0, 2)" :key="name" class="proc-chip">{{ name }}</span>
                  <span v-if="(file.process_names || []).length > 2" class="chip-more">+{{ file.process_names.length - 2 }} more</span>
                  <span v-if="!(file.process_names || []).length" class="chip-none">No processes recorded</span>
                </div>
              </div>
            </div>

            <div class="card-ft">
              <span class="card-age">{{ formatRelDate(file.updated_at) }}</span>
              <button type="button" class="open-btn" @click.stop="quickOpen(file.id)">
                View Details
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                  <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
                </svg>
              </button>
            </div>
          </div>
        </div>

        <!-- ── LIST VIEW ── -->
        <div v-else class="list-view">
          <div class="lv-head">
            <span></span><span>Name</span><span>Department</span>
            <span>Processes</span><span>Date</span><span>ID</span>
          </div>
          <button
            v-for="file in filteredFiles"
            :key="file.id"
            type="button"
            class="lv-row"
            :class="{ 'lv-sel': Number(file.id) === Number(selectedId) }"
            @click="selectFile(file.id)"
          >
            <span class="lv-icon" :style="{ background: fileColor(file.id) }">{{ getInitials(file.assessor_name) }}</span>
            <span class="lv-name">{{ file.assessor_name || 'Unknown' }}</span>
            <span class="lv-dept">{{ file.department || '—' }}</span>
            <span class="lv-proc">
              <span v-for="p in (file.process_names || []).slice(0, 2)" :key="p" class="proc-chip sm">{{ p }}</span>
              <span v-if="(file.process_names || []).length > 2" class="chip-more">+{{ file.process_names.length - 2 }}</span>
              <span v-if="!(file.process_names || []).length" class="chip-none">—</span>
            </span>
            <span class="lv-date">{{ file.assessment_date }}</span>
            <span class="lv-id">#{{ file.id }}</span>
          </button>
        </div>

      </div>

      <!-- ── Detail panel ── -->
      <aside class="detail-panel" :class="{ open: detailOpen }">
        <div v-if="!detailOpen" class="dp-empty">
          <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
            <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
          </svg>
          <span>Select a file</span>
        </div>

        <template v-else>
          <div class="dp-hd">
            <div class="dp-avatar" :style="{ background: selectedMeta ? fileColor(selectedMeta.id) : '#3b7dd8' }">
              {{ getInitials(selectedMeta?.assessor_name) }}
            </div>
            <div class="dp-title-block">
              <div class="dp-title">{{ selectedMeta?.assessor_name }}</div>
              <div class="dp-sub">Assessment ID #{{ selectedMeta?.id }}</div>
            </div>
            <button type="button" class="dp-close" @click="closeDetail">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
              </svg>
            </button>
          </div>

          <div class="dp-mode-row">
            <button type="button" class="mode-btn" :class="{ 'mode-on': mode==='preview' }" @click="setMode('preview')">Preview</button>
            <button type="button" class="mode-btn" :class="{ 'mode-on': mode==='edit' }" @click="setMode('edit')">Edit</button>
            <button type="button" class="mode-btn mode-open" :disabled="!selectedId" @click="openTotalInformation">View Total Information</button>
          </div>

          <div class="dp-body">
            <div class="dp-field"><span class="dp-lbl">Assessor</span><span class="dp-val">{{ selectedMeta?.assessor_name || '—' }}</span></div>
            <div class="dp-field"><span class="dp-lbl">Date</span><span class="dp-val">{{ selectedMeta?.assessment_date || '—' }}</span></div>
            <div class="dp-field full"><span class="dp-lbl">Department</span><span class="dp-val">{{ selectedMeta?.department || '—' }}</span></div>
            <div v-if="selectedMeta?.working_hours" class="dp-field full">
              <span class="dp-lbl">Working Hours</span><span class="dp-val">{{ selectedMeta.working_hours }}</span>
            </div>
            <div class="dp-field full">
              <span class="dp-lbl">Processes ({{ selectedMeta?.processes_count || 0 }})</span>
              <div class="dp-proc-chips">
                <span v-for="p in (selectedMeta?.process_names || [])" :key="p" class="proc-chip">{{ p }}</span>
                <span v-if="!(selectedMeta?.process_names || []).length" class="chip-none">None recorded</span>
              </div>
            </div>
            <div class="dp-field full"><span class="dp-lbl">Mode</span><span class="dp-val">{{ mode === 'edit' ? 'Edit' : 'Preview' }}</span></div>
          </div>
        </template>
      </aside>
    </div>

    <!-- ══ Status bar ══ -->
    <div class="status-bar">
      <span>{{ filteredFiles.length }} item(s)</span>
      <span v-if="selectedMeta">{{ selectedMeta.assessor_name }} — {{ selectedMeta.department }}</span>
    </div>

  </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&display=swap');

* { box-sizing: border-box; }

.era-root {
  font-family: Arial, sans-serif;
  display: flex;
  flex-direction: column;
  height: 100%;
  background: #f0f2f5;
  overflow: hidden;
  border-top: 1px solid #d0d5dd;
}

/* ── Top bar ───────────────────────────────────────────────────────────────── */
.top-bar {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 0 20px;
  height: 60px;
  flex-shrink: 0;
  background: #fff;
  border-bottom: 1px solid #e0e4ea;
}

.stats-group {
  display: flex;
  align-items: center;
  flex-shrink: 0;
}

.stat {
  display: flex;
  flex-direction: column;
  padding: 0 20px;
  gap: 2px;
}

.stat-val  { font-size: 16px; font-weight: 700; color: #1e2a36; line-height: 1.1; }
.stat-date { font-size: 13px; }
.stat-lbl  { font-size: 10px; color: #9aa3ae; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }

.stat-div { width: 1px; height: 30px; background: #e4e8ee; flex-shrink: 0; }

.toolbar-right { display: flex; align-items: center; gap: 10px; margin-left: auto; }

.search-wrap {
  display: flex; align-items: center; gap: 7px;
  background: #f3f5f8; border: 1px solid #dde1e8; border-radius: 7px;
  padding: 6px 12px; color: #8a93a0; min-width: 280px;
}

.search-ico { flex-shrink: 0; }

.search-input {
  border: none; background: transparent; outline: none;
  font-size: 12.5px; font-family: inherit; width: 100%; color: #2f3e4d;
}
.search-input::placeholder { color: #9aa3ae; }

.view-btns {
  display: flex; background: #f0f2f5; border: 1px solid #dde1e8;
  border-radius: 7px; overflow: hidden;
}

.vb {
  padding: 6px 10px; border: none; background: transparent;
  cursor: pointer; color: #8a95a4; display: flex; align-items: center;
}
.vb.vb-on { background: #2f3e4d; color: #fff; }
.vb:hover:not(.vb-on) { background: #e4e8ee; }

.item-count { font-size: 12px; color: #8a95a4; white-space: nowrap; }

/* ── Body ──────────────────────────────────────────────────────────────────── */
.era-body { display: flex; flex: 1; overflow: hidden; min-height: 0; }

.file-area { flex: 1; min-width: 0; overflow-y: auto; padding: 16px 20px; }

/* ── States ────────────────────────────────────────────────────────────────── */
.blank-state {
  display: flex; flex-direction: column; align-items: center;
  justify-content: center; gap: 12px; height: 100%; min-height: 220px;
  color: #9aa3ae; font-size: 13px;
}
.blank-state.err { color: #c0392b; }

.spinner {
  width: 28px; height: 28px;
  border: 3px solid #e0e4ea; border-top-color: #3b7dd8;
  border-radius: 50%; animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Card Grid ─────────────────────────────────────────────────────────────── */
.card-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: 14px;
  align-content: start;
}

.file-card {
  display: flex; flex-direction: column;
  background: #fff; border: 2px solid #e4e8ee; border-radius: 12px;
  overflow: hidden; cursor: pointer;
  transition: border-color 0.15s, box-shadow 0.15s, transform 0.12s;
}
.file-card:hover {
  border-color: #a8c4f0;
  box-shadow: 0 6px 20px rgba(59,125,216,0.13);
  transform: translateY(-2px);
}
.file-card.card-sel { border-color: #3b7dd8; box-shadow: 0 6px 24px rgba(59,125,216,0.2); }

.card-hd {
  display: flex; align-items: center; justify-content: space-between;
  padding: 18px 18px 16px;
}
.card-avatar {
  width: 52px; height: 52px; border-radius: 12px;
  background: rgba(255,255,255,0.2); border: 2px solid rgba(255,255,255,0.3);
  display: flex; align-items: center; justify-content: center;
  color: #fff; font-size: 18px; font-weight: 700;
}
.card-hd-right { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; }
.card-era-badge {
  background: rgba(0,0,0,0.22); color: #fff; font-size: 9px;
  font-weight: 700; padding: 2px 6px; border-radius: 4px; letter-spacing: 0.08em;
}
.card-num { color: rgba(255,255,255,0.8); font-size: 12px; font-weight: 600; }

.card-bd { flex: 1; padding: 16px 18px 12px; display: flex; flex-direction: column; gap: 10px; }

.card-name { font-size: 15px; font-weight: 700; color: #1e2a36; line-height: 1.35; }

.card-dept-row { display: flex; }
.card-dept {
  display: inline-block; padding: 3px 11px; border-radius: 99px; border: 1px solid;
  font-size: 11.5px; font-weight: 600; max-width: 100%;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}

.card-meta-rows { display: flex; flex-direction: column; gap: 5px; }
.meta-row { display: flex; align-items: center; gap: 6px; font-size: 12.5px; color: #5a6578; }
.meta-ic  { color: #9aa3ae; flex-shrink: 0; }

.card-procs { display: flex; flex-direction: column; gap: 6px; border-top: 1px solid #f0f2f5; padding-top: 10px; }
.procs-head { display: flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #8a95a4; }
.chip-row   { display: flex; flex-wrap: wrap; gap: 5px; }

.proc-chip {
  display: inline-block; padding: 3px 9px;
  background: #f0f4ff; color: #3b5bdb; border: 1px solid #c5d5fb;
  border-radius: 99px; font-size: 11.5px; font-weight: 600;
  max-width: 180px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.proc-chip.sm { font-size: 11px; padding: 2px 7px; }

.chip-more {
  display: inline-block; padding: 3px 9px;
  background: #f4f6f9; color: #7a8694; border: 1px solid #dde1e8;
  border-radius: 99px; font-size: 11.5px; font-weight: 600;
}
.chip-none { font-size: 11.5px; color: #b0bac5; font-style: italic; }

.card-ft {
  display: flex; align-items: center; justify-content: space-between;
  padding: 10px 18px 14px; border-top: 1px solid #f0f2f5;
}
.card-age { font-size: 11.5px; color: #a0abb6; }

.open-btn {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 6px 13px; background: #2f3e4d; color: #fff;
  border: none; border-radius: 6px; font-size: 12px; font-weight: 600;
  font-family: inherit; cursor: pointer; transition: background 0.12s;
}
.open-btn:hover { background: #1e2a36; }

/* ── List View ─────────────────────────────────────────────────────────────── */
.list-view  { display: flex; flex-direction: column; gap: 2px; }

.lv-head {
  display: grid;
  grid-template-columns: 32px 1fr 200px 220px 130px 60px;
  padding: 7px 14px; font-size: 11px; font-weight: 700; color: #7a8694;
  text-transform: uppercase; letter-spacing: 0.06em;
  background: #e8ebef; border-radius: 7px; margin-bottom: 4px;
}
.lv-row {
  display: grid;
  grid-template-columns: 32px 1fr 200px 220px 130px 60px;
  gap: 0 10px; align-items: center; padding: 9px 14px;
  background: #fff; border: 1px solid #eaecf0; border-radius: 7px;
  cursor: pointer; font-family: inherit; font-size: 12.5px;
  color: #2f3e4d; text-align: left; transition: background 0.1s;
}
.lv-row:hover { background: #f0f6ff; border-color: #c5d9f5; }
.lv-row.lv-sel { background: #e8f0fd; border-color: #3b7dd8; }

.lv-icon {
  width: 30px; height: 30px; border-radius: 6px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  color: #fff; font-size: 10px; font-weight: 700;
}
.lv-name { font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.lv-dept, .lv-date { color: #6e7d8e; font-size: 12px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.lv-proc { display: flex; flex-wrap: wrap; gap: 4px; }
.lv-id   { color: #a0abb6; font-size: 11.5px; }

/* ── Detail Panel ──────────────────────────────────────────────────────────── */
.detail-panel {
  width: 0; overflow: hidden; border-left: 0 solid #dde1e8;
  background: #fff; display: flex; flex-direction: column;
  transition: width 0.28s cubic-bezier(0.4,0,0.2,1), border-left-width 0.28s;
  flex-shrink: 0;
}
.detail-panel.open { width: 340px; border-left-width: 1px; }

.dp-empty {
  display: flex; flex-direction: column; align-items: center;
  justify-content: center; gap: 10px; height: 100%;
  color: #b0bac5; font-size: 13px;
}

.dp-hd {
  display: flex; align-items: center; gap: 12px;
  padding: 14px 16px; border-bottom: 1px solid #eaecf0;
  background: #f8fafc; flex-shrink: 0;
}
.dp-avatar {
  width: 38px; height: 38px; border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  color: #fff; font-size: 13px; font-weight: 700; flex-shrink: 0;
  box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}
.dp-title-block { flex: 1; min-width: 0; }
.dp-title { font-size: 13.5px; font-weight: 700; color: #1e2a36; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.dp-sub   { font-size: 11px; color: #8a95a4; }
.dp-close {
  width: 26px; height: 26px; border: 1px solid #dde1e8; border-radius: 6px;
  background: #fff; cursor: pointer; display: flex; align-items: center;
  justify-content: center; color: #7a8694; flex-shrink: 0;
}
.dp-close:hover { background: #f0f2f5; }

.dp-mode-row { display: flex; gap: 5px; padding: 10px 14px; border-bottom: 1px solid #eaecf0; flex-shrink: 0; flex-wrap: wrap; }
.mode-btn {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 5px 10px; font-size: 12px; font-weight: 600;
  font-family: inherit; border: 1px solid #d0d5dd; border-radius: 5px;
  background: #fff; color: #4a5568; cursor: pointer;
}
.mode-btn:hover { background: #f4f6f8; }
.mode-btn.mode-on   { background: #2f3e4d; border-color: #2f3e4d; color: #fff; }
.mode-btn.mode-open { margin-left: auto; background: #2a7a52; border-color: #2a7a52; color: #fff; }
.mode-btn:disabled  { opacity: 0.6; cursor: not-allowed; }

.dp-body { flex: 1; overflow-y: auto; padding: 14px; display: flex; flex-direction: column; gap: 8px; }
.dp-field {
  display: flex; flex-direction: column; gap: 4px;
  background: #f8fafc; border: 1px solid #e0e4ea; border-radius: 7px; padding: 9px 12px;
}
.dp-lbl { font-size: 10.5px; font-weight: 700; color: #8a95a4; text-transform: uppercase; letter-spacing: 0.05em; }
.dp-val { font-size: 13px; color: #1e2a36; font-weight: 600; }
.dp-proc-chips { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 5px; }

/* ── Status Bar ────────────────────────────────────────────────────────────── */
.status-bar {
  display: flex; justify-content: space-between;
  padding: 5px 16px; background: #e8ebef;
  border-top: 1px solid #d0d5dd; font-size: 11.5px; color: #7a8694; flex-shrink: 0;
}

/* ── Scrollbars ────────────────────────────────────────────────────────────── */
.file-area::-webkit-scrollbar, .dp-body::-webkit-scrollbar { width: 5px; }
.file-area::-webkit-scrollbar-track, .dp-body::-webkit-scrollbar-track { background: transparent; }
.file-area::-webkit-scrollbar-thumb, .dp-body::-webkit-scrollbar-thumb { background: #c8cdd5; border-radius: 3px; }
</style>
