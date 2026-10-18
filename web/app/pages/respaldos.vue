<script setup lang="ts">
/**
 * Modulo 3: Respaldos.
 *
 * La pantalla mas importante para el cliente: responde "esta mi informacion
 * a salvo?". Solo puede responder con lo que el sistema de respaldo dio. Si no
 * hay registros, lo dice; no muestra una lista vacia que parece un "todo bien".
 */
const { respaldos } = usePanel()
const { data, error } = await respaldos()

const registros = computed(() => data.value?.datos.respaldos ?? [])
const sinRegistros = computed(() => data.value?.datos.sin_registros ?? true)
const ultimo = computed(() => data.value?.datos.ultimo_respaldo ?? null)
</script>

<template>
  <section>
    <h2 class="titulo-seccion">Respaldos</h2>

    <p v-if="error" class="estado-falla">
      No se pudo obtener el historial de respaldos en este momento.
    </p>

    <div v-if="ultimo" class="destacado">
      <p class="destacado__titulo">Ultimo respaldo</p>
      <p class="destacado__fecha">{{ tiempoDesde(ultimo.iniciado_en) }}</p>
      <p>{{ ultimo.estado_texto }} &middot; {{ ultimo.verificacion_texto }}</p>
      <p v-if="ultimo.archivos">{{ ultimo.archivos.toLocaleString('es-CL') }} archivos</p>
    </div>

    <p v-else-if="sinRegistros" class="estado-vacio">
      No hay registros de respaldo para esta empresa. Si espera verlos aqui,
      hable con Hefesto: puede que la integracion con el sistema de respaldo
      este desactivada.
    </p>

    <table v-if="registros.length" class="tabla">
      <thead>
        <tr>
          <th>Inicio</th>
          <th>Estado</th>
          <th>Verificacion</th>
          <th>Archivos</th>
          <th>Duracion</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="respaldo in registros" :key="(respaldo as { id: string }).id">
          <td>{{ tiempoDesde((respaldo as { iniciado_en: string }).iniciado_en) }}</td>
          <td>{{ (respaldo as { estado_texto: string }).estado_texto }}</td>
          <td>{{ (respaldo as { verificacion_texto: string }).verificacion_texto }}</td>
          <td>
            {{ (respaldo as { archivos: number | null }).archivos?.toLocaleString('es-CL') ?? 'sin dato' }}
          </td>
          <td>{{ (respaldo as { duracion_segundos: number | null }).duracion_segundos ?? 'sin dato' }} s</td>
        </tr>
      </tbody>
    </table>

    <p class="nota">
      El panel muestra cantidad de archivos y estado de verificacion. Nunca el
      contenido de un respaldo.
    </p>
  </section>
</template>

<style scoped>
.destacado { background: var(--tarjeta); border: 1px solid var(--borde); border-radius: 10px; padding: 1rem; margin-bottom: 1rem; }
.destacado__titulo { margin: 0; color: var(--texto-suave); font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em; }
.destacado__fecha { margin: 0.2rem 0; font-size: 1.3rem; font-weight: 600; }
.tabla { width: 100%; border-collapse: collapse; background: var(--tarjeta); border: 1px solid var(--borde); border-radius: 10px; }
.tabla th, .tabla td { text-align: left; padding: 0.5rem 0.7rem; border-bottom: 1px solid var(--borde); font-size: 0.9rem; }
.titulo-seccion { margin-top: 0; }
.estado-falla { color: var(--falla); }
.estado-vacio { color: var(--texto-suave); }
.nota { color: var(--texto-suave); font-size: 0.82rem; margin-top: 1rem; }
</style>