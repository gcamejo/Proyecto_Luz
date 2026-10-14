import React, { useState } from 'react'
import { requestError } from './shared'

// Modal genérico de formulario/confirmación. `fields`: [{ name, label, type, required, options, defaultValue, hint }].
const ActionModal = ({ title, message, fields = [], submitLabel = 'Confirmar', danger = false, renderExtra, onSubmit, onClose }) => {
  const [values, setValues] = useState(() => Object.fromEntries(fields.map(field => [field.name, field.defaultValue ?? (field.type === 'checkbox' ? false : '')])))
  const [error, setError] = useState('')
  const [saving, setSaving] = useState(false)

  const change = (field, event) => {
    const value = field.type === 'checkbox' ? event.target.checked : event.target.value
    setValues(current => ({ ...current, [field.name]: value }))
  }

  const submit = async event => {
    event.preventDefault()
    setError('')
    const missing = fields.find(field => field.required && field.type !== 'checkbox' && !String(values[field.name]).trim())
    if (missing) {
      setError(`Completá el campo "${missing.label}".`)
      return
    }
    setSaving(true)
    try {
      await onSubmit(values)
      onClose()
    } catch (requestErrorValue) {
      setError(requestError(requestErrorValue))
      setSaving(false)
    }
  }

  return (
    <div className='payment-modal-backdrop' role='presentation'>
      <form className='payment-modal booking-admin-form' role='dialog' aria-modal='true' aria-labelledby='payment-modal-title' onSubmit={submit}>
        <h2 id='payment-modal-title'>{title}</h2>
        {message && <div className='payment-modal-message'>{message}</div>}
        {fields.map(field => (
          <div key={field.name} className={field.type === 'checkbox' ? 'payment-field payment-field-check' : 'payment-field'}>
            {field.type === 'checkbox' ? (
              <label><input type='checkbox' checked={values[field.name]} onChange={event => change(field, event)} /> {field.label}</label>
            ) : (
              <label>
                {field.label}
                {field.type === 'select' ? (
                  <select value={values[field.name]} onChange={event => change(field, event)}>
                    {field.options.map(option => <option key={option.value} value={option.value}>{option.label}</option>)}
                  </select>
                ) : field.type === 'textarea' ? (
                  <textarea rows='2' value={values[field.name]} onChange={event => change(field, event)} maxLength={field.maxLength} />
                ) : (
                  <input
                    type={field.type || 'text'}
                    inputMode={field.type === 'money' ? 'decimal' : undefined}
                    value={values[field.name]}
                    max={field.max}
                    onChange={event => change(field, event)}
                    autoComplete='off'
                  />
                )}
              </label>
            )}
            {field.hint && <small className='booking-muted'>{field.hint}</small>}
          </div>
        ))}
        {renderExtra && renderExtra(values)}
        {error && <p className='booking-message booking-message-error' role='alert'>{error}</p>}
        <div className='payment-modal-actions'>
          <button type='button' className='booking-text-button' onClick={onClose} disabled={saving}>Cancelar</button>
          <button type='submit' className={danger ? 'booking-button booking-text-button-danger' : 'booking-button booking-button-primary'} disabled={saving}>
            {saving ? 'Guardando...' : submitLabel}
          </button>
        </div>
      </form>
    </div>
  )
}

export default ActionModal
