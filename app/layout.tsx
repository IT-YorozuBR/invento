import type { Metadata } from 'next'
import './globals.css'
import Script from 'next/script'

export const metadata: Metadata = {
  title: 'Invento — Sistema de Inventário',
  description: 'Controle de Inventário Profissional',
}

export default function RootLayout({
  children,
}: {
  children: React.ReactNode
}) {
  return (
    <html lang="pt-BR">
      <head>
        <link rel="stylesheet" href="/assets/css/app.css" />
        <link
          rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
        />
      </head>

      <body>
        {children}

        {/* html5-qrcode */}
        <Script
          src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"
          strategy="afterInteractive"
        />

        {/* Seu JS */}

        <Script
          src="/assets/js/app.js"
          strategy="afterInteractive"
        />
      </body>
    </html>
  )
}