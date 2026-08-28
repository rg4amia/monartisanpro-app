'use client';

import { useState, useEffect } from 'react';
import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { Menu, X, ArrowRight, ShieldCheck, HeartHandshake, Store } from 'lucide-react';
import { motion, AnimatePresence } from 'framer-motion';

export default function Navbar() {
    const [isOpen, setIsOpen] = useState(false);
    const [scrolled, setScrolled] = useState(false);
    const pathname = usePathname();

    useEffect(() => {
        const handleScroll = () => {
            if (window.scrollY > 20) {
                setScrolled(true);
            } else {
                setScrolled(false);
            }
        };

        window.addEventListener('scroll', handleScroll);
        return () => window.removeEventListener('scroll', handleScroll);
    }, []);

    const navLinks = [
        { name: 'Accueil', href: '/' },
        { name: 'Services', href: '/services' },
        { name: 'Artisans', href: '/artisans' },
        { name: 'Formations', href: '/formations' },
        { name: 'Actualités', href: '/actualites' },
        { name: 'Recrutement', href: '/recrutement' },
        { name: 'Contact', href: '/contact' },
    ];

    const isActive = (href: string) => {
        if (href === '/') return pathname === '/';
        return pathname.startsWith(href);
    };

    return (
        <header
            className={`fixed top-0 left-0 right-0 z-50 transition-all duration-300 ${
                scrolled
                    ? 'bg-white/80 backdrop-blur-md shadow-[0_4px_30px_rgba(36,27,22,0.05)] border-b border-[#e6d3b2]/20 py-3'
                    : 'bg-transparent py-5'
            }`}
        >
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="flex items-center justify-between">
                    {/* Logo */}
                    <Link href="/" className="flex items-center gap-2.5 group">
                        <img
                            src="/img/prosartisan-logo.png"
                            alt="ProsArtisan"
                            className="h-10 w-10 object-contain group-hover:scale-105 transition transform"
                        />
                        <div>
                            <span className="font-extrabold text-xl tracking-tight text-[#241b16] group-hover:text-[#8a5d16] transition">
                                ProsArtisan
                            </span>
                            <span className="block text-[9px] font-bold tracking-widest text-[#8a5d16] uppercase">
                                label de confiance
                            </span>
                        </div>
                    </Link>

                    {/* Desktop Navigation */}
                    <nav className="hidden lg:flex items-center gap-1">
                        {navLinks.map((link) => (
                            <Link
                                key={link.href}
                                href={link.href}
                                className={`px-4 py-2 rounded-full text-sm font-semibold transition-all relative ${
                                    isActive(link.href)
                                        ? 'text-[#8a5d16]'
                                        : 'text-[#746251] hover:text-[#241b16] hover:bg-[#efe6da]/40'
                                }`}
                            >
                                {isActive(link.href) && (
                                    <motion.span
                                        layoutId="activeNavTab"
                                        className="absolute inset-0 bg-[#f7efe2] rounded-full -z-10 border border-[#e6d3b2]/40"
                                        transition={{ type: 'spring', stiffness: 380, damping: 30 }}
                                    />
                                )}
                                {link.name}
                            </Link>
                        ))}
                    </nav>

                    {/* Desktop CTA */}
                    <div className="hidden lg:flex items-center gap-3">
                        <Link
                            href="/supplier/login"
                            className="text-[#746251] hover:text-[#8a5d16] border border-[#e6d3b2] hover:border-[#8a5d16] bg-white/60 hover:bg-[#f7efe2] text-xs font-bold px-4 py-2.5 rounded-full flex items-center gap-1.5 transition shadow-sm active:scale-95"
                        >
                            <Store className="h-3.5 w-3.5 text-[#8a5d16]" />
                            <span>Espace Fournisseur</span>
                        </Link>
                        <Link
                            href="/contact"
                            className="bg-[#241b16] hover:bg-[#8a5d16] text-[#fbf9f6] text-xs font-bold px-5 py-3 rounded-full flex items-center gap-1.5 transition shadow-md hover:shadow-lg active:scale-95 transform"
                        >
                            <span>Trouver un pro</span>
                            <ArrowRight className="h-3.5 w-3.5" />
                        </Link>
                    </div>

                    {/* Mobile Menu Button */}
                    <button
                        onClick={() => setIsOpen(!isOpen)}
                        className="lg:hidden p-2 rounded-xl hover:bg-[#efe6da]/40 text-[#241b16] transition"
                        aria-label="Toggle menu"
                    >
                        {isOpen ? <X className="h-6 w-6" /> : <Menu className="h-6 w-6" />}
                    </button>
                </div>
            </div>

            {/* Mobile Navigation Drawer */}
            <AnimatePresence>
                {isOpen && (
                    <motion.div
                        initial={{ opacity: 0, height: 0 }}
                        animate={{ opacity: 1, height: 'auto' }}
                        exit={{ opacity: 0, height: 0 }}
                        className="lg:hidden bg-white border-b border-[#e6d3b2]/30 shadow-inner"
                    >
                        <div className="px-4 pt-2 pb-6 space-y-2">
                            {navLinks.map((link) => (
                                <Link
                                    key={link.href}
                                    onClick={() => setIsOpen(false)}
                                    href={link.href}
                                    className={`block px-4 py-3 rounded-xl text-base font-bold transition ${
                                        isActive(link.href)
                                            ? 'bg-[#f7efe2] text-[#8a5d16] border-l-4 border-[#8a5d16]'
                                            : 'text-[#746251] hover:bg-[#efe6da]/30 hover:text-[#241b16]'
                                    }`}
                                >
                                    {link.name}
                                </Link>
                            ))}
                            <div className="pt-4 border-t border-[#e6d3b2]/20 flex flex-col gap-3">
                                <Link
                                    onClick={() => setIsOpen(false)}
                                    href="/supplier/login"
                                    className="w-full text-center border border-[#8a5d16] text-[#8a5d16] hover:bg-[#f7efe2] font-bold py-3 rounded-xl flex items-center justify-center gap-2 transition text-sm"
                                >
                                    <Store className="h-4 w-4" />
                                    <span>Espace Fournisseur (Quincaillerie)</span>
                                </Link>
                                <Link
                                    onClick={() => setIsOpen(false)}
                                    href="/contact"
                                    className="w-full text-center bg-[#241b16] hover:bg-[#8a5d16] text-[#fbf9f6] font-bold py-3.5 rounded-xl transition"
                                >
                                    Trouver un pro
                                </Link>
                            </div>
                        </div>
                    </motion.div>
                )}
            </AnimatePresence>
        </header>
    );
}
