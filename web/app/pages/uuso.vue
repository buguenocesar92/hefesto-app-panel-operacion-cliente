<script setup lang="ts">
/**
 * Modulo 5: Uso.
 *
 * Aqui NO hay facturacion, y la pantalla lo dice. Hefesto ya tiene un sistema
 * de facturacion: duplicarlo en el panel seria mostrar dos cifras distintas
 * del mismo mes, y el cliente creeria en la que le conviene.
 */
const { uso } = usePanel()
const { data } = await uso()

const recursos = computed(() => data.value?.datos.recursos ?? [])
const notaFacturacion = computed(() => data.value?.datos.nota_facturacion ?? '')
</script>

<template>
  <section>
    <h2 class="titulo-seccion">Uso de recursos</h2>

    <ul class="lista">
      <li v-for="recurso in recursos" :key="(recurso as { recurso: string }).recurso">
        <span>{{ (recurso as { recurso: string }).recurso }}</span>
        <span class="estado-texto">{{ (recurso as { hay_datos: boolean }).hay_datos ? 'con datos' : 'sin datos' }}</span>
        <span class="marca-tiempo">{{ tiempoDesde((recurso as { actualizado_en: string | null }).actualizado_en) }}</span>
      </li>
    </ul>

    <div class="facturacion">
      <h3>Facturacion</h3>
      <p>{{ notaFacturacion }}</p>
    </div>
  </section>
</template>

<style scoped>
.lista { list-style: none; padding: 0; display: grid; gap: 0.4rem; }
.lista li { display: flex; justify-content: space-between; gap: 1rem; background: var(--tarjeta); border: 1px solid var(--borde); border-radius: 8px; padding: 0.6rem 0.8rem; }
.estado-texto { color: var(--texto-suave); font-size: 0.85rem; }
.marca-tiempo { color: var(--texto-suave); font-size: 0.8rem; }
.facturacion { margin-top: 1.5rem; padding: 1rem; background: var(--tarjeta); border: 1px solid var(--borde); border-radius: 10px; }
.titulo-seccion { margin-top: 0; }
</style>