// Display formatters used across index pages and detail views.
// Inputs are forgiving: ISO timestamps, "YYYY-MM-DD", Date objects, null, undefined.

const MONTH_SHORT = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']

function toDate(value) {
    if (!value) return null
    if (value instanceof Date) return Number.isNaN(value.getTime()) ? null : value
    const d = new Date(value)
    return Number.isNaN(d.getTime()) ? null : d
}

/**
 * Format a date as "04 May 2026" (the project default).
 * Variants: 'short' → "04 May", 'long' → "04 May 2026, 14:30", 'iso' → "2026-05-04".
 */
export function formatDate(value, variant = 'medium') {
    const d = toDate(value)
    if (!d) return ''
    const day = String(d.getDate()).padStart(2, '0')
    const mon = MONTH_SHORT[d.getMonth()]
    const year = d.getFullYear()
    if (variant === 'short') return `${day} ${mon}`
    if (variant === 'iso') return `${year}-${String(d.getMonth() + 1).padStart(2, '0')}-${day}`
    if (variant === 'long') {
        const hh = String(d.getHours()).padStart(2, '0')
        const mm = String(d.getMinutes()).padStart(2, '0')
        return `${day} ${mon} ${year}, ${hh}:${mm}`
    }
    return `${day} ${mon} ${year}`
}

/** "04 May 2026 → 09 May 2026" (or single date when end is missing/equal). */
export function formatDateRange(start, end, variant = 'medium') {
    const a = formatDate(start, variant)
    const b = formatDate(end, variant)
    if (!a && !b) return ''
    if (!b || a === b) return a
    if (!a) return b
    return `${a} → ${b}`
}

/** Human-readable relative time: "2h ago", "in 3d", "just now". */
export function formatRelative(value) {
    const d = toDate(value)
    if (!d) return ''
    const diffSec = Math.round((d.getTime() - Date.now()) / 1000)
    const abs = Math.abs(diffSec)
    const future = diffSec > 0
    const fmt = (n, unit) => future ? `in ${n}${unit}` : `${n}${unit} ago`
    if (abs < 45) return 'just now'
    if (abs < 3600) return fmt(Math.round(abs / 60), 'm')
    if (abs < 86400) return fmt(Math.round(abs / 3600), 'h')
    if (abs < 2592000) return fmt(Math.round(abs / 86400), 'd')
    return formatDate(d)
}

/** "marks_entry" → "Marks Entry"; safe on null. */
export function formatStatus(value) {
    if (!value) return ''
    return String(value)
        .replace(/[_-]+/g, ' ')
        .replace(/\b\w/g, c => c.toUpperCase())
}

/** Number formatter: 1234.5 → "1,234.5"; null → "—". */
export function formatNumber(value, opts = {}) {
    if (value == null || value === '') return opts.empty ?? '—'
    const n = Number(value)
    if (Number.isNaN(n)) return String(value)
    return n.toLocaleString('en-US', { maximumFractionDigits: opts.decimals ?? 2 })
}
