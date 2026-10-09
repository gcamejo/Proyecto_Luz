export const createWhatsAppHref = (number, message) => {
  if (typeof number !== 'string' || !/^\d+$/.test(number)) return null
  return `https://wa.me/${number}?text=${encodeURIComponent(message)}`
}

export const getWhatsAppLinkProps = (number, message) => {
  const href = createWhatsAppHref(number, message)
  if (!href) return null

  return {
    href,
    target: '_blank',
    rel: 'noopener noreferrer',
  }
}

export const createWhatsAppMessage = activityName =>
  `Hola, quisiera consultar por la actividad ${activityName}.`
