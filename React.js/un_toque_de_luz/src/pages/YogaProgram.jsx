import React from 'react'
import { Link } from 'react-router-dom'
import { useSelector } from 'react-redux'
import useSWR from 'swr'
import fetcher from '../api/fetcher'
import WhatsAppLink from '../components/WhatsAppLink'
import { yogaPageContent as content } from '../content/yogaPageContent'
import '../Styles/YogaProgram.css'

const dateFormatter = new Intl.DateTimeFormat('es-AR', { day: 'numeric', month: 'long' })
const formatDate = value => dateFormatter.format(new Date(`${value}T12:00:00`))

const YogaProgram = () => {
  const { token } = useSelector(state => state.token)
  const { data, error, isLoading } = useSWR('api/public/yoga/cycles', fetcher)
  const cycles = data?.cycles || []
  const actionTarget = token ? '/reservarCiclo' : '/cargarFicha?redirect=%2FreservarCiclo'

  return (
    <div className="yoga-program">
      <section className="yoga-program-hero">
        <div className="yoga-program-hero-content">
          <p className="yoga-program-eyebrow">{content.eyebrow}</p>
          <h1>{content.title}</h1>
          <p className="yoga-program-intro">{content.introduction}</p>
          <div className="yoga-program-actions">
            <Link className="yoga-program-button yoga-program-button-primary" to={actionTarget}>
              {token ? content.primaryActionBooking : content.primaryActionRegister}
            </Link>
            <WhatsAppLink
              className="yoga-program-whatsapp"
              activityName="Yoga"
              message={content.whatsappMessage}
              label={content.whatsappLabel}
              ariaLabel="Consultar por WhatsApp sobre las clases de Yoga"
            />
          </div>
        </div>
        <Link className="yoga-program-back" to="/">{content.backHome}</Link>
      </section>

      <section className="yoga-program-about" aria-labelledby="yoga-about-title">
        <div className="yoga-program-about-copy">
          <p className="yoga-program-section-eyebrow">{content.aboutEyebrow}</p>
          <h2 id="yoga-about-title">{content.aboutTitle}</h2>
          <p>{content.aboutBody}</p>
        </div>
        <div className="yoga-program-how">
          <h2>{content.howTitle}</h2>
          <ol>
            {content.howSteps.map((step, index) => (
              <li key={step.title}>
                <span className="yoga-program-step-number">0{index + 1}</span>
                <div><h3>{step.title}</h3><p>{step.body}</p></div>
              </li>
            ))}
          </ol>
        </div>
      </section>

      <section className="yoga-program-cycles" aria-labelledby="yoga-cycles-title">
        <header className="yoga-program-cycles-heading">
          <div>
            <p className="yoga-program-section-eyebrow">{content.cyclesEyebrow}</p>
            <h2 id="yoga-cycles-title">{content.cyclesTitle}</h2>
          </div>
          {isLoading && <p className="yoga-program-status" role="status">{content.cyclesLoading}</p>}
        </header>

        {error && <p className="yoga-program-message yoga-program-message-error" role="alert">{content.cyclesError}</p>}
        {!isLoading && !error && cycles.length === 0 && (
          <div className="yoga-program-empty">
            <h3>{content.cyclesEmpty}</h3>
            <p>{content.cyclesEmptyDetail}</p>
          </div>
        )}

        <div className="yoga-program-cycle-list">
          {cycles.map(cycle => (
            <article className="yoga-cycle" key={cycle.id}>
              <header className="yoga-cycle-heading">
                <div>
                  <h3>{cycle.nombre}</h3>
                  <p>{content.cycleDates.replace('{start}', formatDate(cycle.fecha_inicio)).replace('{end}', formatDate(cycle.fecha_fin))}</p>
                </div>
                <span className={`yoga-cycle-availability${cycle.hay_lugares ? ' is-available' : ' is-full'}`}>
                  {cycle.hay_lugares ? content.available : content.full}
                </span>
              </header>
              <p className="yoga-cycle-frequency">{content.classesPerWeek.replace('{count}', cycle.clases_por_semana)}</p>
              <h4>{content.scheduleLabel}</h4>
              <ul className="yoga-cycle-schedules" aria-label={content.schedulesLabel}>
                {cycle.horarios.map((schedule, index) => (
                  <li key={`${schedule.dia}-${schedule.hora_inicio}-${index}`}>
                    <span>{schedule.dia}</span>
                    <time>{schedule.hora_inicio}</time>
                    <span>{content.duration.replace('{minutes}', schedule.duracion_min)}</span>
                    <span className={`yoga-cycle-slot${schedule.hay_lugares ? ' is-available' : ' is-full'}`}>
                      {schedule.hay_lugares ? content.available : content.full}
                    </span>
                  </li>
                ))}
              </ul>
              <footer className="yoga-cycle-footer">
                <p>{content.bookingPrompt}</p>
                <Link className="yoga-program-cycle-link" to={actionTarget}>
                  {token ? content.primaryActionBooking : content.cycleAction}
                </Link>
              </footer>
            </article>
          ))}
        </div>
      </section>

      <section className="yoga-program-close">
        <Link className="yoga-program-button yoga-program-button-primary" to={actionTarget}>
          {token ? content.primaryActionBooking : content.primaryActionRegister}
        </Link>
        <Link className="yoga-program-back-inline" to="/">{content.backHome}</Link>
      </section>
    </div>
  )
}

export default YogaProgram
