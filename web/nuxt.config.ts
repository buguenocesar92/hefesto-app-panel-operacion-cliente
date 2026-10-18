import { defineNuxtConfig } from 'nuxt'

/**
 * Configuracion del frontend del Panel de Operacion del Cliente.
 *
 * El token de acceso NO entra aqui. Vive en una cookie de solo lectura para
 * el navegador (httpOnly) y se usa unicamente en el servidor de Nuxt, que es
 * el unico que habla con la API de Laravel. Asi el token no aparece en el
 * codigo que se descarga el navegador.
 */
export default defineNuxtConfig({
  // SSR obligatorio: el panel muestra datos de estado y el cliente debe ver
  // la pantalla aunque JavaScript tarde en cargar o no llegue a cargar.
  ssr: true,

  devtools: { enabled: false },

  runtimeConfig: {
    // Direccion interna de la API. Se resuelve en el servidor de Nuxt.
    apiInterna: process.env.API_INTERNA || 'http://127.0.0.1:8000',

    public: {
      nombrePanel: process.env.NOMBRE_PANEL || 'Panel de Operacion del Cliente',
    },
  },

  typescript: {
    strict: true,
  },
})