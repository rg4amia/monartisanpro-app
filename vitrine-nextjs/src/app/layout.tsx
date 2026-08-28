import type { Metadata } from "next";
import "./globals.css";
import Navbar from "@/components/Navbar";
import Footer from "@/components/Footer";
import PopupModal from "@/components/PopupModal";

export const metadata: Metadata = {
  title: "ProsArtisan — Plateforme Artisanale de Confiance en Côte d'Ivoire",
  description: "Mise en relation avec des artisans qualifiés en Côte d'Ivoire. Séquestre sécurisé Wave & Orange Money, J-Codes matériaux, diagnostic IA et suivi de chantier.",
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang="fr" className="h-full antialiased">
      <body className="min-h-full flex flex-col bg-[#fbf9f6] text-[#241b16]">
        <Navbar />
        <main className="flex-grow pt-20">
          {children}
        </main>
        <Footer />
        <PopupModal />
      </body>
    </html>
  );
}
