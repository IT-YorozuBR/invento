/** @type {import('next').NextConfig} */
const nextConfig = {
  // Permite uso do iron-session com edge runtime desativado para API routes
  experimental: {},
  // Gera build standalone (server.js autocontido) para imagem Docker enxuta
  output: 'standalone',
}

module.exports = nextConfig
