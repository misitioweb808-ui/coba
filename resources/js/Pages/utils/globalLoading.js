// Simple DOM-based full-screen loading overlay
// Usage: import { showGlobalLoading, hideGlobalLoading } from '../utils/globalLoading.js'

export function showGlobalLoading() {
  if (document.getElementById('global-loading-overlay')) return

  const overlay = document.createElement('div')
  overlay.id = 'global-loading-overlay'
  overlay.setAttribute('aria-busy', 'true')
  overlay.setAttribute('aria-live', 'polite')
  overlay.style.position = 'fixed'
  overlay.style.inset = '0'
  overlay.style.background = 'rgba(0,0,0,0.4)'
  overlay.style.display = 'flex'
  overlay.style.alignItems = 'center'
  overlay.style.justifyContent = 'center'
  overlay.style.zIndex = '9999'

  const style = document.createElement('style')
  style.textContent = `@keyframes ag_spin { from { transform: rotate(0); } to { transform: rotate(360deg); } }`

  const spinner = document.createElement('div')
  spinner.style.width = '56px'
  spinner.style.height = '56px'
  spinner.style.border = '5px solid rgba(0,0,0,0)'
  spinner.style.borderTopColor = '#B91C1C' // red-700
  spinner.style.borderRightColor = '#B91C1C'
  spinner.style.borderRadius = '50%'
  spinner.style.animation = 'ag_spin 0.8s linear infinite'
  spinner.setAttribute('role', 'progressbar')
  spinner.setAttribute('aria-label', 'Cargando')

  overlay.appendChild(style)
  overlay.appendChild(spinner)
  document.body.appendChild(overlay)
}

export function hideGlobalLoading() {
  const overlay = document.getElementById('global-loading-overlay')
  if (overlay && overlay.parentNode) {
    overlay.parentNode.removeChild(overlay)
  }
}

