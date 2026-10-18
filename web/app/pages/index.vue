<script setup lang="ts">
/**
 * Modulo 1: Resumen. Una tarjeta por sistema integrado.
 */
const { resumen } = usePanel()
const { data, error } = await resumen()

const tarjetas = computed(() => data.value?.datos.tarjetas ?? [])
const genero = computed(() => data.value?.datos.generado_en ?? null)
</script>

<template>
  <section>
    <h2 class="titulo-seccion">Resumen de la operacion</h2>

    <p v-if="error" class="estado-falla">
      No se pudo obtener el resumen en este momento. Vuelva a intentar en unos
      minutos; el resto del panel sigue funcionando.
    </p>

    <p class="marca-tiempo">
      Esta pantalla se genero {{ tiempoDesde(genero) }}.
    </p>

    <div class="rejilla">
      <TarjetaIntegracion v-for="tarjeta in tarjetas" :key="tarjeta.sistema" :tarjeta="tarjeta" />
    </div>

    <p v-if="tarjetas.length === 0" class="estado-vacio">
      Todavia no hay integraciones configuradas en esta instalacion.
    </p>
  </section>
</template>

<style scoped>
.rejilla { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); }
.titulo-seccion { margin-top: 0; }
.marca-tiempo { color: var(--texto-suave); font-size: 0.9rem; }
.estado-falla { color: var(--falla); }
.estado-vacio { color: var(--texto-suave); }
</style>