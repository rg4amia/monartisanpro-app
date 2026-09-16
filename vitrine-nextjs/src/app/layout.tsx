import type { Metadata } from "next";
import "./globals.css";
import Navbar from "@/components/Navbar";
import Footer from "@/components/Footer";
import PopupModal from "@/components/PopupModal";
import CookieConsent from "@/components/CookieConsent";
import WhatsAppButton from "@/components/WhatsAppButton";
import GoogleAnalytics from "@/components/GoogleAnalytics";

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
      <body className="min-h-full flex flex-col bg-[#fbf9f6] text-[#241b16]">
        <GoogleAnalytics />
        <Navbar />
        <main className="flex-grow pt-20">
          {children}
        </main>
        <Footer />
        <PopupModal />
        <CookieConsent />
        <WhatsAppButton />
      </body>
    </html>
  );
}
