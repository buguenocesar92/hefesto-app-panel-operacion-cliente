<script setup lang="ts">
/**
 * Modulo 4: Seguridad.
 *
 * Solo cantidades. Nunca direcciones IP ni detalle de ataques: el panel no
 * es el lugar donde publicar quien realizo un ataque.
 */
const { seguridad } = usePanel()
const { data, error } = await seguridad()

const totales = computed(() => data.value?.datos.totales ?? { detectados: 0, bloqueados: 0 })
const porDia = computed(() => data.value?.datos.por_dia ?? [])
const sinRegistros = computed(() => data.value?.datos.sin_registros ?? true)
</script>

<template>
  <section>
    <h2 class="titulo-seccion">Seguridad</h2>

    <p v-if="error" class="estado-falla">
      No se pudieron obtener los contadores de seguridad en este momento.
    </p>

    <div class="cifras">
      <div class="cifra">
        <p class="cifra__valor">{{ totales.detectados.toLocaleString('es-CL') }}</p>
        <p class="cifra__etiqueta">Ataques detectados</p>
      </div>
      <div class="cifra">
        <p class="cifra__valor">{{ totales.bloqueados.toLocaleString('es-CL') }}</p>
        <p class="cifra__etiqueta">Bloqueos aplicados</p>
      </div>
    </div>

    <p v-if="sinRegistros" class="estado-vacio">
      Todavia no hay contadores para esta empresa. Suele significar que la
      integracion con CrowdSec esta desactivada.
    </p>

    <ul v-else class="dias">
      <li v-for="dia in porDia" :key="(dia as { dia: string }).dia">
        <span>{{ (dia as { dia: string }).dia }}</span>
        <span>{{ (dia as { detectados: number }).detectados }} detectados</span>
        <span>{{ (dia as { bloqueados: number }).bloqueados }} bloqueados</span>
      </li>
    </ul>
  </section>
</template>

<style scoped>
.cifras { display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem; }
.cifra { background: var(--tarjeta); border: 1px solid var(--borde); border-radius: 10px; padding: 1rem 1.4rem; }
.cifra__valor { margin: 0; font-size: 1.8rem; font-weight: 600; }
.cifra__etiqueta { margin: 0; color: var(--texto-suave); font-size: 0.85rem; }
.dias { list-style: none; padding: 0; display: grid; gap: 0.4rem; }
.dias li { display: flex; justify-content: space-between; background: var(--tarjeta); border: 1px solid var(--borde); border-radius: 8px; padding: 0.5rem 0.8rem; font-size: 0.9rem; }
.titulo-seccion { margin-top: 0; }
.estado-falla { color: var(--falla); }
.estado-vacio { color: var(--texto-suave); }
</style>