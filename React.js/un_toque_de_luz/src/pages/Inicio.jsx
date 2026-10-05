import React from 'react'
import { Link } from 'react-router-dom'
import '../Styles/Inicio.css'

const Inicio = () => {
  return (
    <div className="inicio-page">
      <section className="inicio-hero">
        <div className="inicio-hero-content">
          <p className="inicio-eyebrow">Yoga · bienestar · encuentro</p>
          <h1>Un toque de luz para volver a vos.</h1>
          <p className="inicio-intro">
            Un espacio para respirar, moverte y encontrar un momento de calma en medio de la rutina.
          </p>
          <div className="inicio-actions">
            <Link className="inicio-button inicio-button-primary" to="/actividades">Conocé las actividades</Link>
            <Link className="inicio-button inicio-button-secondary" to="/login">Ingresar</Link>
          </div>
        </div>
        <span className="inicio-hero-caption">Un lugar para hacer una pausa</span>
      </section>

      <section className="inicio-practicas" aria-labelledby="practicas-title">
        <div className="inicio-section-heading">
          <p className="inicio-eyebrow">Encontrá tu momento</p>
          <h2 id="practicas-title">Prácticas para sentirte bien</h2>
          <p>Explorá propuestas que invitan a bajar el ritmo y conectar con el presente.</p>
        </div>

        <div className="inicio-actividades">
          <article className="inicio-actividad">
            <img src="/img/Constelaciones4.jpg" alt="Espacio preparado para una sesión de constelaciones" />
            <div>
              <h3>Constelaciones</h3>
              <p>Un espacio de escucha para mirar tus vínculos desde otra perspectiva.</p>
            </div>
          </article>
          <article className="inicio-actividad">
            <img src="/img/CuencoAgua.png" alt="Cuenco de agua en una sesión de bienestar" />
            <div>
              <h3>Sonido y pausa</h3>
              <p>Regalate un momento de quietud y atención plena.</p>
            </div>
          </article>
          <article className="inicio-actividad">
            <img src="/img/Cuencoterapia4.jpg" alt="Cuencos preparados para una práctica sonora" />
            <div>
              <h3>Cuencoterapia</h3>
              <p>Dejá que el sonido acompañe un descanso profundo y reparador.</p>
            </div>
          </article>
        </div>
        <Link className="inicio-more-link" to="/actividades">Ver todas las actividades <span aria-hidden="true">→</span></Link>
      </section>

      <section className="inicio-cierre">
        <p className="inicio-eyebrow">Tu próximo paso</p>
        <h2>Hacé lugar para sentirte mejor.</h2>
        <Link className="inicio-button inicio-button-primary" to="/actividades">Elegí una actividad</Link>
        <p className="inicio-login-prompt">¿Ya sos parte? <Link to="/login">Ingresá a tu cuenta</Link></p>
      </section>
    </div>
  )
}

export default Inicio