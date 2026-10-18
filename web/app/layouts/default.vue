<script setup lang="ts">
const enlaces = [
  { to: '/', texto: 'Resumen' },
  { to: '/sitios', texto: 'Sitios' },
  { to: '/respaldos', texto: 'Respaldos' },
  { to: '/seguridad', texto: 'Seguridad' },
  { to: '/uso', texto: 'Uso' },
]
</script>

<template>
  <div class="panel">
    <header class="panel__cabecera">
      <p class="panel__marca">Hefesto Studio</p>
      <h1 class="panel__titulo">Panel de Operacion</h1>
      <p class="panel__aviso">
        Vista de solo lectura. Para iniciar un respaldo, reiniciar un servicio o
        pedir un cambio, contacte a Hefesto.
      </p>
    </header>

    <nav class="panel__menu">
      <NuxtLink v-for="enlace in enlaces" :key="enlace.to" :to="enlace.to" class="panel__enlace">
        {{ enlace.texto }}
      </NuxtLink>
    </nav>

    <main class="panel__contenido">
      <slot />
    </main>

    <footer class="panel__pie">
      <p>
        Los datos que ve aqui se consultan desde los sistemas de Hefesto y se
        guardan en cache unos minutos. Cada pantalla indica de cuando son.
      </p>
    </footer>
  </div>
</template>

<style>
:root {
  --fondo: #f6f5f2;
  --tarjeta: #ffffff;
  --texto: #1f2933;
  --texto-suave: #52606d;
  --borde: #dfe3e8;
  --ok: #1f7a4d;
  --aviso: #8a5a00;
  --falla: #a32020;
  --ausente: #52606d;
}

body {
  margin: 0;
  background: var(--fondo);
  color: var(--texto);
  font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
}

.panel { max-width: 1100px; margin: 0 auto; padding: 1.5rem; }
.panel__cabecera { margin-bottom: 1rem; }
.panel__marca { margin: 0; font-size: 0.85rem; letter-spacing: 0.08em; text-transform: uppercase; color: var(--texto-suave); }
.panel__titulo { margin: 0.2rem 0; font-size: 1.6rem; }
.panel__aviso { margin: 0; color: var(--texto-suave); font-size: 0.9rem; }
.panel__menu { display: flex; gap: 0.5rem; flex-wrap: wrap; border-bottom: 1px solid var(--borde); padding-bottom: 0.5rem; margin-bottom: 1.5rem; }
.panel__enlace { padding: 0.35rem 0.7rem; border-radius: 6px; text-decoration: none; color: var(--texto-suave); }
.panel__enlace.router-link-active { background: var(--texto); color: #fff; }
.panel__contenido { min-height: 60vh; }
.panel__pie { margin-top: 2rem; border-top: 1px solid var(--borde); padding-top: 0.75rem; color: var(--texto-suave); font-size: 0.85rem; }
</style>