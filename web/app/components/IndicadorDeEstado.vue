<script setup lang="ts">
import type { TarjetaIntegracion } from '~/composables/usePanel'

/**
 * Distingue con honestidad cuatro situaciones que un tablero suele mezclar:
 * funcionando, con datos antiguos, caido, y apagado a proposito.
 *
 * "Desactivada" no es un error: es una decision documentada. Se muestra con
 * otro color justamente para que nadie la confunda con una falla.
 */
defineProps<{ tarjeta: Pick<TarjetaIntegracion, 'estado' | 'estado_texto' | 'requiere_atencion'> }>()
</script>

<template>
  <span class="estado" :class="`estado--${tarjeta.estado}`">
    {{ tarjeta.estado_texto }}
    <span v-if="tarjeta.requiere_atencion" class="estado__alerta" aria-label="requiere atencion">!</span>
  </span>
</template>

<style scoped>
.estado { font-size: 0.78rem; padding: 0.15rem 0.5rem; border-radius: 999px; background: #eef1f4; color: var(--texto-suave); white-space: nowrap; }
.estado--disponible { background: #e3f3ea; color: var(--ok); }
.estado--datos_viejos { background: #fbf1dc; color: var(--aviso); }
.estado--no_disponible { background: #fbe6e6; color: var(--falla); }
.estado__alerta { font-weight: 700; margin-left: 0.2rem; }
</style>