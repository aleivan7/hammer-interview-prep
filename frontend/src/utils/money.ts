export function formatDollars(amount: string | number): string {
  const value = typeof amount === 'number' ? amount / 100 : Number(amount)

  if (Number.isNaN(value)) {
    return String(amount)
  }

  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
  }).format(value)
}

export function formatCents(cents: number): string {
  return formatDollars(cents)
}

export function dollarsInputToCents(value: string): number | null {
  const trimmed = value.trim()

  if (!trimmed || !/^-?\d+(\.\d{1,2})?$/.test(trimmed)) {
    return null
  }

  const negative = trimmed.startsWith('-')
  const normalized = trimmed.replace('-', '')
  const [whole, fraction = '0'] = normalized.split('.')
  const cents = Number(whole) * 100 + Number(fraction.padEnd(2, '0').slice(0, 2))

  return negative ? -cents : cents
}

export function formatDate(isoDate: string): string {
  const date = new Date(`${isoDate}T12:00:00`)

  if (Number.isNaN(date.getTime())) {
    return isoDate
  }

  return new Intl.DateTimeFormat('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  }).format(date)
}

export function formatShortDate(isoDate: string): string {
  const date = new Date(`${isoDate}T12:00:00`)

  if (Number.isNaN(date.getTime())) {
    return isoDate
  }

  return new Intl.DateTimeFormat('en-US', {
    month: 'short',
    day: 'numeric',
  }).format(date)
}
