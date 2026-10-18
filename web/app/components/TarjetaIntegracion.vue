<script setup lang="ts">
import type { TarjetaIntegracion } from '~/composables/usePanel'

/**
 * Tarjeta de un sistema integrado.
 *
 * Muestra SIEMPRE cuatro cosas: que es, en que estado esta, por que si no
 * esta bien, y de cuando son los datos. Sin la cuarta, el cliente puede
 * tomar un dato de hace diez minutos por uno en vivo.
 */
defineProps<{ tarjeta: TarjetaIntegracion }>()
</script>

<template>
  <article class="tarjeta" :data-estado="tarjeta.estado">
    <header class="tarjeta__cabecera">
      <h3 class="tarjeta__nombre">{{ tarjeta.nombre }}</h3>
      <IndicadorDeEstado :tarjeta="tarjeta" />
    </header>

    <p class="tarjeta__aporte">{{ tarjeta.que_aporta }}</p>

    <p class="tarjeta__mensaje">{{ tarjeta.mensaje }}</p>

    <footer class="tarjeta__pie">
      <span class="marca-tiempo">Actualizado {{ tiempoDesde(tarjeta.actualizado_en) }}</span>
      <span v-if="tarjeta.desde_cache" class="insignia insignia--cache">en cache</span>
    </footer>
  </article>
</template>

<style scoped>
.tarjeta {
  background: var(--tarjeta);
  border: 1px solid var(--borde);
  border-radius: 10px;
  padding: 1rem;
  border-left-width: 4px;
}
.tarjeta[data-estado='disponible'] { border-left-color: var(--ok); }
.tarjeta[data-estado='datos_viejos'] { border-left-color: var(--aviso); }
.tarjeta[data-estado='no_disponible'] { border-left-color: var(--falla); }
.tarjeta[data-estado='desactivada'],
.tarjeta[data-estado='no_configurada'] { border-left-color: var(--ausente); }

.tarjeta__cabecera { display: flex; justify-content: space-between; align-items: center; gap: 0.5rem; }
.tarjeta__nombre { margin: 0; font-size: 1.05rem; }
.tarjeta__aporte { margin: 0.4rem 0; color: var(--texto-suave); font-size: 0.88rem; }
.tarjeta__mensaje { margin: 0.5rem 0; font-size: 0.9rem; }
.tarjeta__pie { display: flex; justify-content: space-between; align-items: center; font-size: 0.8rem; color: var(--texto-suave); }
.insignia { padding: 0.1rem 0.4rem; border-radius: 4px; background: #eef1f4; }
</style>