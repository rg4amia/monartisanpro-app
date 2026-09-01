import type { Metadata } from "next";
import Script from "next/script";
import "./globals.css";
import Navbar from "@/components/Navbar";
import Footer from "@/components/Footer";
import PopupModal from "@/components/PopupModal";
import CookieConsent from "@/components/CookieConsent";

export const metadata: Metadata = {
  title: "ProsArtisan — Plateforme Artisanale de Confiance en Côte d'Ivoire",
  description: "Mise en relation avec des artisans qualifiés en Côte d'Ivoire. Séquestre sécurisé Wave & Orange Money, J-Codes matériaux, diagnostic IA et suivi de chantier.",
  icons: {
    icon: [
      { url: "/favicon.ico" },
      { url: "/favicon.png", sizes: "512x512", type: "image/png" },
    ],
    shortcut: "/favicon.ico",
    apple: [
      { url: "/apple-touch-icon.png", sizes: "180x180", type: "image/png" },
    ],
  },
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang="fr" className="h-full antialiased">
      <head>
        {/* Google tag (gtag.js) */}
        <Script
          strategy="afterInteractive"
          src="https://www.googletagmanager.com/gtag/js?id=G-JZ32VTRQSP"
        />
        <Script
          id="google-analytics"
          strategy="afterInteractive"
        >
          {`
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', 'G-JZ32VTRQSP');
          `}
        </Script>
      </head>
      <body className="min-h-full flex flex-col bg-[#fbf9f6] text-[#241b16]">
        <Navbar />
        <main className="flex-grow pt-20">
          {children}
        </main>
        <Footer />
        <PopupModal />
        <CookieConsent />
      </body>
    </html>
  );
}
