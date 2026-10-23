import React from 'react'
import { Link, useLocation } from 'react-router-dom'
import useSWR from 'swr'
import fetcher from '../api/fetcher'
import { legalContent, POLICY_DRAFT, POLICY_UPDATED_AT, POLICY_VERSION } from '../content/legalContent'
import '../Styles/Legal.css'

const LegalPage = () => {
  const location = useLocation()
  const type = location.pathname === '/terminos' ? 'terminos' : 'privacidad'
  const policy = legalContent[type]
  const { data: parameters } = useSWR(type === 'terminos' ? 'api/public/legal-parameters' : null, fetcher)

  return (
    <div className='legal-page'>
      <article className='legal-document' aria-labelledby='legal-title'>
        <header className='legal-document-header'>
          <Link className='legal-back' to='/'>Volver al inicio</Link>
          {POLICY_DRAFT && <p className='legal-draft-notice' role='note'>Borrador para revisión legal. No publicar como versión definitiva hasta completar los marcadores.</p>}
          <h1 id='legal-title'>{policy.title}</h1>
          <p className='legal-meta'>Versión {POLICY_VERSION} · Actualizada {POLICY_UPDATED_AT}</p>
          <p className='legal-introduction'>{policy.introduction}</p>
        </header>

        <div className='legal-sections'>
          {policy.sections.map((section, index) => (
            <section className='legal-section' key={section.title} aria-labelledby={`legal-${type}-section-${index}`}>
              <h2 id={`legal-${type}-section-${index}`}>{section.title}</h2>
              {section.paragraphs.map((paragraph, index) => (
                <p key={`${section.title}-${index}`}>
                  {paragraph.replace('{{horas_aviso_minimas}}', parameters?.horas_aviso_minimas ?? '[CONFIGURACIÓN NO DISPONIBLE]')}
                </p>
              ))}
              {section.link && <p><a href={section.link.href} target='_blank' rel='noopener noreferrer'>{section.link.label}</a></p>}
            </section>
          ))}
        </div>

        <footer className='legal-document-footer'>
          <button type='button' onClick={() => window.print()}>Imprimir</button>
          <Link to='/comenzar-yoga'>Volver a Yoga</Link>
        </footer>
      </article>
    </div>
  )
}

export default LegalPage