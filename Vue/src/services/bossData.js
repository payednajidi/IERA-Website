const BOSS_ERA_TRANSFER_KEY = 'boss_era_transfer'

export const saveBossEraTransfer = (data) => {
  localStorage.setItem(BOSS_ERA_TRANSFER_KEY, JSON.stringify(data))
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
