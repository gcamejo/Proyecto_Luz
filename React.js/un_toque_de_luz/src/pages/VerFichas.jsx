import React, { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { BsPencilSquare, BsTrash, BsSearch } from 'react-icons/bs'
import { useDispatch, useSelector } from 'react-redux'
import { deleteFicha, getFichasYoguinis } from '../store/Slice/Yoguinis/yoguinis'
import '../Styles/Yoguinis.css'

const errorMessage = (error) => error.response?.data?.message || error.message || 'No se pudo completar la solicitud.'

const VerFichas = () => {
  const dispatch = useDispatch()
  const { fichasyoguinis, isLoading, error } = useSelector(state => state.fichasyoguinis)
  const [search, setSearch] = useState('')
  const [selected, setSelected] = useState(null)
  const [deleting, setDeleting] = useState(false)
  const [deleteError, setDeleteError] = useState('')

  useEffect(() => {
    dispatch(getFichasYoguinis()).catch(() => {})
  }, [dispatch])

  useEffect(() => {
    if (!selected) return undefined
    const closeOnEscape = event => {
      if (event.key === 'Escape' && !deleting) setSelected(null)
    }
    window.addEventListener('keydown', closeOnEscape)
    return () => window.removeEventListener('keydown', closeOnEscape)
  }, [selected, deleting])

  const query = search.trim().toLocaleLowerCase()
  const filteredFichas = fichasyoguinis.filter(ficha =>
    [ficha.nombre, ficha.apellido, ficha.email, ficha.telefono, ficha.direccion, ficha.numero]
      .some(value => String(value ?? '').toLocaleLowerCase().includes(query))
  )

  const confirmDelete = async () => {
    if (!selected) return
    setDeleting(true)
    setDeleteError('')
    try {
      await dispatch(deleteFicha(selected.id))
      setSelected(null)
    } catch (requestError) {
      setDeleteError(errorMessage(requestError))
    } finally {
      setDeleting(false)
    }
  }

  return (
    <section className="yoguinis-page" aria-labelledby="yoguinis-title">
      <header className="yoguinis-heading">
        <div>
          <p className="yoguinis-eyebrow">Administración</p>
          <h1 id="yoguinis-title">Directorio de yoguinis</h1>
        </div>
        <span className="yoguinis-count" aria-live="polite">
          {isLoading ? 'Cargando' : `${filteredFichas.length} ${filteredFichas.length === 1 ? 'ficha' : 'fichas'}`}
        </span>
      </header>

      <div className="yoguinis-toolbar">
        <label className="yoguinis-search">
          <BsSearch aria-hidden="true" />
          <span className="visually-hidden">Buscar yoguinis</span>
          <input
            type="search"
            value={search}
            onChange={event => setSearch(event.target.value)}
            placeholder="Nombre, correo, teléfono o dirección"
          />
        </label>
        <Link className="yoguini-button yoguini-button-primary" to="/cargarFicha">Nueva ficha</Link>
      </div>

      {error && <p className="yoguini-message yoguini-message-error" role="alert">No se pudo cargar el directorio: {error}</p>}
      {isLoading && <p className="yoguini-message" role="status">Cargando fichas...</p>}
      {!isLoading && !error && fichasyoguinis.length === 0 && (
        <div className="yoguini-empty"><h2>Aún no hay fichas</h2><p>Las fichas creadas aparecerán en este directorio.</p></div>
      )}
      {!isLoading && !error && fichasyoguinis.length > 0 && filteredFichas.length === 0 && (
        <div className="yoguini-empty"><h2>No encontramos coincidencias</h2><p>Probá con otro nombre o dato de contacto.</p></div>
      )}

      {!isLoading && !error && filteredFichas.length > 0 && (
        <div className="yoguinis-table-wrap" role="region" aria-label="Listado de yoguinis" tabIndex="0">
          <table className="yoguinis-table">
            <thead>
              <tr>
                <th scope="col">#</th>
                <th scope="col">Yoguini</th>
                <th scope="col">Contacto</th>
                <th scope="col">Dirección</th>
                <th scope="col">Nacimiento</th>
                <th scope="col"><span className="visually-hidden">Acciones</span></th>
              </tr>
            </thead>
            <tbody>
              {filteredFichas.map((ficha, index) => {
                const initials = `${ficha.nombre?.[0] || ''}${ficha.apellido?.[0] || ''}`.toLocaleUpperCase()
                return (
                  <tr key={ficha.id}>
                    <td className="yoguini-row-number">{index + 1}</td>
                    <td>
                      <div className="yoguini-person">
                        <span className="yoguini-avatar" aria-hidden="true">{initials}</span>
                        <span className="yoguini-person-name">{ficha.nombre} {ficha.apellido}</span>
                      </div>
                    </td>
                    <td>
                      <span className="yoguini-contact-main">{ficha.email}</span>
                      <span className="yoguini-contact-sub">{ficha.telefono}</span>
                    </td>
                    <td>{ficha.direccion} {ficha.numero}</td>
                    <td>{ficha.fechaNacimiento}</td>
                    <td>
                      <div className="yoguini-actions">
                        <Link className="yoguini-action" to={`/editarFicha/${ficha.id}`} aria-label={`Editar ficha de ${ficha.nombre} ${ficha.apellido}`}>
                          <BsPencilSquare aria-hidden="true" /> Editar
                        </Link>
                        <button
                          className="yoguini-action yoguini-action-danger"
                          type="button"
                          onClick={() => { setSelected(ficha); setDeleteError('') }}
                          aria-label={`Eliminar ficha de ${ficha.nombre} ${ficha.apellido}`}
                        >
                          <BsTrash aria-hidden="true" /> Eliminar
                        </button>
                      </div>
                    </td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        </div>
      )}

      {selected && (
        <div className="yoguini-dialog-backdrop" onMouseDown={event => { if (event.target === event.currentTarget && !deleting) setSelected(null) }}>
          <section className="yoguini-dialog" role="dialog" aria-modal="true" aria-labelledby="delete-title" aria-describedby="delete-description">
            <p className="yoguinis-eyebrow">Confirmar eliminación</p>
            <h2 id="delete-title">¿Eliminar esta ficha?</h2>
            <div id="delete-description" className="yoguini-dialog-person">
              <strong>{selected.nombre} {selected.apellido}</strong>
              <span>{selected.direccion} {selected.numero}</span>
              <span>{selected.telefono} · {selected.email}</span>
            </div>
            <p>Esta acción no se puede deshacer.</p>
            {deleteError && <p className="yoguini-message yoguini-message-error" role="alert">{deleteError}</p>}
            <div className="yoguini-dialog-actions">
              <button className="yoguini-button yoguini-button-secondary" type="button" disabled={deleting} onClick={() => setSelected(null)}>Cancelar</button>
              <button className="yoguini-button yoguini-button-danger" type="button" disabled={deleting} onClick={confirmDelete}>
                {deleting ? 'Eliminando...' : 'Eliminar ficha'}
              </button>
            </div>
          </section>
        </div>
      )}
    </section>
  )
}

export default VerFichas