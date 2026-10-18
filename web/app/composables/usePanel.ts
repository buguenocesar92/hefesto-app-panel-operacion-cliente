/**
 * Cliente del panel.
 *
 * Tipos minimos y deliberados: el frontend solo conoce lo que la API
 * devuelve. Si la API empieza a mandar un campo sensible, este archivo no
 * tiene forma de mostrarlo porque no lo conoce.
 */

export interface TarjetaIntegracion {
  sistema: string
  nombre: string
  que_aporta: string
  estado: 'disponible' | 'datos_viejos' | 'no_disponible' | 'desactivada' | 'no_configurada'
  estado_texto: string
  es_degradado: boolean
  requiere_atencion: boolean
  mensaje: string
  actualizado_en: string | null
  desde_cache: boolean
  verificacion_integracion: string
  escritura_posible: false
}

export interface Respuesta<T> {
  datos: T
}

export const usePanel = () => {
  /** Tarjetas del modulo Resumen. */
  const resumen = () => useFetch<Respuesta<{ generado_en: string, tarjetas: TarjetaIntegracion[] }>>('/api/panel/resumen')

  const sitios = () => useFetch<Respuesta<{ sitios: unknown[] }>>('/api/panel/sitios')

  const respaldos = () => useFetch<Respuesta<{ sin_registros: boolean, respaldos: unknown[] }>>('/api/panel/respaldos')

  const seguridad = () => useFetch<Respuesta<{ totales: { detectados: number, bloqueados: number } }>>('/api/panel/seguridad')

  const uso = () => useFetch<Respuesta<{ facturacion: null }>>('/api/panel/uso')

  const integraciones = () => useFetch<Respuesta<{ integraciones: unknown[] }>>('/api/panel/integraciones')

  return { resumen, sitios, respaldos, seguridad, uso, integraciones }
}

/**
 * Texto "hace 6 horas" a partir de una marca ISO.
 *
 * Se muestra en todas las pantallas: el cliente tiene que ver que lo que mira
 * no esta en vivo.
 */
export function tiempoDesde(hasta: string | null): string {
  if (!hasta) {
    return 'sin datos'
  }

  const segundos = Math.floor((Date.now() - new Date(hasta).getTime()) / 1000)

  if (segundos < 60) return 'hace instantes'
  if (segundos < 3600) return `hace ${Math.floor(segundos / 60)} minutos`
  if (segundos < 86400) return `hace ${Math.floor(segundos / 3600)} horas`
  return `hace ${Math.floor(segundos / 86400)} dias`
}