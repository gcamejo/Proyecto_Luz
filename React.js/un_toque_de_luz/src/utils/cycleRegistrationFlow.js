export const cycleRegistrationFlow = cycleId => {
  const bookingPath = `/reservarCiclo?ciclo_id=${encodeURIComponent(String(cycleId))}`

  return {
    bookingPath,
    registerPath: `/cargarFicha?redirect=${encodeURIComponent(bookingPath)}&volver=${encodeURIComponent('/comenzar-yoga')}`,
    returnPath: '/comenzar-yoga',
  }
}

export const prioritizeCycle = (cycles, selectedCycleId) => {
  if (!selectedCycleId) return cycles

  const selectedCycle = cycles.find(cycle => String(cycle.id) === String(selectedCycleId))
  if (!selectedCycle) return cycles

  return [selectedCycle, ...cycles.filter(cycle => String(cycle.id) !== String(selectedCycleId))]
}