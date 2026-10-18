<script setup lang="ts">
/**
 * Modulo 2: Sitios.
 *
 * Cuando el sistema de monitoreo no entrega datos que coincidan con el sitio,
 * la pantalla dice "sin informacion disponible". No dice "caido": son cosas
 * distintas y confundirla seria mostrar una falla donde no la hay.
 */
const { sitios } = usePanel()
const { data, error } = await sitios()

const origen = computed(() => data.value?.datos.origen ?? null)
const lista = computed(() => data.value?.datos.sitios ?? [])
</script>

<template>
  <section>
    <h2 class="titulo-seccion">Sitios</h2>

    <p class="marca-tiempo">
      Datos del sistema de monitoreo: {{ tiempoDesde(origen?.actualizado_en ?? null) }}
      ({{ origen?.estado ?? 'sin estado' }}).
    </p>

    <p v-if="error" class="estado-falla">
      No se pudo obtener la disponibilidad en este momento.
    </p>

    <ul class="lista">
      <li v-for="sitio in lista" :key="(sitio as { id: string }).id" class="sitio">
        <div>
          <p class="sitio__nombre">{{ (sitio as { nombre: string }).nombre }}</p>
          <p class="sitio__host">{{ (sitio as { host: string }).host }}</p>
        </div>
        <p class="sitio__estado">
          {{ (sitio as { disponibilidad_texto: string }).disponibilidad_texto }}
        </p>
        <p class="sitio__nota">{{ (sitio as { nota?: string }).nota }}</p>
      </li>
    </ul>

    <p v-if="lista.length === 0 && !error" class="estado-vacio">
      Todavia no hay sitios registrados para esta empresa.
    </p>
  </section>
</template>

<style scoped>
.lista { list-style: none; padding: 0; display: grid; gap: 0.75rem; }
.sitio { background: var(--tarjeta); border: 1px solid var(--borde); border-radius: 10px; padding: 0.9rem 1rem; }
.sitio__nombre { margin: 0; font-weight: 600; }
.sitio__host { margin: 0.1rem 0 0.4rem; color: var(--texto-suave); font-size: 0.85rem; }
.sitio__estado { margin: 0; }
.sitio__nota { margin: 0.3rem 0 0; color: var(--texto-suave); font-size: 0.8rem; }
.marca-tiempo { color: var(--texto-suave); font-size: 0.9rem; }
.titulo-seccion { margin-top: 0; }
.estado-falla { color: var(--falla); }
.estado-vacio { color: var(--texto-suave); }
</style>