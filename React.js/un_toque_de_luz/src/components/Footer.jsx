import React from 'react'
import { BsEnvelope, BsFacebook, BsGeoAlt, BsInstagram, BsWhatsapp } from 'react-icons/bs'
import { getWhatsAppLinkProps } from '../utils/whatsappLink'

import '../Styles/App.css'

const getHttpsUrl = (value) => {
  if (!value) return null

  try {
    const url = new URL(value)
    return url.protocol === 'https:' ? url.toString() : null
  } catch {
    return null
  }
}

const Footer = () => {
  const address = import.meta.env.VITE_STUDIO_ADDRESS?.trim()
  const mapsUrl = getHttpsUrl(import.meta.env.VITE_GOOGLE_MAPS_URL?.trim())
  const email = import.meta.env.VITE_CONTACT_EMAIL?.trim()
  const emailIsValid = email && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)
  const whatsApp = getWhatsAppLinkProps(
    import.meta.env.VITE_WHATSAPP_NUMBER,
    'Hola, quisiera hacer una consulta.',
  )
  const socialLinks = [
    { label: 'Instagram', href: getHttpsUrl(import.meta.env.VITE_INSTAGRAM_URL?.trim()), Icon: BsInstagram },
    { label: 'Facebook', href: getHttpsUrl(import.meta.env.VITE_FACEBOOK_URL?.trim()), Icon: BsFacebook },
  ].filter((link) => link.href)

  const hasContact = address || emailIsValid || whatsApp

  return (
    <div className='site-footer'>
      <div className='site-footer-main'>
        <div className='site-footer-brand'>
          <p className='site-footer-brand-name'>Un toque de Luz</p>
          <p>Un espacio para respirar, moverte y volver a vos.</p>
        </div>

        {hasContact && (
          <section className='site-footer-group' aria-labelledby='footer-contact-title'>
            <h2 id='footer-contact-title'>Contacto</h2>
            <ul className='site-footer-contact-list'>
              {address && (
                <li>
                  <BsGeoAlt aria-hidden='true' />
                  {mapsUrl
                    ? <a href={mapsUrl} target='_blank' rel='noopener noreferrer'>{address} · Cómo llegar</a>
                    : <span>{address}</span>}
                </li>
              )}
              {emailIsValid && (
                <li>
                  <BsEnvelope aria-hidden='true' />
                  <a href={`mailto:${email}`}>{email}</a>
                </li>
              )}
              {whatsApp && (
                <li>
                  <BsWhatsapp aria-hidden='true' />
                  <a {...whatsApp}>WhatsApp</a>
                </li>
              )}
            </ul>
          </section>
        )}

        {socialLinks.length > 0 && (
          <section className='site-footer-group' aria-labelledby='footer-social-title'>
            <h2 id='footer-social-title'>Seguinos en nuestras redes</h2>
            <nav className='site-footer-socials' aria-label='Redes sociales'>
              {socialLinks.map(({ label, href, Icon }) => (
                <a key={label} href={href} target='_blank' rel='noopener noreferrer' aria-label={label} title={label}>
                  <Icon aria-hidden='true' />
                  <span>{label}</span>
                </a>
              ))}
            </nav>
          </section>
        )}
      </div>

      <div className='site-footer-bottom'>
        <span>© {new Date().getFullYear()} Un toque de Luz</span>
        <span>Desarrollo: SGTech&amp;Dev</span>
        <nav className='footer-legal-links' aria-label='Información legal'>
          <a href='/privacidad'>Política de Privacidad</a>
          <a href='/terminos'>Términos y Condiciones</a>
        </nav>
      </div>
    </div>
  )
}

export default Footer