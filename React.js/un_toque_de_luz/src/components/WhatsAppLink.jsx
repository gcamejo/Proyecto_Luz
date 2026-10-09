import React from 'react'
import { BsWhatsapp } from 'react-icons/bs'
import { getWhatsAppLinkProps } from '../utils/whatsappLink'

const WhatsAppLink = ({
  activityName,
  message,
  label = 'Solicitar turno por WhatsApp',
  ariaLabel = label,
  className = '',
}) => {
  const configuredNumber = import.meta.env.VITE_WHATSAPP_NUMBER
  const linkProps = getWhatsAppLinkProps(
    configuredNumber,
    message || `Hola, quisiera consultar por la actividad ${activityName}.`
  )

  if (!linkProps) return null

  return (
    <a className={className} {...linkProps} aria-label={ariaLabel}>
      <BsWhatsapp aria-hidden="true" />
      <span>{label}</span>
    </a>
  )
}

export default WhatsAppLink
