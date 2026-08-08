const BOSS_ERA_TRANSFER_KEY = 'boss_era_transfer'
const BOSS_GROUP_ID_KEY = 'boss_group_id'

export const saveBossEraTransfer = (data) => {
  localStorage.setItem(BOSS_ERA_TRANSFER_KEY, JSON.stringify(data))
}

export const clearBossEraTransfer = () => {
  localStorage.removeItem(BOSS_ERA_TRANSFER_KEY)
}

export const getBossEraTransfer = () => {
  try {
    const raw = localStorage.getItem(BOSS_ERA_TRANSFER_KEY)
    return raw ? JSON.parse(raw) : null
  } catch {
    return null
  }
}

export const flagBossToEraRedirect = () => {
  sessionStorage.setItem('boss_to_era_redirect', '1')
}

export const consumeBossToEraRedirect = () => {
  const flag = sessionStorage.getItem('boss_to_era_redirect')
  sessionStorage.removeItem('boss_to_era_redirect')
  return !!flag
}

// ── BOSS Group ID ─────────────────────────────────────────────────────────────
// Persists the current session's BOSS group across multiple BOSS form submissions.
// Cleared when the user explicitly starts a completely fresh session.

export const saveBossGroupId = (id) => {
  if (id != null) localStorage.setItem(BOSS_GROUP_ID_KEY, String(id))
}

export const getBossGroupId = () => localStorage.getItem(BOSS_GROUP_ID_KEY) ?? ''

export const clearBossGroupId = () => {
  localStorage.removeItem(BOSS_GROUP_ID_KEY)
}
