<script setup lang="ts">
/**
 * Proxy hacia la API de Laravel.
 *
 * Tres reglas que no se negocian:
 *  1. Solo GET. El panel es de solo lectura; cualquier otro metodo se
 *     rechaza aqui, antes de tocar la API.
 *  2. El token se lee de una cookie httpOnly y viaja solo por servidor. Nunca
 *     se devuelve al navegador ni se incluye en el HTML.
 *  3. No se reenvia la respuesta cruda si viene con un error: se normaliza,
 *     para que el frontend no tenga que conocer el formato de Laravel.
 */
export default defineEventHandler(async (event) => {
  const metodo = event.method.toUpperCase()

  if (metodo !== 'GET' && metodo !== 'HEAD') {
    throw createError({
      statusCode: 405,
      statusMessage: 'El Panel de Operacion es de solo lectura.',
    })
  }

  const token = getCookie(event, 'panel_token')

  if (!token) {
    throw createError({
      statusCode: 401,
      statusMessage: 'No hay sesion activa en el panel.',
    })
  }

  const config = useRuntimeConfig(event)
  const camino = getRouterParam(event, 'ruta') ?? 'resumen'
  const consulta = getQuery(event)
  const destino = new URL(`/api/panel/${camino}`, config.apiInterna)

  for (const [clave, valor] of Object.entries(consulta)) {
    if (typeof valor === 'string') {
      destino.searchParams.set(clave, valor)
    }
  }

  try {
    return await $fetch(destino.toString(), {
      method: 'GET',
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: 'application/json',
      },
    })
  } catch (error: unknown) {
    const estado = (error as { statusCode?: number })?.statusCode ?? 500

    throw createError({
      statusCode: estado,
      statusMessage:
        estado === 404
          ? 'No se encontraron datos para esta empresa.'
          : 'No se pudo obtener la informacion en este momento.',
    })
  }
})