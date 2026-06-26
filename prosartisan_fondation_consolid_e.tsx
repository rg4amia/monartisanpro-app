// @ts-nocheck
import React, { useState, useEffect } from 'react';

// --- 1. DICTIONNAIRE SVG NATIF (Anti-Crash) ---
const Svg=({children,size=24,className="",fill="none",onClick})=> <svg onClick={onClick} xmlns="http://www.w3.org/2000/svg" width={size} height={size} viewBox="0 0 24 24" fill={fill} stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className}>{children}</svg>;
const MapMarker = ({ x, y, onClick, className, children }) => {
    const ref = React.useRef(null);
    React.useEffect(() => {
        if (ref.current) {
            ref.current.style.left = `${x}%`;
            ref.current.style.top = `${y}%`;
        }
    }, [x, y]);
    return (
        <div ref={ref} className={className} onClick={onClick}>
            {children}
        </div>
    );
};
const ICONS = {
  Search: <><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></>, Home: <><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></>,
  User: <><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></>, MapPin: <><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></>,
  Map: <><polygon points="3 6 9 3 15 6 21 3 21 18 15 21 9 18 3 21"/><line x1="9" x2="9" y1="3" y2="18"/><line x1="15" x2="15" y1="6" y2="21"/></>, List: <><line x1="8" x2="21" y1="6" y2="6"/><line x1="8" x2="21" y1="12" y2="12"/><line x1="8" x2="21" y1="18" y2="18"/><line x1="3" x2="3.01" y1="6" y2="6"/><line x1="3" x2="3.01" y1="12" y2="12"/><line x1="3" x2="3.01" y1="18" y2="18"/></>,
  Star: <><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></>, Wrench: <><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></>,
  Zap: <><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></>, Droplet: <><path d="M12 22a7 7 0 0 0 7-7c0-2-1-3.9-3-5.5s-3.5-4-4-6.5c-.5 2.5-2 4.9-4 6.5C6 11.1 5 13 5 15a7 7 0 0 0 7 7z"/></>,
  Hammer: <><path d="m15 12-8.5 8.5c-.83.83-2.17.83-3 0 0 0 0 0 0 0a2.12 2.12 0 0 1 0-3L12 9"/><path d="M17.64 15 22 10.64"/><path d="m20.91 11.7-1.25-1.25c-.6-.6-.93-1.4-.93-2.25v-.86L16.01 4.6a5.56 5.56 0 0 0-3.94-1.64H9l.92.82A6.18 6.18 0 0 1 12 8.4v1.56l2 2h2.47l2.26 1.91"/></>,
  ChevronLeft: <><path d="m15 18-6-6 6-6"/></>, CheckCircle: <><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></>, X: <><path d="M18 6 6 18"/><path d="m6 6 12 12"/></>,
  AlertTriangle: <><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></>, Loader2: <><path d="M21 12a9 9 0 1 1-6.219-8.56"/></>,
  ArrowRight: <><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></>, History: <><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/></>,
  Send: <><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></>, Upload: <><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/></>,
  Navigation: <><polygon points="3 11 22 2 13 21 11 13 3 11"/></>, LogOut: <><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></>,
  Bell: <><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></>, Check: <><polyline points="20 6 9 17 4 12"/></>,
  Edit2: <><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></>, Phone: <><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></>,
  Mail: <><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></>, Save: <><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></>,
  FileText: <><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/><line x1="10" x2="8" y1="9" y2="9"/></>, ClipboardList: <><rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/></>,
  Clock: <><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></>, Plus: <><line x1="12" x2="12" y1="5" y2="19"/><line x1="5" x2="19" y1="12" y2="12"/></>,
  Trash2: <><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></>, Calculator: <><rect width="16" height="20" x="4" y="2" rx="2"/><line x1="8" x2="16" y1="6" y2="6"/><line x1="16" x2="16" y1="14" y2="18"/><path d="M16 10h.01"/><path d="M12 10h.01"/><path d="M8 10h.01"/><path d="M12 14h.01"/><path d="M8 14h.01"/><path d="M12 18h.01"/><path d="M8 18h.01"/></>,
  Camera: <><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="3"/></>, Store: <><path d="m2 7 4.41-4.41A2 2 0 0 1 7.83 2h8.34a2 2 0 0 1 1.42.59L22 7"/><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><path d="M15 22v-4a2 2 0 0 0-2-2h-2a2 2 0 0 0-2-2v4"/><path d="M2 7h20"/><path d="M22 7v3a2 2 0 0 1-2 2v0a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 16 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 12 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 8 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 4 12v0a2 2 0 0 1-2-2V7"/></>,
  Package: <><line x1="16.5" x2="7.5" y1="9.4" y2="4.21"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" x2="12" y1="22.08" y2="12"/></>, ShoppingCart: <><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></>,
  Lock: <><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></>, Tag: <><path d="M12 2H2v10l9.29 9.29c.94.94 2.48.94 3.42 0l6.58-6.58c.94-.94.94-2.48 0-3.42L12 2Z"/><path d="M7 7h.01"/></>,
  Truck: <><path d="M10 17h4V5H2v12h3"/><path d="M20 17h2v-3.34a4 4 0 0 0-1.17-2.83L19 9h-5v8h2"/><path d="M14 17h1"/><circle cx="7.5" cy="17.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/></>, Smartphone: <><rect width="14" height="20" x="5" y="2" rx="2" ry="2"/><path d="M12 18h.01"/></>,
  Activity: <><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></>, Info: <><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></>, 
  Settings: <><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></>,
  Briefcase: <><rect width="20" height="14" x="2" y="7" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></>, ArrowDown: <><line x1="12" x2="12" y1="5" y2="19"/><polyline points="19 12 12 19 5 12"/></>,
  QrCode: <><rect width="5" height="5" x="3" y="3" rx="1"/><rect width="5" height="5" x="16" y="3" rx="1"/><rect width="5" height="5" x="3" y="16" rx="1"/><path d="M21 16h-3a2 2 0 0 0-2 2v3"/><path d="M21 21v.01"/><path d="M12 7v3a2 2 0 0 1-2 2H7"/><path d="M3 12h.01"/><path d="M12 3h.01"/><path d="M12 16v.01"/><path d="M16 12h1"/><path d="M21 12v.01"/><path d="M12 21v-1"/></>,
  Scan: <><path d="M3 7V5a2 2 0 0 1 2-2h2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/><path d="M21 17v2a2 2 0 0 1-2 2h-2"/><path d="M7 21H5a2 2 0 0 1-2-2v-2"/></>, Shield: <><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></>,
  Bank: <><rect width="20" height="14" x="2" y="6" rx="2"/><path d="M2 10h20"/><path d="M6 14h.01"/><path d="M10 14h.01"/><path d="M14 14h.01"/><path d="M18 14h.01"/></>,
  Sparkles: <><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/><path d="M5 3v4"/><path d="M19 17v4"/><path d="M3 5h4"/><path d="M17 19h4"/></>, Wallet: <><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/></>,
  MapPinned: <><path d="M18 8c0 4.5-6 9-6 9s-6-4.5-6-9a6 6 0 0 1 12 0z"/><circle cx="12" cy="8" r="2"/></>,
  Moon: <><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></>,
  Users: <><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></>
};
const Icon = ({ name, size=24, className="", fill="none", onClick }) => <Svg onClick={onClick} size={size} className={className} fill={fill}>{ICONS[name]||<circle cx="12" cy="12" r="10"/>}</Svg>;

const generateUUID = () => typeof crypto !== 'undefined' && crypto.randomUUID ? crypto.randomUUID() : Math.random().toString(36).substring(2, 15);

// Sous-composant utilitaire pour l'upload d'images stylisé (KYC & Plans PDF)
const ImageUploadField = ({ label, image, onChange, isAvatar = false, iconName = "Upload", hint }) => {
    const isPdf = image && typeof image === 'string' && image.startsWith('data:application/pdf');

    return (
        <div className="mb-4 flex flex-col items-center w-full">
            {label && !isAvatar && <label className="block text-xs font-bold text-gray-700 uppercase mb-1 w-full">{label}</label>}
            {hint && <p className="text-[10px] text-gray-500 mb-2 w-full">{hint}</p>}
            <label className={`flex flex-col items-center justify-center bg-gray-50 border-2 border-dashed border-gray-300 hover:bg-gray-100 cursor-pointer relative overflow-hidden transition-colors w-full ${isAvatar ? 'w-24 h-24 rounded-full' : 'h-28 rounded-xl'}`}>
                {image ? (
                    isPdf ? (
                        <div className="flex flex-col items-center justify-center w-full h-full bg-indigo-50 text-indigo-600 p-4 text-center">
                            <Icon name="FileText" size={32} className="mb-2"/>
                            <span className="text-[10px] font-bold">Document PDF joint avec succès</span>
                        </div>
                    ) : (
                        <img src={image} className="w-full h-full object-cover" alt="upload" />
                    )
                ) : (
                    <div className="flex flex-col items-center text-gray-400 p-2 text-center">
                        <Icon name={isAvatar ? "Camera" : iconName} size={24} className="mb-1" />
                        <span className="text-[10px] font-bold">{isAvatar ? 'Photo' : 'Appuyer pour joindre un fichier (PDF ou Image)'}</span>
                    </div>
                )}
                <input type="file" accept="image/*,application/pdf" onChange={onChange} className="hidden" aria-label={label || (isAvatar ? "Photo" : "Joindre un fichier (PDF ou Image)")} />
            </label>
        </div>
    );
};

// --- 2. DONNÉES INITIALES ET MOCK DB ---
const INITIAL_CATEGORIES = [
  { id: 'renovation', name: 'Rénovation Globale (Maître d\'Œuvre)', iconName: 'Briefcase', color: 'bg-indigo-100 text-indigo-600' },
  { id: 'plomberie', name: 'Plomberie', iconName: 'Droplet', color: 'bg-blue-100 text-blue-600' },
  { id: 'electricite', name: 'Électricité', iconName: 'Zap', color: 'bg-yellow-100 text-yellow-600' },
  { id: 'menuiserie', name: 'Menuiserie', iconName: 'Hammer', color: 'bg-orange-100 text-orange-600' },
  { id: 'reparation', name: 'Réparation', iconName: 'Wrench', color: 'bg-gray-100 text-gray-600' },
  { id: 'livraison', name: 'Livraison', iconName: 'Truck', color: 'bg-teal-100 text-teal-600' }
];

const INITIAL_SUPPLIER_SECTORS = ["Quincaillerie", "Matériaux Construction", "Plomberie Sanitaire", "Électricité Éclairage"];

const MOCK_DB = {
  suppliers: [
      { id: 1, role: 'supplier', raisonSociale: "Quincaillerie Pro", adresse: "Abidjan, Adjamé", secteur: "Quincaillerie", contactMobile: "0707070701", password: "123", lat: 5.3555, lng: -4.0200, rating: 4.5, reviewCount: 45, isVerified: true, catalog: [{ id: 'mat1', sku: 'VIS-001', name: "Siphon PVC", price: 2500, stock: 50, image: null }, { id: 'mat2', sku: 'CIM-002', name: "Ciment Bélier 50kg", price: 4500, stock: 100, image: null }] }
  ],
  artisans: [
      { id: 1, role: 'artisan', name: "Kouassi", prenoms: "Jean", telephone: "0505050501", password: "123", category: "renovation", rating: 4.8, reviewCount: 120, location: "Abidjan, Cocody", lat: 5.3599, lng: -3.9897, price: "Sur devis", hasEmergencyKit: true, isVerified: true, isLeadContractor: true }, // MOE
      { id: 3, role: 'driver', name: "Koné", prenoms: "Moussa", telephone: "0707070705", password: "123", category: "livraison", rating: 4.9, reviewCount: 85, location: "Abidjan, Adjamé", lat: 5.3544, lng: -4.0222, price: "Sur devis", isVerified: true, isLeadContractor: false },
      { id: 4, role: 'artisan', name: "Diaby", prenoms: "Oumar", telephone: "0505050504", password: "123", category: "electricite", rating: 4.2, reviewCount: 15, location: "Abidjan, Yopougon", lat: 5.3344, lng: -4.0522, price: "Sur devis", hasEmergencyKit: false, isVerified: true, isLeadContractor: false },
      { id: 5, role: 'artisan', name: "Soro", prenoms: "Ali", telephone: "0505050505", password: "123", category: "plomberie", rating: 5.0, reviewCount: 1, location: "Abidjan, Marcory", lat: 5.3044, lng: -3.9822, price: "Sur devis", hasEmergencyKit: false, isVerified: true, isLeadContractor: false }
  ],
  jobs: [
      { id: 202, clientId: 999, clientName: "Paul A.", problem: "Changement de tuyauterie salle de bain.", status: "sent", artisanId: 5, artisanName: "Soro Ali", artisanCategory: "plomberie", jobType: 'standard', paymentStatus: 'pending', financials: { tokenCode: null, tokenAmount: 0, laborCost: 0, platformFeesBreakdown: { labor: 0, material: 0, delivery: 0 } }, interventionData: { materials: [] }, milestones: [{ id: 1, status: 'pending' }, { id: 2, status: 'pending' }], escrow: { total_budget: 0, currently_escrowed: 0, disbursed_to_subs: 0 }, subJobs: [] },
      { id: 999, clientId: 999, clientName: "Paul A.", problem: "Construction Villa R+1 (Gros Oeuvre)", status: "funded", artisanId: 1, artisanName: "Kouassi Jean", artisanCategory: "renovation", jobType: 'macro', paymentStatus: 'funded', financials: { tokenCode: null, tokenAmount: 0, laborCost: 5000000, platformFeesBreakdown: { labor: 500000, material: 0, delivery: 0 } }, interventionData: { materials: [] }, milestones: [{ id: 1, name: 'Démarrage (30%)', amount: 1500000, status: 'paid' }, { id: 2, name: 'Gros Oeuvre (40%)', amount: 2000000, status: 'pending' }, { id: 3, name: 'Finitions (30%)', amount: 1500000, status: 'pending' }], escrow: { total_budget: 5000000, currently_escrowed: 1500000, disbursed_to_subs: 0 }, subJobs: [] }
  ],
  orders: [
      { id: 301, clientId: 999, clientName: "Marc B.", clientPhone: "0707070707", clientAddress: "Abidjan, Plateau", supplierId: 1, supplierName: "Quincaillerie Pro", driverId: 3, deliveryMode: 'delivery', subtotal: 5000, status: 'searching_driver', deliveryCost: 1500, deliveryCode: 'LIVREUR-9999', driverToClientCode: 'LIV-8888', clientReceiveCode: 'RECEPTION-8888', platformFeesBreakdown: { material: 400, delivery: 75, labor: 0 }, total: 6975, items: [{ id: 'mat2', name: "Ciment", quantity: 1, price: 5000, image: null }], rejectedBy: [] }
  ]
};

// --- 3. COMPOSANTS UTILITAIRES ---
const Button = ({ children, onClick, variant = 'primary', className = '', iconName, disabled, type = "button" }) => {
  const v = { primary: "bg-blue-600 text-white hover:bg-blue-700", secondary: "bg-white text-gray-700 border hover:bg-gray-50", ai: "bg-gradient-to-r from-purple-600 to-blue-600 text-white", success: "bg-green-600 text-white hover:bg-green-700", danger: "bg-red-50 text-red-600 border border-red-100 hover:bg-red-100", ghost: "bg-transparent text-gray-500 hover:bg-gray-100", outline: "border-2 border-blue-600 text-blue-600 bg-white hover:bg-blue-50", admin: "bg-slate-800 text-white hover:bg-slate-900" };
  return <button onClick={onClick} type={type} disabled={disabled} className={`px-4 py-3 rounded-xl font-medium flex items-center justify-center gap-2 active:scale-95 disabled:opacity-50 disabled:grayscale disabled:cursor-not-allowed ${v[variant]} ${className}`}>{iconName && <Icon name={iconName} size={18} />}{children}</button>;
};
const Badge = ({ text, color="bg-blue-50 text-blue-700 border-blue-100" }) => <span className={`px-2 py-1 text-[10px] font-bold rounded-md border ${color}`}>{text}</span>;
const InputGroup = ({ label, children }) => (
  <div className="mb-3">
    <label className="block text-xs font-bold text-gray-700 uppercase mb-1">
      {label}
      <span className="block mt-1 font-normal normal-case">{children}</span>
    </label>
  </div>
);
const BasicInput = ({value, onChange, placeholder, type="text", required=false, readOnly=false, ...props}) => {
  const labelText = placeholder || "Champ de saisie";
  return <input type={type} value={value} onChange={onChange} placeholder={placeholder} readOnly={readOnly} title={labelText} aria-label={labelText} className={`w-full bg-gray-50 border p-3 rounded-xl outline-none focus:border-blue-500 ${readOnly ? 'text-gray-500' : ''}`} required={required} {...props}/>;
};

const WorkflowTracker = ({ steps, currentStepIndex }) => (
    <div className="flex flex-col mb-4 bg-gray-50 p-3 rounded-xl border border-gray-100">
        <div className="flex items-center justify-between mb-2">
            {steps.map((_, idx) => (
                <div key={idx} className={`h-1.5 flex-1 mx-1 rounded-full ${idx <= currentStepIndex ? 'bg-blue-600' : 'bg-gray-200'}`} />
            ))}
        </div>
        <p className="text-xs font-bold text-blue-800 text-center">{steps[currentStepIndex]}</p>
    </div>
);

// Composant d'Édition de Profil Universel avec KYC
const ProfileEditor = ({ user, onSave, onLogout, reviews = [], avgRating = 0 }) => {
    const [f, setF] = useState({ ...user });
    const [loadingGps, setLoadingGps] = useState(false);
    const isPro = user.role === 'artisan' || user.role === 'driver';
    const isSupplier = user.role === 'supplier';

    const handleGetLocation = () => {
        setLoadingGps(true);
        setTimeout(() => {
            const newLat = 5.345317 + (Math.random() * 0.05 - 0.025);
            const newLng = -4.024429 + (Math.random() * 0.05 - 0.025);
            setF({ ...f, lat: newLat, lng: newLng, adresse: "Abidjan, Cocody (Géolocalisé)" });
            setLoadingGps(false);
        }, 1500);
    };

    const handleImageChange = (field) => (e) => {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onloadend = () => setF(prev => ({...prev, [field]: reader.result}));
            reader.readAsDataURL(file);
        }
    };

    const handleSave = () => {
        let updatedUser = { ...f };
        if (isPro) {
            updatedUser.isVerified = !!(updatedUser.photo && updatedUser.cniNumber && updatedUser.cniImage);
        } else if (isSupplier) {
            updatedUser.isVerified = !!(updatedUser.rccmNumber && updatedUser.rccmImage && updatedUser.ccNumber && updatedUser.ccImage && updatedUser.storefrontImage);
        }
        onSave(updatedUser);
    };

    return (
        <div className="pt-12 px-6 pb-24 animate-in fade-in">
            <div className="text-center mb-6">
                {isPro ? (
                    <ImageUploadField image={f.photo} onChange={handleImageChange('photo')} isAvatar={true} />
                ) : (
                    <div className="w-20 h-20 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center mx-auto mb-3"><Icon name="User" size={40}/></div>
                )}
                <h2 className="font-bold text-xl text-gray-800">Mon Profil</h2>
                <p className="text-xs text-gray-500 capitalize">{user.role} {(isPro || isSupplier) && (!f.isVerified ? '- Restreint' : '- Certifié')}</p>
                {user.isLeadContractor && <Badge text="Maître d'Œuvre" color="bg-indigo-100 text-indigo-800" />}
            </div>

            {(isPro || isSupplier) && !f.isVerified && (
                <div className="bg-red-50 p-4 rounded-xl border border-red-200 mb-6 animate-pulse">
                    <h3 className="font-bold text-sm text-red-800 flex items-center gap-2"><Icon name="AlertTriangle" size={16}/> Action Requise</h3>
                    <p className="text-xs text-red-600 mt-1">Remplissez la section KYC plus bas pour débloquer toutes les fonctionnalités.</p>
                </div>
            )}

            {(isPro || isSupplier) && (
                <div className="bg-gradient-to-r from-yellow-500 to-yellow-600 p-5 rounded-2xl text-white mb-6 shadow-lg">
                    <h3 className="font-bold text-sm mb-2 flex items-center gap-2"><Icon name="Star" fill="currentColor"/> Ma Réputation</h3>
                    <div className="flex items-end gap-3 mb-4">
                        <span className="text-4xl font-black">{avgRating}</span>
                        <span className="text-yellow-100 text-sm mb-1">/ 5 ({reviews.length} avis)</span>
                    </div>
                    {reviews.length > 0 ? (
                        <div className="space-y-2 max-h-40 overflow-y-auto pr-2 custom-scrollbar">
                            {reviews.map((r, i) => (
                                <div key={i} className="bg-white/10 p-3 rounded-lg text-xs border border-white/20">
                                    <div className="flex justify-between text-yellow-50 mb-1">
                                        <span className="font-bold">{r.clientName}</span>
                                        <span className="flex">{[...Array(5)].map((_,j)=><Icon key={j} name="Star" size={10} fill={j<r.rating?'currentColor':'none'} className={j<r.rating?'text-yellow-300':'text-yellow-700'}/>)}</span>
                                    </div>
                                    <p className="italic text-white">"{r.comment || 'Aucun commentaire'}"</p>
                                    {r.date && <p className="text-[9px] text-yellow-200 mt-1 text-right">{r.date}</p>}
                                </div>
                            ))}
                        </div>
                    ) : <p className="text-xs text-yellow-200">Aucun avis reçu pour le moment.</p>}
                </div>
            )}

            <div className="bg-white p-5 rounded-2xl shadow-sm border border-gray-100 space-y-4 mb-6">
                {isSupplier ? (
                    <InputGroup label="Raison Sociale"><BasicInput value={f.raisonSociale} onChange={e=>setF({...f, raisonSociale: e.target.value})} /></InputGroup>
                ) : (
                    <div className="grid grid-cols-2 gap-3">
                        <InputGroup label="Nom"><BasicInput value={f.nom || f.name || ''} onChange={e=>setF({...f, nom: e.target.value})} /></InputGroup>
                        <InputGroup label="Prénoms"><BasicInput value={f.prenoms || ''} onChange={e=>setF({...f, prenoms: e.target.value})} /></InputGroup>
                    </div>
                )}
                <InputGroup label="Téléphone"><BasicInput value={f.telephone || f.contactMobile || ''} onChange={e=>setF({...f, telephone: e.target.value})} /></InputGroup>
                <InputGroup label="Adresse de base">
                    <BasicInput value={f.adresse || f.location || f.adresseManuelle || ''} onChange={e=>setF({...f, adresse: e.target.value})} readOnly={isPro || isSupplier} placeholder={isPro || isSupplier ? "Définie via GPS" : ""} />
                </InputGroup>
                <InputGroup label="Mot de passe"><BasicInput type="password" value={f.password} onChange={e=>setF({...f, password: e.target.value})} /></InputGroup>
            </div>

            {(isPro || isSupplier) && (
                <div className="bg-blue-50 p-5 rounded-2xl border border-blue-100 mb-6">
                    <h3 className="font-bold text-sm text-blue-900 mb-2 flex items-center gap-2"><Icon name="MapPin" size={16}/> Localisation Exacte</h3>
                    <p className="text-xs text-blue-700 mb-4">L'utilisation du GPS remplace la saisie manuelle de l'adresse pour garantir des livraisons et des recherches précises.</p>
                    <div className="flex items-center justify-between bg-white p-3 rounded-xl border border-blue-200 mb-3">
                        <div><p className="text-[10px] text-gray-500 font-bold uppercase">Lat / Lng Actuel</p><p className="text-sm font-mono text-gray-800">{f.lat ? `${f.lat.toFixed(4)}, ${f.lng.toFixed(4)}` : 'Non défini'}</p></div>
                        {f.lat && <Icon name="CheckCircle" className="text-green-500"/>}
                    </div>
                    <Button variant="outline" className="w-full text-xs shadow-none bg-white border-blue-300" onClick={handleGetLocation} disabled={loadingGps}>
                        {loadingGps ? 'Analyse GPS...' : 'Définir ma position par GPS'}
                    </Button>
                </div>
            )}

            {isPro && (
                <div className="bg-gray-100 p-5 rounded-2xl border border-gray-200 mb-6">
                    <h3 className="font-bold text-sm text-gray-800 mb-4 flex items-center gap-2"><Icon name="Shield" size={16} className="text-blue-600"/> Vérification d'Identité (KYC)</h3>
                    <div className="space-y-4">
                        <InputGroup label="N° CNI / Passeport">
                            <BasicInput value={f.cniNumber || ''} onChange={e=>setF({...f, cniNumber: e.target.value})} placeholder="Ex: C0012345678" />
                        </InputGroup>
                        <ImageUploadField label="Photo de la pièce" image={f.cniImage} onChange={handleImageChange('cniImage')} />
                        <div className="pt-4 border-t border-gray-200">
                            <label className="flex items-start gap-3 cursor-pointer mb-3">
                                <input type="checkbox" title="J'ai une carte d'artisan ou licence pro" aria-label="J'ai une carte d'artisan ou licence pro" checked={f.hasArtisanCard || false} onChange={e=>setF({...f, hasArtisanCard: e.target.checked})} className="mt-1 w-5 h-5 accent-blue-600" />
                                <div><p className="text-sm font-bold text-gray-800">J'ai une {user.role === 'artisan' ? "carte d'artisan" : "licence pro"}</p></div>
                            </label>
                            {f.hasArtisanCard && (
                                <div className="space-y-4 animate-in fade-in">
                                    <InputGroup label="N° du document"><BasicInput value={f.artisanCardNumber || ''} onChange={e=>setF({...f, artisanCardNumber: e.target.value})} placeholder="Ex: ART-998877" /></InputGroup>
                                    <ImageUploadField label="Photo du document" image={f.artisanCardImage} onChange={handleImageChange('artisanCardImage')} />
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            )}

            {isSupplier && (
                <div className="bg-gray-100 p-5 rounded-2xl border border-gray-200 mb-6">
                    <h3 className="font-bold text-sm text-gray-800 mb-4 flex items-center gap-2"><Icon name="Shield" size={16} className="text-green-600"/> Vérification d'Entreprise (KYB)</h3>
                    <div className="space-y-6">
                        <div>
                            <InputGroup label="N° Registre de Commerce (RCCM)"><BasicInput value={f.rccmNumber || ''} onChange={e=>setF({...f, rccmNumber: e.target.value})} placeholder="Ex: CI-ABJ-2026-B-1234" /></InputGroup>
                            <ImageUploadField label="Photo du document RCCM" image={f.rccmImage} onChange={handleImageChange('rccmImage')} iconName="FileText" />
                        </div>
                        <div className="pt-4 border-t border-gray-200">
                            <InputGroup label="N° Compte Contribuable (CC)"><BasicInput value={f.ccNumber || ''} onChange={e=>setF({...f, ccNumber: e.target.value})} placeholder="Ex: 1234567A" /></InputGroup>
                            <ImageUploadField label="Déclaration Fiscale (DFE) ou CC" image={f.ccImage} onChange={handleImageChange('ccImage')} iconName="FileText" />
                        </div>
                        <div className="pt-4 border-t border-gray-200">
                            <p className="text-xs font-bold text-gray-700 uppercase mb-2">Photo de la Façade (Magasin)</p>
                            <p className="text-[10px] text-gray-500 mb-3">Indispensable pour que les livreurs et clients repèrent rapidement votre boutique.</p>
                            <ImageUploadField image={f.storefrontImage} onChange={handleImageChange('storefrontImage')} iconName="Store" />
                        </div>
                    </div>
                </div>
            )}

            {user.role === 'artisan' && (
                <div className="bg-purple-50 p-4 rounded-xl border border-purple-200 mb-6">
                    <label className="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" title="Kit d'Urgence (Interventions de Nuit)" aria-label="Kit d'Urgence (Interventions de Nuit)" checked={f.hasEmergencyKit || false} onChange={e=>setF({...f, hasEmergencyKit: e.target.checked})} className="mt-1 w-5 h-5 accent-purple-600" />
                        <div>
                            <p className="text-sm font-bold text-purple-900">Kit d'Urgence (Interventions de Nuit)</p>
                            <p className="text-xs text-purple-700 mt-1">Cochez si vous possédez un stock de survie pour intervenir entre 18h et 7h.</p>
                        </div>
                    </label>
                </div>
            )}

            <Button className="w-full py-4 mb-4" onClick={handleSave}>Enregistrer les modifications</Button>
            <Button variant="danger" className="w-full py-4 text-red-700 bg-red-50 hover:bg-red-100" onClick={onLogout} iconName="LogOut">Déconnexion</Button>
        </div>
    );
};

const InteractiveMockMap = ({ artisans, suppliers, filterCategory, filterZone, onSelectArtisan, onSelectSupplier, clientLat = 5.345, clientLng = -4.024 }) => {
    const filteredArtisans = artisans.filter(a => a.lat && a.lng && a.isVerified !== false && (filterCategory === 'all' || a.category === filterCategory));
    const filteredSuppliers = suppliers.filter(s => s.lat && s.lng && (filterZone === 'all' || (s.adresse && s.adresse.includes(filterZone))));

    const project = (lat, lng) => {
        const y = ((lat - 5.3) / 0.1) * 100;
        const x = ((lng - (-4.1)) / 0.2) * 100;
        return { x: Math.max(10, Math.min(x, 90)), y: Math.max(10, Math.min(100 - y, 90)) }; 
    };

    return (
        <div className="relative w-full h-[400px] bg-slate-100 rounded-2xl overflow-hidden border-2 border-gray-200 shadow-inner mt-4">
            <svg className="absolute inset-0 w-full h-full opacity-30" viewBox="0 0 100 100" preserveAspectRatio="none">
                <path d="M0,50 Q25,60 50,40 T100,30" stroke="#cbd5e1" strokeWidth="2" fill="none" />
                <path d="M20,0 Q30,50 80,100" stroke="#cbd5e1" strokeWidth="2" fill="none" />
                <path d="M70,0 Q60,30 90,80" stroke="#cbd5e1" strokeWidth="1" fill="none" />
            </svg>
            
            <div className="absolute w-12 h-12 -ml-6 -mt-6 bg-blue-500/20 rounded-full animate-ping pointer-events-none left-1/2 top-1/2"></div>
            <div className="absolute w-4 h-4 -ml-2 -mt-2 bg-blue-600 border-2 border-white rounded-full shadow-lg z-10 left-1/2 top-1/2"></div>
            <span className="absolute text-[10px] font-bold bg-white px-2 py-0.5 rounded shadow-sm z-10 left-1/2 top-1/2 -translate-x-1/2 translate-y-3">Vous</span>

            {filteredArtisans.map(a => {
                const pos = project(a.lat, a.lng);
                return (
                    <MapMarker key={`map-a-${a.id}`} x={pos.x} y={pos.y} className="absolute w-8 h-8 -ml-4 -mt-8 cursor-pointer group z-20 transition-transform hover:scale-110 hover:z-30" onClick={() => onSelectArtisan(a)}>
                        {a.photo ? (
                            <img src={a.photo} className="w-8 h-8 rounded-full object-cover shadow-lg border-2 border-blue-600" alt={`Photo de ${a.name}`} />
                        ) : (
                            <div className="w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center shadow-lg shadow-blue-600/40 relative">
                                <Icon name="Wrench" size={14}/>
                                <div className="absolute -bottom-1 left-1/2 w-2 h-2 bg-blue-600 rotate-45 transform -translate-x-1/2"></div>
                            </div>
                        )}
                        <div className="opacity-0 group-hover:opacity-100 absolute top-10 left-1/2 transform -translate-x-1/2 bg-gray-900 text-white text-[10px] font-bold px-2 py-1 rounded whitespace-nowrap transition-opacity">{a.name}</div>
                    </MapMarker>
                )
            })}

            {filteredSuppliers.map(s => {
                const pos = project(s.lat, s.lng);
                return (
                    <MapMarker key={`map-s-${s.id}`} x={pos.x} y={pos.y} className="absolute w-8 h-8 -ml-4 -mt-8 cursor-pointer group z-20 transition-transform hover:scale-110 hover:z-30" onClick={() => onSelectSupplier(s)}>
                        {s.storefrontImage ? (
                            <img src={s.storefrontImage} className="w-8 h-8 rounded-full object-cover shadow-lg border-2 border-green-600" alt={s.raisonSociale || "Fournisseur"} />
                        ) : (
                            <div className="w-8 h-8 bg-green-600 text-white rounded-full flex items-center justify-center shadow-lg shadow-green-600/40 relative">
                                <Icon name="Store" size={14}/>
                                <div className="absolute -bottom-1 left-1/2 w-2 h-2 bg-green-600 rotate-45 transform -translate-x-1/2"></div>
                            </div>
                        )}
                        <div className="opacity-0 group-hover:opacity-100 absolute top-10 left-1/2 transform -translate-x-1/2 bg-gray-900 text-white text-[10px] font-bold px-2 py-1 rounded whitespace-nowrap transition-opacity">{s.raisonSociale}</div>
                    </MapMarker>
                )
            })}

            <div className="absolute bottom-2 left-2 bg-white/90 backdrop-blur p-2 rounded-lg shadow text-[9px] font-bold text-gray-600 border border-gray-100">
                <div className="flex items-center gap-1 mb-1"><div className="w-2 h-2 bg-blue-600 rounded-full"></div> Artisans</div>
                <div className="flex items-center gap-1"><div className="w-2 h-2 bg-green-600 rounded-full"></div> Fournisseurs</div>
            </div>
        </div>
    );
};

// --- MODALES EXISTANTES ---
const PaymentModal = ({ amount, paymentAmount, isMacro, onClose, onSuccess, title="Paiement Sécurisé" }) => {
    const [provider, setProvider] = useState(null);
    const [loading, setLoading] = useState(false);
    const finalAmount = paymentAmount || amount;
    
    // VERROU DE SÉCURITÉ FINANCIÈRE: Blocage du Mobile Money pour les gros montants
    const isHighValue = isMacro || finalAmount >= 2000000;

    return (
        <div className="fixed inset-0 bg-black/50 z-[100] flex items-end sm:items-center justify-center p-4 backdrop-blur-sm animate-in fade-in"><div className="bg-white w-full max-w-sm rounded-3xl p-6 shadow-2xl relative max-h-[90vh] overflow-y-auto"><button onClick={onClose} className="absolute top-4 right-4 text-gray-400" aria-label="Fermer"><Icon name="X" size={24}/></button><h3 className="text-xl font-bold mb-2 flex items-center gap-2"><Icon name="Shield" className="text-green-600"/> {title}</h3><p className="text-sm text-gray-500 mb-6">Fonds placés sous séquestre ProsArtisan.</p><div className="text-center mb-6"><span className="text-3xl font-bold text-blue-900">{(finalAmount || 0).toLocaleString()} FCFA</span></div>
        
        {!provider ? (
            <div className="space-y-6">
                {!isHighValue && (
                    <div className="animate-in fade-in">
                        <p className="text-[10px] font-bold text-gray-400 uppercase mb-3 tracking-widest border-b pb-1">Mobile Money (Instantané)</p>
                        <div className="grid grid-cols-2 gap-4"><button onClick={() => setProvider('wave')} className="p-4 rounded-xl bg-sky-50 flex flex-col items-center border border-transparent hover:border-sky-300"><div className="w-10 h-10 bg-sky-500 rounded-full flex items-center justify-center text-white font-bold">W</div><span className="font-bold text-sky-700 mt-2">Wave</span></button><button onClick={() => setProvider('om')} className="p-4 rounded-xl bg-orange-50 flex flex-col items-center border border-transparent hover:border-orange-300"><div className="w-10 h-10 bg-orange-500 rounded-full flex items-center justify-center text-white font-bold">OM</div><span className="font-bold text-orange-700 mt-2">Orange</span></button></div>
                    </div>
                )}

                {(isHighValue || !isHighValue) && (
                    <div className="animate-in fade-in">
                        <p className="text-[10px] font-bold text-indigo-400 uppercase mb-3 tracking-widest border-b pb-1 flex items-center gap-1"><Icon name="Briefcase" size={12}/> {isHighValue ? "Grands Comptes (Obligatoire > 2M)" : "Autres méthodes"}</p>
                        {isHighValue && <p className="text-[10px] text-red-500 mb-3 font-bold bg-red-50 p-2 rounded">Les paiements Mobile Money sont limités. Veuillez utiliser une méthode Grands Comptes pour ce projet.</p>}
                        <div className="grid grid-cols-2 gap-3">
                            <button onClick={() => setProvider('visa')} className="p-3 rounded-xl bg-blue-50 flex flex-col items-center border border-transparent hover:border-blue-300 transition-colors"><Icon name="Bank" size={28} className="text-blue-600 mb-1"/><span className="text-xs font-bold text-blue-800 text-center">Carte Visa / MC</span></button>
                            <button onClick={() => setProvider('virement')} className="p-3 rounded-xl bg-slate-100 flex flex-col items-center border border-transparent hover:border-slate-400 transition-colors"><Icon name="FileText" size={28} className="text-slate-700 mb-1"/><span className="text-xs font-bold text-slate-800 text-center">Virement Bancaire</span></button>
                        </div>
                    </div>
                )}
            </div>
        ) : (
            <div className="space-y-4 animate-in slide-in-from-right-4">
                <button onClick={()=>setProvider(null)} className="text-xs font-bold text-gray-500 flex items-center mb-2"><Icon name="ChevronLeft" size={14}/> Changer de méthode</button>
                
                {provider === 'virement' ? (
                    <div className="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <p className="text-sm font-bold text-slate-800 mb-2">Instructions de Virement</p>
                        <p className="text-[10px] text-slate-600 mb-3">Veuillez transférer les fonds sur notre compte séquestre. Le chantier débutera dès validation par nos services financiers (24h à 48h).</p>
                        <div className="bg-white p-3 rounded-lg border border-slate-200 font-mono text-xs space-y-1 mb-4">
                            <p><span className="text-gray-400">Banque :</span> ECOBANK CI</p>
                            <p><span className="text-gray-400">Titulaire :</span> PROSARTISAN ESCROW</p>
                            <p><span className="text-gray-400">IBAN :</span> CI59 CI05 9012 3456 7890 12</p>
                            <div className="mt-2 pt-2 border-t border-red-100">
                                <p className="text-red-600 font-bold">Motif obligatoire :</p>
                                <p className="text-lg font-black text-slate-900 tracking-widest">REF-{Date.now().toString().slice(-6)}</p>
                            </div>
                        </div>
                        <Button onClick={() => { setLoading(true); setTimeout(()=>onSuccess('virement'), 2000); }} disabled={loading} className="w-full bg-slate-800">{loading ? 'Traitement...' : `J'ai effectué le virement`}</Button>
                    </div>
                ) : provider === 'visa' ? (
                    <div className="space-y-3">
                        <div className="bg-blue-50 text-blue-800 text-[10px] p-2 rounded flex gap-2"><Icon name="Lock" size={14}/> Passerelle sécurisée par Stripe 3D-Secure.</div>
                        <InputGroup label="Numéro de carte"><BasicInput type="text" placeholder="XXXX XXXX XXXX XXXX"/></InputGroup>
                        <div className="grid grid-cols-2 gap-3">
                            <InputGroup label="Expiration"><BasicInput type="text" placeholder="MM/YY"/></InputGroup>
                            <InputGroup label="CVC"><BasicInput type="text" placeholder="123"/></InputGroup>
                        </div>
                        <Button onClick={() => { setLoading(true); setTimeout(()=>onSuccess('visa'), 2000); }} disabled={loading} className="w-full bg-blue-600">{loading ? 'Sécurisation...' : `Payer ${(finalAmount || 0).toLocaleString()} FCFA`}</Button>
                    </div>
                ) : (
                    <div className="space-y-3">
                        <div className={`p-4 rounded-xl flex items-center justify-center gap-3 font-bold text-white mb-4 ${provider === 'wave' ? 'bg-sky-500' : 'bg-orange-500'}`}>
                            Payez via {provider === 'wave' ? 'Wave' : 'Orange Money'}
                        </div>
                        <InputGroup label="Numéro Mobile (Lié au compte)"><BasicInput type="tel" placeholder="07 XX XX XX XX"/></InputGroup>
                        <Button onClick={() => { setLoading(true); setTimeout(()=>onSuccess(provider), 1500); }} disabled={loading} className={`w-full ${provider === 'wave' ? '!bg-sky-600' : '!bg-orange-600'}`}>{loading ? 'Traitement réseau...' : `Valider ${(finalAmount || 0).toLocaleString()} FCFA`}</Button>
                    </div>
                )}
            </div>
        )}
        </div></div>
    );
};

const CodeVerificationModal = ({ title, description, expectedCode, onSuccess, onClose }) => {
    const [inputCode, setInputCode] = useState('');
    const [error, setError] = useState('');
    const handleVerify = () => {
        if(inputCode.trim().toUpperCase() === expectedCode) { setError(''); onSuccess(); } 
        else { setError("Le code saisi est incorrect. Veuillez vérifier."); }
    };
    return (
        <div className="fixed inset-0 bg-black/60 z-[100] flex items-center justify-center p-4 backdrop-blur-sm animate-in fade-in">
            <div className="bg-white w-full max-w-sm rounded-3xl p-6 text-center relative shadow-2xl">
                <button onClick={onClose} className="absolute top-4 right-4 text-gray-400" aria-label="Fermer"><Icon name="X"/></button>
                <div className="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4 text-blue-600"><Icon name="Scan" size={32}/></div>
                <h3 className="text-xl font-bold text-gray-800 mb-2">{title}</h3>
                <p className="text-sm text-gray-500 mb-4">{description}</p>
                {error && <p className="text-xs font-bold text-red-600 bg-red-50 p-2 rounded-lg mb-4">{error}</p>}
                <input value={inputCode} onChange={e => {setInputCode(e.target.value.toUpperCase()); setError('');}} placeholder="Ex: RECEPTION-XXXX" className="w-full p-4 border-2 border-gray-200 focus:border-blue-500 outline-none rounded-xl text-center text-lg font-mono mb-4 tracking-widest uppercase" aria-label="Code de confirmation OTP"/>
                <Button className="w-full py-3" variant="primary" onClick={handleVerify}>Confirmer le code</Button>
            </div>
        </div>
    );
};

const DisputeModal = ({ targetInfo, onClose, onSubmit }) => {
    const [reason, setReason] = useState('Travail incomplet / mal fait');
    const [description, setDescription] = useState('');
    const [proofImg, setProofImg] = useState(null);

    return (
        <div className="fixed inset-0 bg-black/60 z-[100] flex items-center justify-center p-4 backdrop-blur-sm animate-in fade-in">
            <div className="bg-white w-full max-w-md rounded-3xl p-6 shadow-2xl relative max-h-[90vh] overflow-y-auto">
                <button onClick={onClose} className="absolute top-4 right-4 text-gray-400" aria-label="Fermer"><Icon name="X"/></button>
                <div className="flex items-center gap-3 mb-2">
                    <div className="w-10 h-10 bg-red-100 text-red-600 rounded-full flex items-center justify-center"><Icon name="AlertTriangle"/></div>
                    <h3 className="text-xl font-bold text-gray-800">Signaler un Litige</h3>
                </div>
                <p className="text-xs text-gray-600 mb-4 bg-gray-50 p-2 rounded-lg">Transaction : <b>{targetInfo.title}</b></p>
                
                <div className="space-y-4">
                    <InputGroup label="Motif de contestation">
                        <select value={reason} onChange={e=>setReason(e.target.value)} className="w-full bg-gray-50 border p-3 rounded-xl outline-none focus:border-red-500" aria-label="Motif de contestation">
                            <option>Travail incomplet / mal fait</option>
                            <option>Retard important / Abandon</option>
                            <option>Matériel défectueux ou non conforme</option>
                            <option>Dommage causé pendant la prestation</option>
                        </select>
                    </InputGroup>
                    
                    <InputGroup label="Description des faits">
                        <textarea className="w-full bg-gray-50 border rounded-xl p-3 h-24 focus:border-red-500 outline-none" value={description} onChange={e=>setDescription(e.target.value)} placeholder="Décrivez précisément le problème..." aria-label="Description des faits du litige"/>
                    </InputGroup>

                    <div className="bg-red-50/50 p-4 rounded-xl border border-red-100">
                        <label className="block text-xs font-bold text-red-800 uppercase mb-2 flex items-center gap-1"><Icon name="Camera" size={14}/> Preuve Requise (Obligatoire)</label>
                        <p className="text-[10px] text-red-600 mb-3">La plateforme arbitrera ce litige. Une photo claire du défaut est exigée pour geler les fonds du professionnel.</p>
                        <div className="bg-white h-32 rounded-xl flex items-center justify-center border-2 border-dashed border-red-200 hover:bg-red-50 cursor-pointer relative transition-colors overflow-hidden">
                            <input type="file" onChange={e => {const r=new FileReader(); r.onload=()=>setProofImg(r.result); r.readAsDataURL(e.target.files[0]);}} className="absolute inset-0 opacity-0 z-10 cursor-pointer" aria-label="Téléverser une preuve de litige" />
                            {proofImg ? <img src={proofImg} className="w-full h-full object-cover" alt="Preuve du litige" /> : <div className="text-center text-red-400"><Icon name="Upload" size={24} className="mx-auto mb-1"/><p className="text-xs font-bold">Ajouter une photo</p></div>}
                        </div>
                    </div>

                    <Button 
                        onClick={() => onSubmit({reason, description, proofImg})} 
                        variant="danger" 
                        className={`w-full py-4 ${!proofImg ? 'opacity-50 cursor-not-allowed grayscale' : ''}`}
                        disabled={!proofImg}
                    >
                        {!proofImg ? 'Ajoutez une preuve pour continuer' : 'Geler les fonds et Soumettre'}
                    </Button>
                </div>
            </div>
        </div>
    );
};

const ProofUploadModal = ({ onClose, onSubmit, isMaterialStep }) => {
    const [photo, setPhoto] = useState(null);
    return (
        <div className="fixed inset-0 bg-black/60 z-[100] flex items-center justify-center p-4 backdrop-blur-sm animate-in fade-in"><div className="bg-white w-full max-w-sm rounded-3xl p-6"><div className="flex justify-between items-center mb-4"><h3 className="font-bold text-lg flex items-center gap-2"><Icon name="Camera" className="text-blue-600"/> {isMaterialStep ? "Preuve Matériel" : "Preuve Travaux"}</h3><button onClick={onClose} className="text-gray-400" aria-label="Fermer"><Icon name="X"/></button></div><p className="text-xs text-gray-500 mb-4">{isMaterialStep ? "Prenez une photo du matériel récupéré chez le fournisseur." : "Prenez une photo claire de votre réalisation pour que le client valide la fin des travaux."}</p><div className="mb-4 bg-gray-50 h-48 rounded-xl flex items-center justify-center border-2 border-dashed border-gray-300 hover:bg-gray-100 cursor-pointer relative transition-colors"><input type="file" onChange={e => {const r=new FileReader(); r.onload=()=>setPhoto(r.result); r.readAsDataURL(e.target.files[0]);}} className="absolute inset-0 opacity-0 z-10 cursor-pointer" aria-label="Téléverser une photo de preuve" />{photo ? <img src={photo} className="w-full h-full object-cover rounded-xl" alt="Aperçu de la photo de preuve" /> : <div className="text-center text-gray-400"><Icon name="Camera" size={32} className="mx-auto mb-2"/><p className="text-xs font-bold">Ajouter Photo</p></div>}</div><Button disabled={!photo} onClick={() => onSubmit(photo)} className="w-full" variant="success">Envoyer la Preuve</Button></div></div>
    );
};

const RatingModal = ({ targetInfo, onClose, onSubmit }) => {
    const [rating, setRating] = useState(0);
    const [comment, setComment] = useState('');
    return (
        <div className="fixed inset-0 bg-black/60 z-[100] flex items-center justify-center p-4 backdrop-blur-sm animate-in fade-in">
            <div className="bg-white w-full max-w-md rounded-3xl p-6 shadow-2xl relative">
                <button onClick={onClose} className="absolute top-4 right-4 text-gray-400" aria-label="Fermer"><Icon name="X"/></button>
                <h3 className="text-lg font-bold text-gray-800 text-center mb-2">Évaluer {targetInfo.name}</h3>
                <p className="text-xs text-gray-500 text-center mb-6">
                    {targetInfo.role === 'artisan' ? "Comment s'est passée l'intervention ?" : 
                     targetInfo.role === 'driver' ? "Comment s'est passée la livraison ?" : "Avez-vous été satisfait de votre commande ?"}
                </p>

                <div className="flex justify-center gap-2 mb-6">
                    {[1, 2, 3, 4, 5].map(star => (
                        <button key={star} type="button" onClick={() => setRating(star)} className={`${star <= rating ? 'text-yellow-400' : 'text-gray-200'} transition-colors`} aria-label={`${star} étoile${star > 1 ? 's' : ''} sur 5`}>
                            <Icon name="Star" size={40} fill={star <= rating ? 'currentColor' : 'none'} />
                        </button>
                    ))}
                </div>

                <InputGroup label="Laissez un commentaire (optionnel)">
                    <textarea className="w-full bg-gray-50 border rounded-xl p-3 h-24 outline-none focus:border-blue-500" value={comment} onChange={e=>setComment(e.target.value)} placeholder="Votre avis nous aide à améliorer le service..." aria-label="Commentaire de l'évaluation"/>
                </InputGroup>

                <Button onClick={() => onSubmit({rating, comment})} disabled={rating === 0} className="w-full mt-2">Soumettre l'évaluation</Button>
            </div>
        </div>
    )
};

// --- NOUVELLES MODALES POUR LE SYSTÈME D'APPELS D'OFFRES (OPTION A) ---
const CreateTenderModal = ({ availableBudget, onClose, onSubmit }) => {
    const [title, setTitle] = useState('');
    const [desc, setDesc] = useState('');
    const [documentUrl, setDocumentUrl] = useState(null);

    const handleImageChange = (e) => {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onloadend = () => setDocumentUrl(reader.result);
            reader.readAsDataURL(file);
        }
    };

    const handleSubmit = () => {
        if (!title || !desc || !documentUrl) return alert("Le titre, la description et le document technique sont obligatoires.");
        onSubmit({
            id: generateUUID(),
            title,
            description: desc,
            documentUrl,
            status: 'open',
            bids: []
        });
    };

    return (
        <div className="fixed inset-0 bg-black/60 z-[100] flex items-center justify-center p-4 backdrop-blur-sm animate-in fade-in">
            <div className="bg-white w-full max-w-md rounded-3xl p-6 shadow-2xl relative max-h-[90vh] overflow-y-auto">
                <button onClick={onClose} className="absolute top-4 right-4 text-gray-400" aria-label="Fermer"><Icon name="X"/></button>
                <div className="flex items-center gap-3 mb-4">
                    <div className="w-10 h-10 bg-indigo-100 text-indigo-600 rounded-full flex items-center justify-center"><Icon name="Briefcase"/></div>
                    <h3 className="text-xl font-bold text-gray-800">Publier un Appel d'Offres</h3>
                </div>

                <div className="bg-indigo-50 p-3 rounded-lg border border-indigo-100 mb-6">
                    <p className="text-[10px] text-indigo-600 font-bold uppercase mb-1">Reste à allouer (Séquestre)</p>
                    <p className="text-xl font-black text-indigo-900">{availableBudget.toLocaleString()} F</p>
                </div>

                <div className="space-y-4">
                    <InputGroup label="Titre du lot">
                        <BasicInput value={title} onChange={e=>setTitle(e.target.value)} placeholder="Ex: Lot Électricité - Villa" />
                    </InputGroup>

                    <InputGroup label="Description des travaux">
                        <textarea value={desc} onChange={e=>setDesc(e.target.value)} placeholder="Décrivez le besoin technique..." className="w-full h-24 bg-gray-50 border p-3 rounded-xl outline-none focus:border-indigo-500"  aria-label="Description du besoin technique" />
                    </InputGroup>

                    <ImageUploadField 
                        label="Document Technique (Requis)" 
                        image={documentUrl} 
                        onChange={handleImageChange} 
                        iconName="FileText"
                        hint="Joignez le plan ou cahier des charges spécifique à ce lot."
                    />
                    
                    <Button className="w-full py-4 bg-indigo-600 hover:bg-indigo-700 text-white" onClick={handleSubmit}>Diffuser l'Appel d'Offres</Button>
                </div>
            </div>
        </div>
    );
};

const SubmitBidModal = ({ tender, artisan, onClose, onSubmit }) => {
    const [amount, setAmount] = useState('');

    const handleSubmit = () => {
        if (!amount || parseInt(amount) <= 0) return alert("Veuillez entrer un montant valide.");
        onSubmit({
            artisanId: artisan.id,
            artisanName: `${artisan.prenoms} ${artisan.nom}`,
            amount: parseInt(amount)
        });
    };

    return (
        <div className="fixed inset-0 bg-black/60 z-[100] flex items-center justify-center p-4 backdrop-blur-sm animate-in fade-in">
            <div className="bg-white w-full max-w-md rounded-3xl p-6 shadow-2xl relative">
                <button onClick={onClose} className="absolute top-4 right-4 text-gray-400" aria-label="Fermer"><Icon name="X"/></button>
                <div className="flex items-center gap-3 mb-4">
                    <div className="w-10 h-10 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center"><Icon name="Edit2"/></div>
                    <h3 className="text-xl font-bold text-gray-800">Proposer un Devis</h3>
                </div>

                <div className="bg-gray-50 p-3 rounded-xl mb-4 border border-gray-200">
                    <p className="text-[10px] font-bold text-gray-500 uppercase mb-1">Lot à chiffrer :</p>
                    <p className="text-sm text-gray-800 font-bold">{tender.title}</p>
                </div>

                <InputGroup label="Votre Prix Net (FCFA)">
                    <BasicInput type="number" value={amount} onChange={e=>setAmount(e.target.value)} placeholder="Ex: 250000" />
                </InputGroup>
                
                <Button className="w-full py-4" onClick={handleSubmit}>Soumettre mon devis</Button>
            </div>
        </div>
    );
};

const AwardBidModal = ({ tender, bid, artisanName, onClose, onSubmit }) => {
  const [milestones, setMilestones] = useState([{ name: 'Démarrage', percentage: 40 }, { name: 'Finitions', percentage: 60 }]);
  const totalPercentage = milestones.reduce((acc, m) => acc + parseInt(m.percentage || 0), 0);
  const isInvalid = totalPercentage !== 100;

  return (
    <div className="fixed inset-0 bg-black/60 z-[100] flex items-center justify-center p-4 backdrop-blur-sm animate-in fade-in">
      <div className="bg-white w-full max-w-md rounded-3xl p-6 max-h-[90vh] overflow-y-auto shadow-2xl relative">
        <button onClick={onClose} className="absolute top-4 right-4 text-gray-400" aria-label="Fermer"><Icon name="X"/></button>
        <h2 className="text-xl font-black mb-1">Engager {artisanName}</h2>
        <p className="text-xs text-slate-500 mb-4">Montant retenu : <span className="font-bold text-emerald-600">{bid.amount.toLocaleString()} F</span></p>
        
        <h3 className="text-sm font-bold mb-3 flex justify-between items-center"><span>Échéancier</span><button onClick={()=>setMilestones([...milestones, {name:'', percentage:0}])} className="text-[10px] text-indigo-600 bg-indigo-50 px-2 py-1 rounded">+ Jalon</button></h3>
        <div className="space-y-2 mb-4">
          {milestones.map((m, idx) => (
            <div key={idx} className="flex gap-2">
              <input type="text" placeholder="Nom" value={m.name} onChange={(e) => { const newM=[...milestones]; newM[idx].name=e.target.value; setMilestones(newM); }} className="flex-1 border p-2 text-xs rounded-lg outline-none focus:border-indigo-500" aria-label="Nom du jalon" />
              <input type="number" value={m.percentage} onChange={(e) => { const newM=[...milestones]; newM[idx].percentage=e.target.value; setMilestones(newM); }} className="w-16 border p-2 text-xs rounded-lg text-center outline-none focus:border-indigo-500" placeholder="0" aria-label="Pourcentage du jalon" />
              <span className="w-8 flex items-center justify-center text-xs font-bold">%</span>
            </div>
          ))}
        </div>
        {isInvalid && <p className="text-[10px] text-red-500 mb-4 font-bold">Total doit être 100%</p>}
        <div className="flex gap-3"><Button variant="outline" onClick={onClose}>Annuler</Button><Button variant="success" disabled={isInvalid} onClick={()=>onSubmit(milestones)}>Valider le Contrat</Button></div>
      </div>
    </div>
  );
};

// --- AUTH & INSCRIPTION ---
const LoginScreen = ({ onLogin, onGoToRegister, error }) => {
  const [id, setId] = useState('0707070707'); const [pwd, setPwd] = useState('123');
  return (
    <div className="min-h-screen flex flex-col items-center justify-center p-6 bg-slate-50">
      <div className="w-full max-w-sm space-y-6 bg-white p-8 rounded-3xl shadow-xl">
        <div className="text-center"><div className="w-16 h-16 bg-blue-100 text-blue-600 rounded-2xl flex items-center justify-center mx-auto mb-4"><Icon name="Briefcase" size={32}/></div><h1 className="text-3xl font-black text-gray-900 tracking-tight">ProsArtisan</h1></div>
        
        {error && <p className="text-red-500 text-xs font-bold text-center bg-red-50 p-2 rounded mb-4">{error}</p>}
        
        <form onSubmit={e => { e.preventDefault(); onLogin(id, pwd); }} className="space-y-4">
          <InputGroup label="Identifiant / Téléphone"><BasicInput value={id} onChange={e=>setId(e.target.value)} required /></InputGroup>
          <InputGroup label="Mot de passe"><BasicInput type="password" value={pwd} onChange={e=>setPwd(e.target.value)} required /></InputGroup>
          <Button type="submit" className="w-full py-4 text-lg">Se connecter</Button>
        </form>
        <div className="mt-4 text-center text-[10px] text-gray-500 bg-gray-100 p-3 rounded-xl leading-relaxed">
            <span className="font-bold border-b pb-1 mb-1 block">Comptes de test (Pass: 123) :</span>
            Admin: <b>admin</b> | Client: <b>0707070707</b><br/>Artisan/MOE: <b>0505050501</b> | Fournisseur: <b>0707070701</b> | Livreur: <b>0707070705</b>
        </div>
        <div className="text-center pt-4 border-t"><Button variant="ghost" className="w-full text-sm text-blue-600" onClick={onGoToRegister}>Créer un compte</Button></div>
      </div>
    </div>
  );
};

const RoleSelectionScreen = ({ onSelectRole, onBack }) => (
  <div className="min-h-screen flex flex-col items-center justify-center p-6 bg-slate-50 relative">
    <button onClick={onBack} className="absolute top-6 left-6 text-gray-500 flex items-center gap-1"><Icon name="ChevronLeft" size={18}/> Retour</button>
    <div className="w-full max-w-sm space-y-4 mt-10">
      <div className="text-center mb-8"><h1 className="text-2xl font-bold text-blue-600">Inscription</h1><p className="text-gray-500">Choisissez votre profil</p></div>
      <Button className="w-full py-4 shadow-md" onClick={() => onSelectRole('client')} iconName="User">Je suis Client</Button>
      <Button className="w-full py-4" onClick={() => onSelectRole('artisan')} variant="secondary" iconName="Wrench">Je suis Artisan</Button>
      <Button className="w-full py-4" onClick={() => onSelectRole('supplier')} variant="secondary" iconName="Store">Je suis Fournisseur</Button>
      <Button className="w-full py-4" onClick={() => onSelectRole('driver')} variant="secondary" iconName="Truck">Je suis Livreur</Button>
    </div>
  </div>
);

// LAZY KYC
const RegistrationFlow = ({ initialRole, onRegister, onBack, categories, supplierSectors }) => {
  const role = initialRole;
  const [f, setF] = useState({ nom: '', prenoms: '', tel: '', pwd: '', adresse: '', category: 'plomberie', secteur: 'Quincaillerie', rccm: '', rs: '' });

  const submit = (e) => { 
      e.preventDefault(); 
      const finalData = { ...f, role, id: Date.now() };
      
      if (role === 'supplier') { 
          finalData.catalog = []; 
          finalData.walletBalance = 0; 
          finalData.isVerified = false;
      } else if (role === 'artisan' || role === 'driver') { 
          finalData.rating = 5.0; 
          finalData.reviewCount = 0;
          finalData.location = f.adresse; 
          finalData.price = "Sur devis"; 
          finalData.hasEmergencyKit = false; 
          finalData.isVerified = false; 
      }
      onRegister(finalData); 
  };

  return (
    <div className="min-h-screen bg-slate-50 p-6 pt-12 pb-20 overflow-y-auto">
      <button onClick={onBack} className="mb-6 text-gray-500 flex items-center gap-1"><Icon name="ChevronLeft"/> Retour</button>
      <h2 className="text-2xl font-bold mb-2">Détails du profil</h2>
      <p className="text-xs text-gray-500 mb-6">Inscription rapide en 10 secondes. Vous compléterez vos documents légaux plus tard.</p>
      
      <form onSubmit={submit} className="space-y-4 bg-white p-6 rounded-2xl shadow-sm">
        {role === 'supplier' && (<><InputGroup label="Raison Sociale"><BasicInput value={f.rs} onChange={e=>setF({...f,rs:e.target.value})} required/></InputGroup><InputGroup label="Secteur principal"><select className="w-full bg-gray-50 border p-3 rounded-xl outline-none" onChange={e=>setF({...f,secteur:e.target.value})} required aria-label="Secteur principal"><option value="">Choisir</option>{supplierSectors.map(s=><option key={s}>{s}</option>)}</select></InputGroup></>)}
        {(role === 'artisan' || role === 'driver') && (<><div className="grid grid-cols-2 gap-3"><InputGroup label="Nom"><BasicInput value={f.nom} onChange={e=>setF({...f,nom:e.target.value})} required/></InputGroup><InputGroup label="Prénoms"><BasicInput value={f.prenoms} onChange={e=>setF({...f,prenoms:e.target.value})} required/></InputGroup></div><InputGroup label="Spécialité"><select className="w-full bg-gray-50 border p-3 rounded-xl outline-none" onChange={e=>setF({...f,category:e.target.value})} required aria-label="Spécialité"><option value="">Choisir</option>{categories.map(c=><option key={c.id} value={c.id}>{c.name}</option>)}</select></InputGroup></>)}
        {role === 'client' && (<div className="grid grid-cols-2 gap-3"><InputGroup label="Nom"><BasicInput value={f.nom} onChange={e=>setF({...f,nom:e.target.value})} required/></InputGroup><InputGroup label="Prénoms"><BasicInput value={f.prenoms} onChange={e=>setF({...f,prenoms:e.target.value})} required/></InputGroup></div>)}
        <InputGroup label="Téléphone"><BasicInput type="tel" value={f.tel} onChange={e=>setF({...f,tel:e.target.value})} required/></InputGroup>
        <InputGroup label="Adresse de base (Quartier)"><BasicInput value={f.adresse} onChange={e=>setF({...f,adresse:e.target.value})} required/></InputGroup>
        <InputGroup label="Mot de passe"><BasicInput type="password" value={f.pwd} onChange={e=>setF({...f,pwd:e.target.value})} required/></InputGroup>
        <Button type="submit" className="w-full mt-6 py-4 text-lg">Créer mon compte</Button>
      </form>
    </div>
  );
};

// --- DASHBOARDS METIERS ---

// 1. ESPACE ADMINISTRATEUR
const AdminDashboard = ({ user, onLogout, categories, setCategories, supplierSectors, setSupplierSectors, platformFees, setPlatformFees, jobs, orders }) => {
    const [tab, setTab] = useState('dashboard');
    const [newCat, setNewCat] = useState({ name: '', fee: '' });
    const [newSec, setNewSec] = useState({ name: '', fee: '' });
    const [localNotif, setLocalNotif] = useState(null);

    const showNotification = (title, text) => {
        setLocalNotif({ title, text });
        setTimeout(() => setLocalNotif(null), 4000);
    };

    const validJobs = jobs.filter(j => ['funded', 'materials_picked_up', 'work_done', 'completed'].includes(j.status));
    const revLabor = validJobs.reduce((acc, j) => acc + (j.financials?.platformFeesBreakdown?.labor || 0), 0);
    const validOrders = orders.filter(o => ['paid', 'prepared', 'searching_driver', 'driver_assigned', 'driver_picked_up', 'shipping', 'delivered'].includes(o.status));
    const revMaterial = validOrders.reduce((acc, o) => acc + (o.platformFeesBreakdown?.material || 0), 0);
    const revDelivery = validOrders.reduce((acc, o) => acc + (o.platformFeesBreakdown?.delivery || 0), 0);
    const totalRev = revLabor + revMaterial + revDelivery;

    const handleAddCategory = (e) => {
        e.preventDefault();
        if(!newCat.name || !newCat.fee) return;
        const id = newCat.name.toLowerCase().replace(/\s+/g, '_');
        setCategories([...categories, { id, name: newCat.name, iconName: 'Wrench', color: 'bg-indigo-100 text-indigo-600' }]);
        setPlatformFees(prev => ({ ...prev, artisanCategories: { ...prev.artisanCategories, [id]: parseFloat(newCat.fee) } }));
        setNewCat({ name: '', fee: '' });
        showNotification("Succès", "Catégorie ajoutée.");
    };

    const handleAddSector = (e) => {
        e.preventDefault();
        if(!newSec.name || !newSec.fee) return;
        setSupplierSectors([...supplierSectors, newSec.name]);
        setPlatformFees(prev => ({ ...prev, supplierSectors: { ...prev.supplierSectors, [newSec.name]: parseFloat(newSec.fee) } }));
        setNewSec({ name: '', fee: '' });
        showNotification("Succès", "Secteur ajouté.");
    };

    return (
        <div className="min-h-screen bg-slate-900 text-white pb-20 flex flex-col relative">
            {localNotif && (
                <div className="absolute top-4 left-4 right-4 bg-green-600 text-white p-4 rounded-xl shadow-2xl z-[100] animate-in slide-in-from-top-5 flex items-start gap-3">
                    <Icon name="CheckCircle" size={24} className="shrink-0" />
                    <div><p className="font-bold text-sm">{localNotif.title}</p><p className="text-xs text-green-100">{localNotif.text}</p></div>
                    <button onClick={() => setLocalNotif(null)} className="ml-auto text-green-200 hover:text-white" aria-label="Fermer la notification"><Icon name="X" size={16}/></button>
                </div>
            )}
            <div className="pt-12 pb-6 px-6 flex justify-between items-center border-b border-slate-800">
                <div><p className="text-xs text-blue-400 uppercase font-bold tracking-widest mb-1">Super Admin</p><h1 className="text-2xl font-bold">ProsArtisan</h1></div>
                <Button variant="danger" onClick={onLogout} className="p-2"><Icon name="LogOut"/></Button>
            </div>
            <div className="px-4 mt-6 flex-1">
                {tab === 'dashboard' && (
                    <div className="animate-in fade-in space-y-6">
                        <div className="bg-gradient-to-br from-blue-600 to-indigo-800 rounded-2xl p-6 shadow-xl relative overflow-hidden">
                            <p className="text-blue-100 text-xs font-bold uppercase mb-1">Revenus Globaux Sécurisés</p>
                            <h3 className="text-4xl font-black mb-2">{(totalRev || 0).toLocaleString()} <span className="text-xl font-medium">F</span></h3>
                        </div>

                        <div className="space-y-3">
                            <h3 className="text-sm font-bold text-slate-400 uppercase tracking-widest border-b border-slate-800 pb-2">Ventilation par pôle</h3>
                            <div className="bg-slate-800 p-4 rounded-xl flex justify-between items-center">
                                <div className="flex items-center gap-3">
                                    <div className="w-10 h-10 bg-slate-700 rounded-lg flex items-center justify-center text-blue-400"><Icon name="Wrench"/></div>
                                    <span className="font-bold">Main d'œuvre</span>
                                </div>
                                <span className="font-bold text-blue-400">{(revLabor || 0).toLocaleString()} F</span>
                            </div>
                            <div className="bg-slate-800 p-4 rounded-xl flex justify-between items-center">
                                <div className="flex items-center gap-3">
                                    <div className="w-10 h-10 bg-slate-700 rounded-lg flex items-center justify-center text-green-400"><Icon name="Store"/></div>
                                    <span className="font-bold">Matériaux (B2C/B2B)</span>
                                </div>
                                <span className="font-bold text-green-400">{(revMaterial || 0).toLocaleString()} F</span>
                            </div>
                            <div className="bg-slate-800 p-4 rounded-xl flex justify-between items-center">
                                <div className="flex items-center gap-3">
                                    <div className="w-10 h-10 bg-slate-700 rounded-lg flex items-center justify-center text-teal-400"><Icon name="Truck"/></div>
                                    <span className="font-bold">Livraison</span>
                                </div>
                                <span className="font-bold text-teal-400">{(revDelivery || 0).toLocaleString()} F</span>
                            </div>
                        </div>
                    </div>
                )}
                {tab === 'commissions' && (
                    <div className="animate-in slide-in-from-bottom-5 space-y-6">
                        <div className="bg-purple-900/30 p-4 rounded-xl border border-purple-800/50 mb-6">
                            <p className="text-xs text-purple-300"><Icon name="Moon" size={14} className="inline mr-1"/>La <b>Majoration Urgence Nuit (18h-7h)</b> s'applique automatiquement sur la part de l'artisan (Stock perso + Main d'œuvre).</p>
                        </div>
                        
                        <div className="bg-slate-800 p-3 rounded-xl flex justify-between items-center border border-purple-500/50 mb-6">
                            <span className="text-sm font-bold text-purple-400">Multiplicateur Nuit (x)</span>
                            <div className="flex items-center gap-2">
                                <input type="number" step="0.1" value={platformFees?.nightSurgeMultiplier ?? 1.5} onChange={e=>setPlatformFees({...platformFees, nightSurgeMultiplier: parseFloat(e.target.value)})} className="w-16 bg-slate-900 border border-purple-500 p-2 rounded text-center text-sm outline-none text-purple-300" aria-label="Multiplicateur Nuit" />
                            </div>
                        </div>

                        <div className="space-y-3">
                            <h3 className="text-sm font-bold text-blue-400 uppercase tracking-widest border-b border-slate-800 pb-2">Taux Globaux par Défaut</h3>
                            <div className="bg-slate-800 p-3 rounded-xl flex justify-between items-center"><span className="text-sm">Main d'œuvre Artisan</span><div className="flex items-center gap-2"><input type="number" value={platformFees?.labor ?? 10} onChange={e=>setPlatformFees({...platformFees, labor: parseFloat(e.target.value)})} className="w-16 bg-slate-900 border border-slate-700 p-2 rounded text-center text-sm outline-none" aria-label="Commission Main d'œuvre Artisan" /><span>%</span></div></div>
                            <div className="bg-slate-800 p-3 rounded-xl flex justify-between items-center"><span className="text-sm">Vente Matériaux</span><div className="flex items-center gap-2"><input type="number" value={platformFees?.material ?? 3} onChange={e=>setPlatformFees({...platformFees, material: parseFloat(e.target.value)})} className="w-16 bg-slate-900 border border-slate-700 p-2 rounded text-center text-sm outline-none" aria-label="Commission Vente Matériaux" /><span>%</span></div></div>
                            <div className="bg-slate-800 p-3 rounded-xl flex justify-between items-center"><span className="text-sm">Frais Livraison</span><div className="flex items-center gap-2"><input type="number" value={platformFees?.delivery ?? 5} onChange={e=>setPlatformFees({...platformFees, delivery: parseFloat(e.target.value)})} className="w-16 bg-slate-900 border border-slate-700 p-2 rounded text-center text-sm outline-none" aria-label="Commission Frais Livraison" /><span>%</span></div></div>
                        </div>
                    </div>
                )}
                {tab === 'profile' && <ProfileEditor user={user} onSave={() => showNotification("Sauvegardé", "Les données sont à jour.")} onLogout={onLogout} />}
            </div>
            <div className="fixed bottom-0 w-full max-w-md bg-slate-900 border-t border-slate-800 py-3 px-6 flex justify-around z-50">
                <button onClick={() => setTab('dashboard')} className={`flex-1 flex flex-col items-center gap-1 text-[10px] ${tab === 'dashboard' ? 'text-blue-400' : 'text-slate-500'}`}><Icon name="Activity" size={20}/><span>Finance</span></button>
                <button onClick={() => setTab('commissions')} className={`flex-1 flex flex-col items-center gap-1 text-[10px] ${tab === 'commissions' ? 'text-blue-400' : 'text-slate-500'}`}><Icon name="Settings" size={20}/><span>Commissions</span></button>
                <button onClick={() => setTab('profile')} className={`flex-1 flex flex-col items-center gap-1 text-[10px] ${tab === 'profile' ? 'text-blue-400' : 'text-slate-500'}`}><Icon name="User" size={20}/><span>Profil</span></button>
            </div>
        </div>
    );
};

// 3. ESPACE FOURNISSEUR
const SupplierDashboard = ({ supplier, jobs, orders, onUpdateJob, onUpdateOrder, onUpdateUser, onNotify, onLogout, platformFees }) => {
    const [tab, setTab] = useState('tracking');
    const [trackingFilter, setTrackingFilter] = useState('new');
    const [scanCode, setScanCode] = useState('');
    const [newItem, setNewItem] = useState({ name: '', sku: '', price: '', stock: '', image: null });
    const [isAddingItem, setIsAddingItem] = useState(false);
    const [localNotif, setLocalNotif] = useState(null);
    const [proofModalJobId, setProofModalJobId] = useState(null);

    const showNotification = (title, text) => {
        setLocalNotif({ title, text });
        setTimeout(() => setLocalNotif(null), 4000);
    };

    const getGrossMatPrice = (netPrice) => {
        const feePercent = platformFees?.supplierSectors?.[supplier.secteur] ?? platformFees?.material ?? 3;
        return Math.round((netPrice || 0) * (1 + feePercent / 100));
    };

    const myOrders = orders.filter(o => o.supplierId === supplier.id);
    const ordersToPrepare = myOrders.filter(o => o.status === 'paid');
    const escrowOrders = myOrders.filter(o => ['paid', 'prepared', 'searching_driver', 'driver_assigned', 'disputed'].includes(o.status));
    const releasedOrders = myOrders.filter(o => ['shipping', 'delivered'].includes(o.status));

    const supplierJobs = jobs.filter(j => j.interventionData?.materials?.some(m => m.supplierId === supplier.id));
    const escrowJobs = supplierJobs.filter(j => ['funded', 'disputed'].includes(j.status) && j.milestones && j.milestones[0]?.status !== 'validated');
    const releasedJobs = supplierJobs.filter(j => j.milestones && j.milestones[0]?.status === 'validated');

    const calcMatAmount = (job) => job.interventionData?.materials?.filter(m => m.supplierId === supplier.id).reduce((sum, m) => sum + ((m.price || 0) * (m.quantity || 1)), 0) || 0;

    const escrowJobsBalance = escrowJobs.reduce((acc, j) => acc + calcMatAmount(j), 0);
    const releasedJobsBalance = releasedJobs.reduce((acc, j) => acc + calcMatAmount(j), 0);
    const escrowOrdersBalance = escrowOrders.reduce((acc, o) => acc + (o.subtotal || 0), 0);
    const releasedOrdersBalance = releasedOrders.reduce((acc, o) => acc + (o.subtotal || 0), 0);

    const totalEscrow = escrowJobsBalance + escrowOrdersBalance;
    const availableBalance = releasedJobsBalance + releasedOrdersBalance;

    const disputedOrders = myOrders.filter(o => o.status === 'disputed');
    const activeB2COrders = myOrders.filter(o => ['prepared', 'searching_driver', 'driver_assigned', 'shipping'].includes(o.status));
    const historyB2COrders = myOrders.filter(o => ['delivered', 'disputed'].includes(o.status));

    const newB2BJobs = supplierJobs.filter(j => j.status === 'funded' && j.milestones && j.milestones[0]?.status !== 'validated');
    const activeB2BJobs = supplierJobs.filter(j => ['materials_picked_up', 'work_done'].includes(j.status));
    const historyB2BJobs = supplierJobs.filter(j => ['completed', 'disputed'].includes(j.status));

    const supplierReviews = myOrders.filter(o => o.supplierRating).map(o => ({ rating: o.supplierRating, comment: o.supplierReview, date: o.date, clientName: o.clientName }));
    const avgRating = supplierReviews.length > 0 ? (supplierReviews.reduce((sum, r) => sum + r.rating, 0) / supplierReviews.length).toFixed(1) : (supplier.rating || 5.0);

    const handleScan = () => {
        const job = jobs.find(j => j.financials?.tokenCode === scanCode && j.status === 'funded');
        const order = orders.find(o => o.deliveryCode === scanCode && o.status === 'driver_assigned') || orders.find(o => o.pickupCode === scanCode && o.status === 'prepared');

        if(job) {
            onUpdateJob(job.id, { ...job, status: 'materials_picked_up', milestones: job.milestones.map(m=>m.id===1?{...m, status:'validated'}:m) });
            onNotify(job.clientId, 'client', `Le fournisseur a remis le matériel à l'artisan. Les travaux commencent.`);
            onNotify(job.artisanId, 'artisan', "Matériel récupéré ! Vous pouvez démarrer les travaux.");
            const amount = calcMatAmount(job);
            showNotification("Retrait B2B validé", `Matériel remis à l'artisan. ${(amount || 0).toLocaleString()} F transférés vers votre solde disponible.`);
            setScanCode('');
        } else if (order) {
            if (order.deliveryMode === 'delivery') onUpdateOrder(order.id, { status: 'shipping' });
            else onUpdateOrder(order.id, { status: 'delivered' });
            showNotification("Retrait B2C validé", `Colis remis. ${(order.subtotal || 0).toLocaleString()} F transférés vers votre solde disponible.`);
            setScanCode('');
        } else {
            showNotification("Erreur", "Code invalide ou commande non prête.");
        }
    };

    const handlePrepareOrder = (orderId) => {
        const order = orders.find(o => o.id === orderId);
        onUpdateOrder(orderId, { status: order.deliveryMode === 'delivery' ? 'searching_driver' : 'prepared' });
        showNotification("Commande prête", `La commande est passée en attente de retrait.`);
    };

    const handleImageUpload = (e) => {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onloadend = () => { setNewItem({ ...newItem, image: reader.result }); };
            reader.readAsDataURL(file);
        }
    };

    const handleAddItem = (e) => {
        e.preventDefault();
        const product = { id: `prod_${Date.now()}`, sku: newItem.sku || `SKU-${Math.floor(Math.random()*1000)}`, name: newItem.name, price: parseInt(newItem.price), stock: parseInt(newItem.stock) || 0, image: newItem.image };
        onUpdateUser({ ...supplier, catalog: [...(supplier.catalog || []), product] });
        setNewItem({ name: '', sku: '', price: '', stock: '', image: null });
        setIsAddingItem(false);
        showNotification("Catalogue mis à jour", `Le produit a été ajouté avec succès.`);
    };

    const handleProofUpload = (photo) => {
        showNotification("Preuve Envoyée", "Votre défense a été soumise à l'équipe de médiation.");
        setProofModalJobId(null);
    };

    return (
        <div className="min-h-screen bg-gray-50 pb-20 flex flex-col relative">
            {localNotif && (
                <div className="absolute top-4 left-4 right-4 bg-green-600 text-white p-4 rounded-xl shadow-2xl z-[100] animate-in slide-in-from-top-5 flex items-start gap-3">
                    <Icon name={localNotif.title === "Erreur" ? "AlertTriangle" : "CheckCircle"} size={24} className="shrink-0" />
                    <div><p className="font-bold text-sm">{localNotif.title}</p><p className="text-xs text-green-100">{localNotif.text}</p></div>
                    <button onClick={() => setLocalNotif(null)} className="ml-auto text-green-200 hover:text-white" aria-label="Fermer la notification" title="Fermer"><Icon name="X" size={16}/></button>
                </div>
            )}
            
            {proofModalJobId && <ProofUploadModal onClose={()=>setProofModalJobId(null)} onSubmit={handleProofUpload} isMaterialStep={true} />}

            <div className="bg-green-800 text-white pt-12 pb-8 px-6 rounded-b-[2rem] flex justify-between items-center shadow-lg">
                <div className="text-left"><p className="text-xs text-green-200 uppercase font-bold">Espace Fournisseur</p><h1 className="text-2xl font-bold mt-1">{supplier.raisonSociale}</h1></div>
                <Button variant="ghost" onClick={onLogout} className="p-2 text-white hover:text-green-200"><Icon name="LogOut"/></Button>
            </div>
            
            <div className="px-4 flex-1 mt-6">
                
                {!supplier.isVerified && (
                    <div className="bg-red-50 p-4 rounded-xl border border-red-200 mb-6 animate-pulse shadow-sm">
                        <h3 className="font-bold text-sm text-red-800 flex items-center gap-2"><Icon name="AlertTriangle" size={16}/> Compte Restreint</h3>
                        <p className="text-xs text-red-600 mt-1">Vous devez compléter votre vérification d'identité dans l'onglet Profil pour rendre votre catalogue public.</p>
                    </div>
                )}

                {tab === 'dashboard' && (
                    <div className="space-y-6 animate-in fade-in">
                        <div className="bg-gradient-to-r from-green-700 to-emerald-900 rounded-2xl p-6 text-white shadow-lg relative overflow-hidden transition-all">
                            <Icon name="Wallet" className="absolute -bottom-4 -right-4 text-white/10" size={100}/>
                            <p className="text-white/80 text-xs font-bold uppercase mb-1 relative z-10">Portefeuille (Disponible)</p>
                            <h3 className="text-4xl font-bold mb-2 relative z-10">{(availableBalance || 0).toLocaleString()} <span className="text-xl">F</span></h3>
                        </div>
                        <div className="bg-white p-5 rounded-2xl shadow-sm border border-gray-100 transition-all">
                            <h3 className="text-[10px] text-gray-500 font-bold uppercase mb-2">Séquestre Bloqué (En attente)</h3>
                            <p className="text-3xl font-bold text-blue-600 mb-3">{(totalEscrow || 0).toLocaleString()} F</p>
                            <div className="space-y-2 text-xs text-gray-600 border-t pt-3">
                                <div className="flex justify-between items-center">
                                    <span className="flex items-center gap-1"><Icon name="Wrench" size={14}/> B2B (Devis Artisans)</span> 
                                    <span className="font-bold">{(escrowJobsBalance || 0).toLocaleString()} F</span>
                                </div>
                                <div className="flex justify-between items-center">
                                    <span className="flex items-center gap-1"><Icon name="User" size={14}/> B2C (Commandes Directes)</span> 
                                    <span className="font-bold">{(escrowOrdersBalance || 0).toLocaleString()} F</span>
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {tab === 'tracking' && (
                    <div className="space-y-4 animate-in slide-in-from-bottom-5">
                        <div className="flex bg-gray-200 p-1 rounded-xl mb-4">
                            <button onClick={() => setTrackingFilter('new')} className={`flex-1 py-2 text-xs font-bold rounded-lg transition-all ${trackingFilter === 'new' ? 'bg-white shadow text-green-600' : 'text-gray-500'}`}>À Préparer</button>
                            <button onClick={() => setTrackingFilter('active')} className={`flex-1 py-2 text-xs font-bold rounded-lg transition-all ${trackingFilter === 'active' ? 'bg-white shadow text-green-600' : 'text-gray-500'}`}>En transit</button>
                            <button onClick={() => setTrackingFilter('history')} className={`flex-1 py-2 text-xs font-bold rounded-lg transition-all ${trackingFilter === 'history' ? 'bg-white shadow text-green-600' : 'text-gray-500'}`}>Historique</button>
                        </div>

                        {trackingFilter === 'new' && (
                            <>
                                {ordersToPrepare.length === 0 && newB2BJobs.length === 0 ? (
                                    <p className="text-center text-gray-400 py-10 text-sm">Rien à préparer pour le moment.</p>
                                ) : (
                                    <>
                                        {ordersToPrepare.map(o => (
                                            <div key={o.id} className="bg-white p-4 rounded-xl shadow-sm border-l-4 border-orange-500 mb-3">
                                                <div className="flex justify-between items-center mb-2"><span className="font-bold text-sm">CMD B2C #{String(o.id).slice(-4)}</span><span className="font-bold text-green-600">{(o.subtotal || 0).toLocaleString()} F</span></div>
                                                <p className="text-xs text-gray-500 mb-3"><Icon name={o.deliveryMode === 'delivery' ? 'Truck' : 'Store'} size={12} className="inline mr-1"/> {o.deliveryMode === 'delivery' ? 'Pour Livraison (Via Livreur)' : 'Retrait en Magasin (Client)'}</p>
                                                <Button className="w-full py-2 text-xs bg-orange-600 hover:bg-orange-700 shadow-none" onClick={() => handlePrepareOrder(o.id)} disabled={!supplier.isVerified}>Marquer comme Prête</Button>
                                            </div>
                                        ))}

                                        {newB2BJobs.map(j => (
                                            <div key={j.id} className="bg-white p-4 rounded-xl shadow-sm border-l-4 border-blue-500 mb-3">
                                                <div className="flex justify-between items-center mb-2"><span className="font-bold text-sm">Devis B2B (Artisan)</span><span className="font-bold text-blue-600">{calcMatAmount(j).toLocaleString()} F</span></div>
                                                <p className="text-xs text-gray-500">L'artisan <b>{j.artisanName}</b> va passer récupérer du matériel. Le paiement est garanti sous séquestre.</p>
                                            </div>
                                        ))}
                                    </>
                                )}
                            </>
                        )}

                        {trackingFilter === 'active' && (
                            <>
                                {activeB2COrders.length === 0 && activeB2BJobs.length === 0 ? (
                                    <p className="text-center text-gray-400 py-10 text-sm">Aucune commande en attente de retrait / transit.</p>
                                ) : (
                                    <>
                                        {activeB2COrders.map(o => (
                                            <div key={o.id} className="bg-white p-4 rounded-xl shadow-sm border border-gray-100 mb-3">
                                                <div className="flex justify-between items-center mb-1"><span className="font-bold text-sm text-gray-800">CMD B2C #{String(o.id).slice(-4)}</span><Badge text="Attente Retrait" color="bg-blue-50 text-blue-700"/></div>
                                                <p className="text-xs text-gray-500 mt-2">La commande est prête. En attente du passage du {o.deliveryMode === 'delivery' ? 'Livreur' : 'Client'}.</p>
                                            </div>
                                        ))}
                                        {activeB2BJobs.map(j => (
                                            <div key={j.id} className="bg-white p-4 rounded-xl shadow-sm border border-gray-100 mb-3">
                                                <div className="flex justify-between items-center mb-1"><span className="font-bold text-sm text-gray-800">Devis B2B #{String(j.id).slice(-4)}</span><Badge text="Matériel Récupéré" color="bg-blue-50 text-blue-700"/></div>
                                                <p className="text-xs text-gray-500 mt-2">L'artisan {j.artisanName} réalise les travaux.</p>
                                            </div>
                                        ))}
                                    </>
                                )}
                            </>
                        )}

                        {trackingFilter === 'history' && (
                            <>
                                {historyB2COrders.length === 0 && historyB2BJobs.length === 0 ? (
                                    <p className="text-center text-gray-400 py-10 text-sm">Aucun historique.</p>
                                ) : (
                                    [...historyB2COrders, ...historyB2BJobs].map((item, idx) => (
                                        <div key={idx} className="bg-white p-4 rounded-xl shadow-sm border border-gray-100 mb-3 flex justify-between items-center">
                                            <div>
                                                <p className="font-bold text-sm text-gray-800">{item.clientName ? `Chantier: ${item.clientName}` : `CMD #${String(item.id).slice(-4)}`}</p>
                                                <p className="text-[10px] text-gray-400">{item.status === 'disputed' ? 'Litige' : 'Terminé/Livré'}</p>
                                            </div>
                                            <span className={`font-bold text-sm ${item.status === 'disputed' ? 'text-red-600' : 'text-green-600'}`}>{(item.subtotal || calcMatAmount(item)).toLocaleString()} F</span>
                                        </div>
                                    ))
                                )}
                            </>
                        )}
                    </div>
                )}

                {tab === 'catalog' && (
                    <div className="space-y-4 animate-in slide-in-from-bottom-5">
                        <div className="flex justify-between items-center border-b pb-2"><h2 className="font-bold text-lg text-slate-800">Mon Catalogue</h2><Button variant="success" className="py-2 text-xs shadow-none" iconName="Plus" onClick={() => setIsAddingItem(!isAddingItem)} disabled={!supplier.isVerified}>{isAddingItem ? 'Fermer' : 'Ajouter un article'}</Button></div>
                        {isAddingItem && (
                            <form onSubmit={handleAddItem} className="bg-white p-4 rounded-xl shadow-sm border border-green-200 space-y-4 mb-6">
                                <div className="flex gap-4 items-start">
                                    {newItem.image ? (
                                        <div className="w-20 h-20 shrink-0 relative rounded-xl border border-gray-200 overflow-hidden"><img src={newItem.image} className="w-full h-full object-cover" alt="Aperçu du nouvel article" /><button type="button" onClick={()=>setNewItem({...newItem, image:null})} className="absolute top-1 right-1 bg-white/80 rounded-full p-1" aria-label="Supprimer la photo"><Icon name="X" size={12} className="text-red-500"/></button></div>
                                    ) : (
                                        <label className="w-20 h-20 shrink-0 bg-gray-50 hover:bg-gray-100 rounded-xl border-2 border-dashed border-gray-300 flex flex-col items-center justify-center cursor-pointer transition-colors text-gray-400">
                                            <Icon name="Camera" size={20} className="mb-1"/>
                                            <span className="text-[10px] font-bold">Photo</span>
                                            <input type="file" accept="image/*" onChange={handleImageUpload} className="hidden" aria-label="Photo de l'article"/>
                                        </label>
                                    )}
                                    <div className="flex-1 space-y-3">
                                        <InputGroup label="Nom de l'article"><BasicInput value={newItem.name} onChange={e=>setNewItem({...newItem, name: e.target.value})} placeholder="Ex: Ciment Bélier" required/></InputGroup>
                                    </div>
                                </div>
                                <div className="grid grid-cols-2 gap-3">
                                    <InputGroup label="Votre Prix Net (FCFA)">
                                        <input type="number" value={newItem.price} onChange={e=>setNewItem({...newItem, price: e.target.value})} className="w-full bg-gray-50 border p-3 rounded-xl focus:border-green-500 outline-none font-bold" placeholder="0" aria-label="Votre Prix Net (FCFA)" required/>
                                    </InputGroup>
                                    <InputGroup label="Stock dispo">
                                        <input type="number" value={newItem.stock} onChange={e=>setNewItem({...newItem, stock: e.target.value})} className="w-full bg-gray-50 border p-3 rounded-xl outline-none" placeholder="0" aria-label="Stock dispo"/>
                                    </InputGroup>
                                </div>
                                {newItem.price && (
                                    <p className="text-xs text-right text-gray-500 bg-green-50 p-2 rounded-lg">Le client verra : <span className="font-bold text-green-700">{getGrossMatPrice(parseInt(newItem.price)).toLocaleString()} F TTC</span></p>
                                )}
                                <Button type="submit" variant="success" className="w-full">Enregistrer l'article</Button>
                            </form>
                        )}
                        <div className="space-y-3 pb-8">
                            {!supplier.catalog || supplier.catalog.length === 0 ? <p className="text-center text-gray-400 py-10">Votre catalogue est vide.</p> : 
                            supplier.catalog.map(item => (
                                <div key={item.id} className="bg-white p-3 rounded-xl shadow-sm border border-gray-100 flex items-center gap-3">
                                    {item.image ? <img src={item.image} className="w-12 h-12 object-cover rounded-lg border border-gray-100" alt={item.name || "Image de l'article"}/> : <div className="w-12 h-12 bg-gray-50 rounded-lg flex items-center justify-center border border-gray-100 text-gray-400"><Icon name="Package" size={20}/></div>}
                                    <div className="flex-1"><p className="font-bold text-gray-800 text-sm">{item.name}</p><p className="text-xs text-gray-400 mt-1">Réf: {item.sku || 'N/A'}</p></div>
                                    <div className="text-right">
                                        <p className="font-black text-green-700 text-sm">{getGrossMatPrice(item.price).toLocaleString()} F <span className="text-[10px] text-gray-400 font-normal">TTC</span></p>
                                        <p className="text-xs text-gray-500 font-bold">Net: {(item.price || 0).toLocaleString()} F</p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {tab === 'scan' && (
                    <div className="animate-in slide-in-from-bottom-5">
                        <div className="bg-white p-6 rounded-2xl shadow-lg border border-green-100 text-center">
                            <div className="w-20 h-20 bg-green-50 rounded-full flex items-center justify-center mx-auto mb-4 text-green-600"><Icon name="Scan" size={40}/></div>
                            <h2 className="text-xl font-bold text-gray-800 mb-2">Validation de Retrait</h2>
                            <p className="text-sm text-gray-500 mb-6">Scannez le code présenté par l'artisan ou le livreur pour valider la remise et débloquer les fonds du compte séquestre.</p>
                            <input value={scanCode} onChange={e=>setScanCode(e.target.value.toUpperCase())} placeholder="Ex: PA-1234 ou LIVREUR-1234" className="w-full border-2 border-gray-200 focus:border-green-500 p-4 text-center text-2xl font-mono mb-4 rounded-xl outline-none tracking-widest uppercase" aria-label="Code de retrait"/>
                            <Button onClick={handleScan} className="w-full py-4 text-lg bg-green-600 hover:bg-green-700 shadow-lg shadow-green-200" disabled={scanCode.length < 5 || !supplier.isVerified}>Valider la remise</Button>
                        </div>
                    </div>
                )}

                {tab === 'incidents' && (
                    <div className="space-y-4 animate-in slide-in-from-bottom-5">
                        <h2 className="font-bold text-lg text-slate-800 border-b pb-2">Litiges et Incidents</h2>
                        {disputedOrders.length === 0 && escrowJobs.filter(j=>j.status==='disputed').length === 0 ? (
                            <div className="bg-white p-8 rounded-2xl shadow-sm border border-gray-100 text-center mt-6">
                                <Icon name="CheckCircle" className="mx-auto text-green-500 mb-3" size={48}/>
                                <h3 className="font-bold text-gray-800">Aucun litige</h3>
                                <p className="text-gray-500 text-xs mt-2">Votre réputation est impeccable. Continuez ainsi !</p>
                            </div>
                        ) : (
                            <>
                                {disputedOrders.map(item => (
                                    <div key={item.id} className="bg-white p-5 rounded-2xl shadow-sm border-l-4 border-red-500 mb-4">
                                        <div className="flex justify-between items-start mb-3">
                                            <div>
                                                <h3 className="font-bold text-sm text-gray-800">Commande #{String(item.id).slice(-4)}</h3>
                                                <p className="text-xs text-gray-500">Fonds gelés: <span className="font-bold text-red-600">{(item.subtotal || 0).toLocaleString()} F</span></p>
                                            </div>
                                            <Badge text="Bloqué" color="bg-red-100 text-red-700"/>
                                        </div>
                                        <div className="bg-red-50 p-3 rounded-lg border border-red-100 mb-4">
                                            <p className="text-xs font-bold text-red-900 mb-1"><Icon name="AlertTriangle" size={12} className="inline"/> Motif déclaré :</p>
                                            <p className="text-xs text-red-800 italic">"{item.disputeReason || 'Non spécifié'}"</p>
                                        </div>
                                        <Button variant="outline" className="w-full text-xs shadow-none border-red-200 text-red-700 font-bold" onClick={() => setProofModalJobId({id: item.id, type: 'dispute'})}>Soumettre une contre-preuve</Button>
                                    </div>
                                ))}
                                {escrowJobs.filter(j=>j.status==='disputed').map(item => (
                                    <div key={item.id} className="bg-white p-5 rounded-2xl shadow-sm border-l-4 border-red-500 mb-4">
                                        <div className="flex justify-between items-start mb-3">
                                            <div>
                                                <h3 className="font-bold text-sm text-gray-800">Devis B2B #{String(item.id).slice(-4)}</h3>
                                                <p className="text-xs text-gray-500">Fonds gelés: <span className="font-bold text-red-600">{calcMatAmount(item).toLocaleString()} F</span></p>
                                            </div>
                                            <Badge text="Bloqué" color="bg-red-100 text-red-700"/>
                                        </div>
                                        <div className="bg-red-50 p-3 rounded-lg border border-red-100 mb-4">
                                            <p className="text-xs font-bold text-red-900 mb-1"><Icon name="AlertTriangle" size={12} className="inline"/> Chantier en litige :</p>
                                            <p className="text-xs text-red-800 italic">L'artisan {item.artisanName} a un litige avec le client. Vos fonds matériels sont bloqués en attente de médiation.</p>
                                        </div>
                                    </div>
                                ))}
                            </>
                        )}
                    </div>
                )}

                {tab === 'profile' && <ProfileEditor user={supplier} onSave={(u)=>{onUpdateUser(u); showNotification("Profil à jour", "Vos informations et KYC ont été sauvegardés.");}} onLogout={onLogout} reviews={supplierReviews} avgRating={avgRating} />}
            </div>
            
            <div className="fixed bottom-0 w-full max-w-md bg-white border-t py-2 px-1 flex justify-between z-50">
                <button onClick={() => setTab('tracking')} className={`flex flex-col items-center gap-1 p-2 w-1/6 ${tab === 'tracking' ? 'text-green-600' : 'text-gray-400'}`}>
                    <div className="relative"><Icon name="ClipboardList" size={20}/>{(ordersToPrepare.length > 0) && <span className="absolute -top-1 -right-2 w-2 h-2 bg-red-500 rounded-full"></span>}</div>
                    <span className="text-[8px] font-bold">Suivi</span>
                </button>
                <button onClick={() => setTab('catalog')} className={`flex flex-col items-center gap-1 p-2 w-1/6 ${tab === 'catalog' ? 'text-green-600' : 'text-gray-400'}`}>
                    <Icon name="Package" size={20}/>
                    <span className="text-[8px] font-bold">Stock</span>
                </button>
                <button onClick={() => setTab('scan')} className={`flex flex-col items-center gap-1 p-2 w-1/6 ${tab === 'scan' ? 'text-green-600' : 'text-gray-400'}`}>
                    <Icon name="Scan" size={20}/>
                    <span className="text-[8px] font-bold">Scan</span>
                </button>
                <button onClick={() => setTab('dashboard')} className={`flex flex-col items-center gap-1 p-2 w-1/6 ${tab === 'dashboard' ? 'text-green-600' : 'text-gray-400'}`}>
                    <Icon name="Bank" size={20}/>
                    <span className="text-[8px] font-bold">Finance</span>
                </button>
                <button onClick={() => setTab('incidents')} className={`flex flex-col items-center gap-1 p-2 w-1/6 ${tab === 'incidents' ? 'text-red-500' : 'text-gray-400'}`}>
                    <div className="relative"><Icon name="AlertTriangle" size={20}/>{(disputedOrders.length > 0 || escrowJobs.filter(j=>j.status==='disputed').length > 0) && <span className="absolute -top-1 -right-2 w-4 h-4 bg-red-500 text-white text-[8px] font-bold flex items-center justify-center rounded-full">{disputedOrders.length + escrowJobs.filter(j=>j.status==='disputed').length}</span>}</div>
                    <span className="text-[8px] font-bold">Litiges</span>
                </button>
                <button onClick={() => setTab('profile')} className={`flex flex-col items-center gap-1 p-2 w-1/6 ${tab === 'profile' ? 'text-green-600' : 'text-gray-400'}`}>
                    <div className="relative"><Icon name="User" size={20}/>{!supplier.isVerified && <span className="absolute -top-1 -right-2 w-2 h-2 bg-red-500 rounded-full animate-ping"></span>}</div>
                    <span className="text-[8px] font-bold">Profil</span>
                </button>
            </div>
        </div>
    );
};

// 2. ESPACE CLIENT
const ClientDashboard = ({ user, onLogout, categories, artisans, suppliers, jobs, orders, onAddJob, onAddOrder, platformFees, onNotify, onUpdateJob, onUpdateOrder, onUpdateUser, isNightMode }) => {
  const [tab, setTab] = useState('home');
  const [directoryType, setDirectoryType] = useState('artisan');
  const [artisanCategoryFilter, setArtisanCategoryFilter] = useState('all');
  const [supplierZoneFilter, setSupplierZoneFilter] = useState('all');
  const [viewMode, setViewMode] = useState('list');

  const [selectedArtisan, setSelectedArtisan] = useState(null);
  
  // États étendus pour la demande de devis (Macro-Chantier)
  const [problemDescription, setProblemDescription] = useState('');
  const [provisionalBudget, setProvisionalBudget] = useState('');
  const [planDocument, setPlanDocument] = useState(null);
  
  const [selectedSupplier, setSelectedSupplier] = useState(null);
  const [cart, setCart] = useState([]);
  const [showCart, setShowCart] = useState(false);
  const [showPayment, setShowPayment] = useState(null); 
  const [showDisputeModal, setShowDisputeModal] = useState(null);
  const [ratingTarget, setRatingTarget] = useState(null);
  const [deliveryMode, setDeliveryMode] = useState('pickup');
  const [localNotif, setLocalNotif] = useState(null);

  const DELIVERY_FEE = 1500;

  const showNotification = (title, text) => {
      setLocalNotif({ title, text });
      setTimeout(() => setLocalNotif(null), 4000);
  };

  const getGrossMatPrice = (netPrice, sector) => {
      const feePercent = platformFees?.supplierSectors?.[sector] ?? platformFees?.material ?? 3;
      return Math.round((netPrice || 0) * (1 + feePercent / 100));
  };
  const grossDeliveryFee = Math.round(DELIVERY_FEE * (1 + (platformFees?.delivery ?? 5) / 100));
  
  const getJobTotal = (j) => {
      if(!j || !j.financials) return 0;
      if(j.jobType === 'macro') return j.escrow?.total_budget || 0;
      return (j.financials.laborCost || 0) + (j.financials.tokenAmount || 0) + (j.financials.platformFeesBreakdown?.labor || 0) + (j.financials.platformFeesBreakdown?.material || 0);
  };

  const uniqueZones = [...new Set(suppliers.map(s => s.adresse?.split(',')[0].trim()).filter(Boolean))];

  let baseArtisans = artisans.filter(a => a.isVerified && (artisanCategoryFilter === 'all' || a.category === artisanCategoryFilter));
  if (isNightMode) {
      baseArtisans = baseArtisans.filter(a => a.hasEmergencyKit);
  }
  const filteredArtisans = baseArtisans;
  const filteredSuppliers = isNightMode ? [] : suppliers.filter(s => s.isVerified && (supplierZoneFilter === 'all' || (s.adresse && s.adresse.includes(supplierZoneFilter))));

  const myRequests = jobs.filter(j => j.clientId === user.id || j.clientId === 999);
  const myOrders = orders.filter(o => o.clientId === user.id || o.clientId === 999);

  const getOrderTotal = (o) => o.total || ((o.subtotal || 0) + (o.platformFee || 0) + (o.deliveryCost || 0));

  const allTransactions = [];
  let totalEscrow = 0;
  let totalDisbursed = 0;

  // 1. Historique des Commandes B2C
  myOrders.forEach(o => {
      if (['paid', 'prepared', 'driver_assigned', 'shipping', 'delivered', 'disputed'].includes(o.status)) {
          const amount = getOrderTotal(o);
          const isCompleted = o.status === 'delivered';
          if (isCompleted) totalDisbursed += amount; else totalEscrow += amount;
          allTransactions.push({
              id: `ord_${o.id}`, type: 'order', title: `Commande: ${o.supplierName}`,
              date: o.date || new Date().toLocaleDateString(), amount, isCompleted, status: o.status
          });
      }
  });

  // 2. Historique des Chantiers (Standard & Macro)
  myRequests.forEach(j => {
      if (j.jobType === 'macro') {
          // Pour les macro, on tracke chaque jalon payé individuellement
          (j.milestones || []).forEach((m) => {
              if (m.status === 'paid') {
                  const isCompleted = j.status === 'completed'; 
                  if (isCompleted) totalDisbursed += m.amount; else totalEscrow += m.amount;
                  allTransactions.push({
                      id: `mac_${j.id}_${m.id}`, type: 'job', title: `Jalon: ${m.name} (${j.artisanName})`,
                      date: j.date || new Date().toLocaleDateString(), amount: m.amount, isCompleted, status: j.status
                  });
              }
          });
      } else {
          // Chantiers standards
          if (['funded', 'materials_picked_up', 'work_done', 'completed', 'disputed'].includes(j.status)) {
              const amount = getJobTotal(j);
              const isCompleted = j.status === 'completed';
              if (isCompleted) totalDisbursed += amount; else totalEscrow += amount;
              allTransactions.push({
                  id: `std_${j.id}`, type: 'job', title: `Prestation: ${j.artisanName}`,
                  date: j.date || new Date().toLocaleDateString(), amount, isCompleted, status: j.status
              });
          }
      }
  });

  const totalSpent = totalEscrow + totalDisbursed;
  // Tri décroissant pour afficher les transactions les plus récentes en haut
  allTransactions.sort((a, b) => b.id.localeCompare(a.id));

  const handleDocumentChange = (e) => {
      const file = e.target.files[0];
      if (file) {
          const reader = new FileReader();
          reader.onloadend = () => setPlanDocument(reader.result);
          reader.readAsDataURL(file);
      }
  };

  const handleSendQuoteRequest = () => {
      if(!problemDescription.trim()) {
          showNotification("Erreur", "Veuillez décrire votre besoin.");
          return;
      }
      const isMacro = selectedArtisan.isLeadContractor;
      
      if (isMacro && !provisionalBudget) {
          showNotification("Erreur", "Veuillez indiquer un budget prévisionnel pour ce macro-projet.");
          return;
      }

      onAddJob({ 
          id: Date.now(), clientId: user.id, clientName: user.prenoms, 
          artisanId: selectedArtisan.id, artisanName: selectedArtisan.name, artisanCategory: selectedArtisan.category, 
          jobType: isMacro ? 'macro' : 'standard',
          problem: problemDescription,
          provisionalBudget: isMacro ? parseInt(provisionalBudget) : null,
          planDocument: isMacro ? planDocument : null,
          status: 'sent', date: new Date().toLocaleDateString(), 
          paymentStatus: 'pending', financials: { tokenCode: null, tokenAmount: 0, laborCost: 0, platformFeesBreakdown: { labor: 0, material: 0, delivery: 0 } }, 
          interventionData: { materials: [] }, 
          subJobs: [], escrow: { total_budget: 0, currently_escrowed: 0, disbursed_to_subs: 0 },
          milestones: [{id: 1, status: 'pending'}, {id: 2, status: 'pending'}], 
          isNightRequest: isNightMode 
      });
      onNotify(selectedArtisan.id, 'artisan', `Nouvelle demande reçue de ${user.prenoms}.`);
      showNotification("Demande envoyée", "Le professionnel a été notifié.");
      setSelectedArtisan(null); setProblemDescription(''); setProvisionalBudget(''); setPlanDocument(null); setTab('requests');
  };

  const handleCheckoutSuccess = (provider) => {
      if (showPayment.type === 'order') {
          // Logique Commande Matériel
          const subtotalNet = cart.reduce((a, b) => a + (b.price || 0), 0);
          const subtotalGross = cart.reduce((a, b) => a + getGrossMatPrice(b.price, selectedSupplier.secteur), 0);
          const matFeeAmount = subtotalGross - subtotalNet;
          const finalDeliveryCostNet = deliveryMode === 'delivery' ? DELIVERY_FEE : 0;
          const deliveryFeeAmount = deliveryMode === 'delivery' ? (grossDeliveryFee - DELIVERY_FEE) : 0;

          onAddOrder({ id: Date.now(), clientId: user.id, clientName: user.prenoms, clientPhone: user.telephone || "0707070707", clientAddress: user.adresse || "Non spécifiée", supplierId: selectedSupplier.id, supplierName: selectedSupplier.raisonSociale, items: cart, subtotal: subtotalNet, deliveryCost: finalDeliveryCostNet, platformFee: matFeeAmount + deliveryFeeAmount, total: subtotalGross + (deliveryMode === 'delivery' ? grossDeliveryFee : 0), status: 'paid', deliveryMode: deliveryMode, pickupCode: `RETRAIT-${Math.floor(1000 + Math.random() * 9000)}`, deliveryCode: `LIVREUR-${Math.floor(1000 + Math.random() * 9000)}`, clientReceiveCode: `RECEPTION-${Math.floor(1000 + Math.random() * 9000)}`, platformFeesBreakdown: { material: matFeeAmount, delivery: deliveryFeeAmount, labor: 0 }, date: new Date().toLocaleDateString() });
          setCart([]); setShowCart(false); setSelectedSupplier(null); setTab('requests');
          showNotification("Paiement Réussi", "Votre commande a été validée sous séquestre.");
      } else {
          // Logique Paiement Prestation B2C
          const job = showPayment.item;
          
          if(job.jobType === 'macro') {
              // Paiement d'un Jalon Macro
              const milestoneIndex = job.milestones.findIndex(m => m.status === 'pending');
              const updatedMilestones = [...job.milestones];
              updatedMilestones[milestoneIndex].status = 'paid';
              
              onUpdateJob(job.id, {
                  ...job,
                  status: 'funded',
                  paymentStatus: 'funded',
                  escrow: {
                      ...job.escrow,
                      currently_escrowed: job.escrow.currently_escrowed + showPayment.amount
                  },
                  milestones: updatedMilestones
              });
              showNotification("Jalon Validé", "Le séquestre a été approvisionné. L'équipe peut avancer.");
          } else {
              // Paiement Standard
              const code = `PA-${Math.floor(1000 + Math.random() * 9000)}`;
              onUpdateJob(job.id, { 
                  ...job, status: job.isNightRequest ? 'materials_picked_up' : 'funded', paymentStatus: 'funded', 
                  financials: { ...job.financials, tokenCode: code },
                  milestones: job.milestones.map(m=> (m.id===1 && job.isNightRequest) ? {...m, status:'validated'} : m)
              });
              showNotification("Séquestre Validé", "Le professionnel est notifié.");
          }
      }
      setShowPayment(null);
  };

  const handleValidateWork = (job) => {
      onUpdateJob(job.id, { ...job, status: 'completed', milestones: job.milestones.map(m=>m.id===2?{...m, status:'validated'}:m) });
      onNotify(job.artisanId, 'artisan', `Le client a validé les travaux ! Vos fonds ont été décaissés.`);
      showNotification("Travaux validés", "La prestation est clôturée.");
  };

  if (selectedArtisan) {
      const isMacro = selectedArtisan.isLeadContractor;
      return (
          <div className="bg-white min-h-screen pt-12 px-6 flex flex-col">
              <button onClick={() => setSelectedArtisan(null)} className="mb-4 flex items-center gap-1 text-gray-500"><Icon name="ChevronLeft"/> Retour</button>
              <div className="flex gap-4 items-center mb-2">
                  {selectedArtisan.photo ? <img src={selectedArtisan.photo} className="w-16 h-16 rounded-2xl object-cover shadow-sm" alt={`Photo de ${selectedArtisan.name}`} /> : <div className="w-16 h-16 bg-blue-50 rounded-2xl flex items-center justify-center text-blue-500 font-bold text-xl uppercase">{selectedArtisan.name[0]}{selectedArtisan.prenoms[0]}</div>}
                  <div>
                      <h2 className="text-2xl font-bold flex items-center gap-2">{selectedArtisan.name} {selectedArtisan.prenoms} {isMacro && <Icon name="Star" className="text-yellow-500" fill="currentColor" size={16}/>}</h2>
                      <Badge text={categories.find(c=>c.id===selectedArtisan.category)?.name || selectedArtisan.category} color={isMacro ? "bg-indigo-100 text-indigo-700" : "bg-blue-50 text-blue-700"} />
                  </div>
              </div>

              {isMacro && (
                  <div className="bg-indigo-50 border border-indigo-200 p-3 rounded-xl mt-4 mb-4">
                      <p className="text-xs text-indigo-800 font-bold mb-1 flex items-center gap-1"><Icon name="Briefcase" size={14}/> Projet de Rénovation Globale</p>
                      <p className="text-[10px] text-indigo-600">Le Maître d'Œuvre analysera votre besoin et votre plan pour construire un devis détaillé avec un échéancier (Jalons).</p>
                  </div>
              )}
              
              <div className="flex-1 overflow-y-auto pb-6">
                  {isMacro && (
                      <div className="space-y-4 mb-4 animate-in fade-in">
                          <InputGroup label="Budget Prévisionnel Estimé (FCFA)">
                              <BasicInput type="number" value={provisionalBudget} onChange={e=>setProvisionalBudget(e.target.value)} placeholder="Ex: 15000000"/>
                          </InputGroup>
                          <ImageUploadField 
                              label="Joindre un Plan (PDF ou Image)" 
                              image={planDocument} 
                              onChange={handleDocumentChange} 
                              iconName="FileText"
                              hint="Document technique, croquis, plan d'architecte..."
                          />
                      </div>
                  )}

                  <InputGroup label={`Décrivez précisément votre ${isMacro ? 'projet' : 'besoin'}`}>
                      <textarea className="w-full h-32 bg-gray-50 border rounded-xl p-3 outline-none focus:border-blue-500" value={problemDescription} onChange={e=>setProblemDescription(e.target.value)} placeholder="Ex: Construction d'une villa R+1..." aria-label="Description du problème pour diagnostic"></textarea>
                  </InputGroup>
                  <Button className={`w-full mt-4 py-3 ${isMacro ? 'bg-indigo-600 hover:bg-indigo-700' : ''}`} iconName="Send" onClick={handleSendQuoteRequest}>Envoyer {isMacro ? "la demande globale" : "la Demande"}</Button>
              </div>
          </div>
      );
  }

  if (selectedSupplier) {
      const subtotalGross = cart.reduce((a,b)=>a+getGrossMatPrice(b.price, selectedSupplier.secteur),0);
      const deliveryCostGross = deliveryMode === 'delivery' ? grossDeliveryFee : 0;
      return (
          <div className="bg-gray-50 min-h-screen pb-24">
              <div className="bg-green-800 text-white pt-12 pb-8 px-6 rounded-b-[2rem]"><button onClick={() => setSelectedSupplier(null)} className="mb-4 flex items-center gap-1 text-green-200"><Icon name="ChevronLeft"/> Retour</button><h1 className="text-2xl font-bold">{selectedSupplier.raisonSociale}</h1><p className="text-sm text-green-200">{selectedSupplier.secteur}</p></div>
              <div className="p-6">
                  <div className="space-y-3">
                      {selectedSupplier.catalog?.map(item => (
                          <div key={item.id} className="bg-white p-3 rounded-2xl shadow-sm flex items-center gap-3"><div className="flex-1"><p className="font-bold">{item.name}</p><p className="text-sm font-black text-green-600">{(getGrossMatPrice(item.price, selectedSupplier.secteur) || 0).toLocaleString()} F</p></div><button onClick={() => {setCart([...cart, item]); showNotification("Succès", "Article ajouté");}} className="w-10 h-10 bg-green-50 text-green-600 rounded-xl flex items-center justify-center" aria-label={`Ajouter ${item.name} au panier`}><Icon name="Plus"/></button></div>
                      ))}
                  </div>
              </div>
              {cart.length > 0 && (
                  <button onClick={() => setShowCart(true)} className="fixed bottom-6 right-6 bg-green-600 text-white p-4 rounded-full shadow-lg z-40">
                      <div className="relative"><Icon name="ShoppingCart"/><span className="absolute -top-2 -right-2 bg-red-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full">{cart.length}</span></div>
                  </button>
              )}
              {showCart && (
                  <div className="fixed inset-0 bg-black/60 z-[60] flex items-end justify-center p-4"><div className="bg-white w-full max-w-md rounded-3xl p-6"><Button className="w-full" variant="success" onClick={() => { setShowPayment({ type: 'order', total: subtotalGross + deliveryCostGross }); }}>Payer</Button></div></div>
              )}
          </div>
      );
  }

  return (
    <div className="bg-gray-50 min-h-screen pb-20 relative">
      {localNotif && (
          <div className="absolute top-4 left-4 right-4 bg-green-600 text-white p-4 rounded-xl shadow-2xl z-[100] animate-in slide-in-from-top-5 flex items-start gap-3">
              <Icon name="CheckCircle" size={24} className="shrink-0" />
              <div><p className="font-bold text-sm">{localNotif.title}</p><p className="text-xs text-green-100">{localNotif.text}</p></div>
              <button onClick={() => setLocalNotif(null)} className="ml-auto text-green-200 hover:text-white" aria-label="Fermer la notification"><Icon name="X" size={16}/></button>
          </div>
      )}

      {showPayment && <PaymentModal amount={showPayment.amount} paymentAmount={showPayment.amount} isMacro={showPayment.item?.jobType === 'macro'} title={showPayment.item?.jobType === 'macro' ? `Paiement du Jalon` : `Paiement Sécurisé`} onClose={()=>setShowPayment(null)} onSuccess={handleCheckoutSuccess}/>}
      
      {showDisputeModal && <DisputeModal targetInfo={showDisputeModal} onClose={()=>setShowDisputeModal(null)} onSubmit={(data)=>{
          if (showDisputeModal.type === 'job') onUpdateJob(showDisputeModal.id, { ...jobs.find(j=>j.id===showDisputeModal.id), status: 'disputed', disputeReason: data.reason });
          setShowDisputeModal(null); showNotification("Litige enregistré", "Les fonds sont gelés.");
      }} />}

      {tab === 'home' && (
          <div className="pt-12 px-6 pb-6 animate-in fade-in flex flex-col min-h-screen">
              <div className="flex justify-between items-center mb-6">
                  <div><p className="text-blue-600 text-sm font-bold">Bonjour, {user.prenoms}</p><h2 className="font-black text-2xl text-slate-800">Annuaire</h2></div>
              </div>

              {/* MISE EN AVANT DES MAITRES D'OEUVRE */}
              <div className="bg-indigo-900 text-white p-4 rounded-xl shadow-lg mb-6 flex items-start gap-3 cursor-pointer hover:bg-indigo-800 transition-colors" onClick={() => {setDirectoryType('artisan'); setArtisanCategoryFilter('renovation');}}>
                  <div className="w-10 h-10 bg-indigo-800 rounded-full flex items-center justify-center shrink-0"><Icon name="Briefcase" size={20} className="text-indigo-300"/></div>
                  <div>
                      <h3 className="font-bold text-sm text-indigo-50">Projet de Rénovation ?</h3>
                      <p className="text-[10px] text-indigo-200 mt-1">Trouvez un Maître d'Œuvre certifié pour coordonner toute une équipe d'artisans avec le Smart Escrow (Paiement par étapes).</p>
                  </div>
              </div>

              <div className="bg-gray-200 p-1 rounded-xl flex mb-4 shrink-0">
                  <button onClick={() => setDirectoryType('artisan')} className={`flex-1 py-2 text-sm font-bold rounded-lg transition-all ${directoryType === 'artisan' ? 'bg-white shadow text-blue-600' : 'text-gray-500'}`}>Artisans</button>
                  <button onClick={() => setDirectoryType('supplier')} className={`flex-1 py-2 text-sm font-bold rounded-lg transition-all ${directoryType === 'supplier' ? 'bg-white shadow text-green-600' : 'text-gray-500'}`}>Fournisseurs</button>
              </div>

              {directoryType === 'artisan' && (
                  <div className="mb-4 shrink-0"><select value={artisanCategoryFilter} onChange={e => setArtisanCategoryFilter(e.target.value)} className="w-full bg-white border border-gray-200 p-3 rounded-xl outline-none text-sm text-gray-700 font-medium shadow-sm" aria-label="Filtrer par catégorie"><option value="all">Toutes les catégories</option>{categories.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}</select></div>
              )}
              
              {directoryType === 'supplier' && !isNightMode && (
                  <div className="mb-4 shrink-0"><select value={supplierZoneFilter} onChange={e => setSupplierZoneFilter(e.target.value)} className="w-full bg-white border border-gray-200 p-3 rounded-xl outline-none text-sm text-gray-700 font-medium shadow-sm" aria-label="Filtrer par région / zone"><option value="all">Toutes les régions / zones</option>{uniqueZones.map(z => <option key={z} value={z}>{z}</option>)}</select></div>
              )}

              {viewMode === 'map' ? (
                  <InteractiveMockMap 
                      artisans={directoryType === 'artisan' ? filteredArtisans : []} 
                      suppliers={directoryType === 'supplier' && !isNightMode ? filteredSuppliers : []} 
                      filterCategory={artisanCategoryFilter} filterZone={supplierZoneFilter}
                      onSelectArtisan={setSelectedArtisan} onSelectSupplier={setSelectedSupplier}
                      clientLat={user.lat || 5.345} clientLng={user.lng || -4.024}
                  />
              ) : (
                  <div className="space-y-3 pb-8 flex-1 overflow-y-auto">
                      {directoryType === 'artisan' && (
                          filteredArtisans.length === 0 ? (
                              <p className="text-center text-gray-400 py-10 text-sm">Aucun artisan {isNightMode ? 'de garde ' : ''}trouvé pour cette catégorie.</p>
                          ) : (
                              filteredArtisans.map(a => (
                                  <div key={`client-artisan-${a.id}`} className={`bg-white p-4 rounded-2xl shadow-sm border flex gap-4 relative overflow-hidden ${a.isLeadContractor ? 'border-indigo-200' : 'border-gray-100'}`}>
                                     {isNightMode && <div className="absolute top-0 left-0 w-full h-1 bg-purple-500"></div>}
                                     {a.photo ? <img src={a.photo} className="w-16 h-16 rounded-xl object-cover" alt={`Photo de ${a.name} ${a.prenoms}`} /> : <div className="w-16 h-16 bg-blue-50 rounded-xl flex items-center justify-center text-blue-500 font-bold text-xl uppercase">{a.name[0]}{a.prenoms[0]}</div>}
                                     <div className="flex-1">
                                         <div className="flex justify-between items-center"><h3 className="font-bold text-gray-800">{a.name} {a.prenoms}</h3><div className="flex items-center text-yellow-500 text-xs font-bold"><Icon name="Star" size={12} fill="currentColor" className="mr-1"/>{a.rating}</div></div>
                                         <p className="text-xs text-blue-600 capitalize font-medium mb-1">{categories.find(c=>c.id === a.category)?.name || a.category}</p>
                                         {a.isLeadContractor && <p className="text-[9px] font-bold text-indigo-500 mb-2 uppercase">Maître d'Œuvre (B2B2C)</p>}
                                         <Button className="w-full py-2 mt-2 text-xs" iconName="Send" onClick={() => setSelectedArtisan(a)}>Contacter</Button>
                                     </div>
                                  </div>
                              ))
                          )
                      )}
                      
                      {directoryType === 'supplier' && (
                          isNightMode ? (
                              <div className="text-center p-6 bg-purple-50 rounded-2xl border border-purple-100 mt-2">
                                  <Icon name="Moon" className="mx-auto text-purple-400 mb-3" size={40}/>
                                  <h3 className="font-bold text-purple-900 mb-1 text-sm">Fournisseurs Fermés</h3>
                                  <p className="text-xs text-purple-700">En mode Urgence Nuit, les commandes directes de matériaux sont suspendues. Seuls les artisans disposant d'un stock d'urgence personnel sont affichés et mobilisables.</p>
                              </div>
                          ) : filteredSuppliers.length === 0 ? (
                              <p className="text-center text-gray-400 py-10 text-sm">Aucun fournisseur trouvé dans cette zone.</p>
                          ) : (
                              filteredSuppliers.map(s => (
                                  <div key={`client-sup-${s.id}`} className="bg-white p-4 rounded-2xl shadow-sm border flex gap-4 relative">
                                     {s.storefrontImage ? <img src={s.storefrontImage} className="w-16 h-16 rounded-xl object-cover" alt={s.raisonSociale || "Devanture du magasin"} /> : <div className="w-16 h-16 bg-green-50 rounded-xl flex items-center justify-center text-green-500 font-bold"><Icon name="Store" size={28}/></div>}
                                     <div className="flex-1">
                                         <h3 className="font-bold text-gray-800">{s.raisonSociale}</h3>
                                         <p className="text-xs text-green-600 font-medium mb-1">{s.secteur}</p>
                                         <Button variant="success" className="w-full py-2 mt-2 text-xs" iconName="Package" onClick={() => setSelectedSupplier(s)}>Catalogue</Button>
                                     </div>
                                  </div>
                              ))
                          )
                      )}
                  </div>
              )}
          </div>
      )}

      {tab === 'requests' && (
          <div className="pt-12 px-6 pb-6 animate-in fade-in">
              <h2 className="font-bold text-2xl text-slate-800 mb-6">Mon Suivi</h2>
              
              <div className="space-y-6">
                  {myRequests.map(job => {
                      const isMacro = job.jobType === 'macro';
                      const pendingMilestone = job.milestones?.find(m => m.status === 'pending');

                      return (
                          <div key={job.id} className={`bg-white p-5 rounded-2xl shadow-sm border ${isMacro ? 'border-indigo-200' : 'border-gray-100'}`}>
                              <div className="flex justify-between items-start mb-2">
                                  <div>
                                      <span className="font-bold text-sm text-gray-800 block">Pro: {job.artisanName}</span>
                                      {isMacro && <span className="text-[9px] font-bold text-indigo-600 uppercase">Projet Global</span>}
                                  </div>
                                  <Badge text={job.status} color="bg-blue-50 text-blue-700" />
                              </div>
                              <p className="text-sm text-gray-600 bg-gray-50 p-3 rounded-xl mb-4 italic">"{job.problem}"</p>
                              
                              {/* LOGIQUE DE PAIEMENT : MACRO PROJET */}
                              {isMacro && job.status === 'quote_provided' && (
                                  <div className="pt-4 border-t border-indigo-100">
                                      <div className="bg-indigo-50 p-3 rounded-lg border border-indigo-100 mb-4">
                                          <p className="text-xs text-indigo-800 font-bold mb-1">Budget Total Validé : {(job.escrow?.total_budget || 0).toLocaleString()} F</p>
                                          <p className="text-[10px] text-indigo-600">Le Maître d'Œuvre utilisera ce séquestre pour payer ses artisans.</p>
                                      </div>

                                      {/* Document de Devis du MOE */}
                                      {job.quoteDocument && (
                                          <div className="flex justify-between items-center bg-white p-2 rounded-lg mb-4 border border-gray-200">
                                              <span className="text-xs font-bold text-gray-800 flex items-center gap-2"><Icon name="FileText" size={14}/> Devis Détaillé (MOE)</span>
                                              <a href={job.quoteDocument} download={`Devis_${job.artisanName}_${job.id}`} target="_blank" rel="noreferrer" className="text-[10px] bg-gray-800 text-white px-3 py-1.5 rounded font-bold hover:bg-gray-900 transition-colors">Consulter</a>
                                          </div>
                                      )}

                                      {pendingMilestone && (
                                          <div className="bg-orange-50 p-4 rounded-xl border border-orange-200 mb-4">
                                              <p className="text-xs font-bold text-orange-900 mb-2">Appel de Fonds Actuel :</p>
                                              <div className="flex justify-between items-center border-b border-orange-200 pb-2 mb-2">
                                                  <span className="text-sm text-orange-800">{pendingMilestone.name}</span>
                                                  <span className="font-black text-orange-900">{pendingMilestone.amount.toLocaleString()} F</span>
                                              </div>
                                              <Button variant="success" className="w-full text-xs shadow-none mt-2" onClick={()=>setShowPayment({type: 'job', item: job, amount: pendingMilestone.amount})}>
                                                  Sécuriser ce Jalon
                                              </Button>
                                          </div>
                                      )}
                                      
                                      {/* Barre de progression des Jalons */}
                                      {job.jobType === 'macro' && job.status === 'quote_provided' && (
                                          <div className="space-y-2 mt-4">
                                              <p className="text-[10px] font-bold text-gray-500 uppercase">État des Jalons</p>
                                              {job.milestones.map((m, idx) => (
                                                  <div key={idx} className="flex justify-between items-center text-xs">
                                                      <span className={m.status === 'paid' ? 'text-green-600 font-bold' : 'text-gray-400'}>{m.name}</span>
                                                      <span className={m.status === 'paid' ? 'text-green-600' : 'text-gray-400'}>{(m.amount || 0).toLocaleString()} F</span>
                                                  </div>
                                              ))}
                                          </div>
                                      )}
                                  </div>
                              )}

                              {/* LOGIQUE DE PAIEMENT : STANDARD */}
                              {!isMacro && job.status === 'quote_provided' && (
                                  <div className="pt-4 border-t border-gray-100">
                                      <div className="bg-orange-50 p-3 rounded-lg border border-orange-100 mb-4 text-xs">
                                          <div className="flex justify-between mb-2"><span className="text-orange-800">Total:</span><span className="font-bold text-orange-900">{(getJobTotal(job) || 0).toLocaleString()} F</span></div>
                                      </div>
                                      <Button variant="success" className="w-full text-xs shadow-none" onClick={()=>setShowPayment({type: 'job', item: job, amount: getJobTotal(job)})}>Payer {getJobTotal(job).toLocaleString()} F</Button>
                                  </div>
                              )}

                              {job.status === 'work_done' && (
                                  <div className="pt-4 border-t border-gray-100">
                                      <div className="bg-green-50 p-4 rounded-xl border border-green-200 text-center mb-4">
                                          <Icon name="CheckCircle" className="mx-auto text-green-600 mb-2" size={32}/>
                                          <p className="text-xs font-bold text-green-900">Fin des travaux déclarée.</p>
                                      </div>
                                      <Button variant="success" className="w-full py-3 text-xs" onClick={()=>handleValidateWork(job)}>Valider les travaux (Décaisser)</Button>
                                  </div>
                              )}
                          </div>
                      );
                  })}
              </div>
          </div>
      )}

      {tab === 'finances' && (
          <div className="pt-12 px-6 pb-6 animate-in fade-in">
              <h2 className="font-bold text-2xl text-slate-800 mb-6">Mes Paiements</h2>

              <div className="space-y-6">
                  <div className="bg-gradient-to-br from-blue-600 to-indigo-800 rounded-2xl p-6 text-white shadow-lg relative overflow-hidden">
                      <Icon name="Wallet" className="absolute -bottom-4 -right-4 text-white/10" size={120}/>
                      <p className="text-white/80 text-xs font-bold uppercase mb-1 relative z-10">Total Engagé</p>
                      <h3 className="text-4xl font-bold mb-1 relative z-10">{(totalSpent || 0).toLocaleString()} <span className="text-xl">F</span></h3>
                      <p className="text-xs text-blue-100 relative z-10">Somme de tous vos paiements sur ProsArtisan.</p>
                  </div>

                  <div className="grid grid-cols-2 gap-4">
                      <div className="bg-white p-4 rounded-xl shadow-sm border border-blue-200">
                          <div className="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center text-blue-600 mb-2"><Icon name="Shield" size={16}/></div>
                          <p className="text-[10px] text-gray-500 font-bold uppercase mb-1">En Séquestre (Sécurisé)</p>
                          <p className="text-xl font-black text-blue-800">{(totalEscrow || 0).toLocaleString()} F</p>
                      </div>
                      <div className="bg-white p-4 rounded-xl shadow-sm border border-green-200">
                          <div className="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center text-green-600 mb-2"><Icon name="CheckCircle" size={16}/></div>
                          <p className="text-[10px] text-gray-500 font-bold uppercase mb-1">Payé aux Pros (Terminé)</p>
                          <p className="text-xl font-black text-green-800">{(totalDisbursed || 0).toLocaleString()} F</p>
                      </div>
                  </div>

                  <div>
                      <h3 className="font-bold text-sm text-slate-800 border-b pb-2 mb-4">Historique des Transactions</h3>
                      <div className="space-y-3">
                          {allTransactions.length === 0 ? <p className="text-center text-gray-400 py-6 text-xs">Aucune transaction.</p> : 
                          allTransactions.map((t) => (
                              <div key={t.id} className="bg-white p-3 rounded-xl border border-gray-100 flex justify-between items-center shadow-sm">
                                  <div className="flex gap-3 items-center">
                                      <div className={`w-10 h-10 rounded-lg flex items-center justify-center shrink-0 ${t.type === 'job' ? 'bg-blue-50 text-blue-600' : 'bg-green-50 text-green-600'}`}>
                                          <Icon name={t.type === 'job' ? 'Wrench' : 'Package'} size={18}/>
                                      </div>
                                      <div>
                                          <p className="text-xs font-bold text-gray-800 line-clamp-1">{t.title}</p>
                                          <p className="text-[10px] text-gray-500">{t.date}</p>
                                      </div>
                                  </div>
                                  <div className="text-right shrink-0">
                                      <p className="text-sm font-bold text-gray-900">{(t.amount || 0).toLocaleString()} F</p>
                                      <p className={`text-[9px] font-bold uppercase ${t.status === 'disputed' ? 'text-red-500' : t.isCompleted ? 'text-gray-400' : 'text-blue-500'}`}>
                                          {t.status === 'disputed' ? 'Gelé' : t.isCompleted ? 'Décaissé' : 'Séquestre'}
                                      </p>
                                  </div>
                              </div>
                          ))}
                      </div>
                  </div>
              </div>
          </div>
      )}

      {tab === 'profile' && <ProfileEditor user={user} onSave={(u)=>{onUpdateUser(u); showNotification("Profil à jour", "Vos informations ont été sauvegardées.");}} onLogout={onLogout} />}

      <div className="fixed bottom-0 w-full max-w-md bg-white border-t py-2 px-3 flex justify-between z-50">
          <button onClick={() => setTab('home')} className={`flex flex-col items-center gap-1 p-2 w-1/4 ${tab === 'home' ? 'text-blue-600' : 'text-gray-400'}`}>
              <Icon name="Home" size={20}/>
              <span className="text-[9px] font-bold">Accueil</span>
          </button>
          <button onClick={() => setTab('requests')} className={`flex flex-col items-center gap-1 p-2 w-1/4 ${tab === 'requests' ? 'text-blue-600' : 'text-gray-400'}`}>
              <Icon name="ClipboardList" size={20}/>
              <span className="text-[9px] font-bold">Suivi</span>
          </button>
          <button onClick={() => setTab('finances')} className={`flex flex-col items-center gap-1 p-2 w-1/4 ${tab === 'finances' ? 'text-blue-600' : 'text-gray-400'}`}>
              <Icon name="Wallet" size={20}/>
              <span className="text-[9px] font-bold">Paiements</span>
          </button>
          <button onClick={() => setTab('profile')} className={`flex flex-col items-center gap-1 p-2 w-1/4 ${tab === 'profile' ? 'text-blue-600' : 'text-gray-400'}`}>
              <Icon name="User" size={20}/>
              <span className="text-[9px] font-bold">Profil</span>
          </button>
      </div>
    </div>
  );
};

// 4. ESPACE ARTISAN / MAÎTRE D'ŒUVRE
const ArtisanDashboard = ({ artisan, jobs, onUpdateJob, orders, onUpdateOrder, suppliers, onLogout, platformFees, onNotify, onUpdateUser, isNightMode, artisans, tenders, onUpdateTenders }) => {
    const [tab, setTab] = useState('tracking');
    const [trackingFilter, setTrackingFilter] = useState('active'); 
    
    const [quoteBuilderJobId, setQuoteBuilderJobId] = useState(null);
    const [macroQuoteDraft, setMacroQuoteDraft] = useState({ totalBudget: '', quoteDocument: null, milestones: [{name: 'Démarrage (30%)', percent: 30}, {name: 'Gros oeuvre (40%)', percent: 40}, {name: 'Finitions (30%)', percent: 30}] });
    
    // Nouveaux états pour le système d'appels d'offres (Option A)
    const [activeTenderModalJob, setActiveTenderModalJob] = useState(null);
    const [activeBidTender, setActiveBidTender] = useState(null);
    const [awardModalData, setAwardModalData] = useState(null);
    
    const [localNotif, setLocalNotif] = useState(null);

    const showNotification = (title, text) => {
        setLocalNotif({ title, text });
        setTimeout(() => setLocalNotif(null), 4000);
    };

    const myJobs = jobs.filter(j => j.artisanId === artisan.id);
    const newJobs = myJobs.filter(j => j.status === 'sent');
    const activeJobs = myJobs.filter(j => ['quote_provided', 'funded', 'materials_picked_up', 'work_done'].includes(j.status));

    const artisanReviews = myJobs.filter(j => j.rating).map(j => ({ rating: j.rating, comment: j.review, date: j.date, clientName: j.clientName }));
    const avgRating = artisanReviews.length > 0 ? (artisanReviews.reduce((sum, r) => sum + r.rating, 0) / artisanReviews.length).toFixed(1) : (artisan.rating || 5.0);

    const stdEarned = myJobs.filter(j => j.jobType !== 'macro' && j.status === 'completed').reduce((acc, j) => acc + (j.financials?.laborCost || 0), 0);
    const subEarned = jobs.flatMap(j => j.subJobs || []).filter(sub => sub.artisanId === artisan.id && sub.payment_status === 'disbursed').reduce((acc, sub) => acc + sub.amount, 0);
    const disbursedAmount = stdEarned + subEarned;

    const escrowAmount = activeJobs.filter(j => j.jobType === 'macro').reduce((acc, j) => acc + (j.escrow?.currently_escrowed || 0) - (j.escrow?.disbursed_to_subs || 0), 0);

    const submitMacroQuote = (job) => {
        const total = parseInt(macroQuoteDraft.totalBudget);
        if (!total || total < 100000) {
            showNotification("Erreur", "Veuillez saisir un budget global valide.");
            return;
        }

        if (!macroQuoteDraft.quoteDocument) {
            showNotification("Erreur", "Le devis détaillé (PDF/Image) est obligatoire pour un Macro-Projet.");
            return;
        }
        
        const platformFee = Math.round(total * 0.1); 
        const generatedMilestones = macroQuoteDraft.milestones.map((m, idx) => ({
            id: idx + 1,
            name: m.name,
            amount: Math.round(total * (m.percent / 100)),
            status: 'pending'
        }));

        onUpdateJob(job.id, {
            ...job,
            status: 'quote_provided',
            quoteDocument: macroQuoteDraft.quoteDocument,
            escrow: {
                total_budget: total,
                currently_escrowed: 0,
                disbursed_to_subs: 0
            },
            financials: { ...job.financials, platformFeesBreakdown: { labor: platformFee, material: 0, delivery: 0 } },
            milestones: generatedMilestones
        });
        showNotification("Devis Global Envoyé", "Le client a reçu votre proposition et la demande du Jalon 1.");
        setQuoteBuilderJobId(null);
    };

    const handleMacroDocChange = (e) => {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onloadend = () => setMacroQuoteDraft(prev => ({...prev, quoteDocument: reader.result}));
            reader.readAsDataURL(file);
        }
    };

    return (
        <div className="min-h-screen bg-slate-50 pb-24 relative">
            {localNotif && (
                <div className="absolute top-4 left-4 right-4 bg-green-600 text-white p-4 rounded-xl shadow-2xl z-[100] animate-in slide-in-from-top-5 flex items-start gap-3">
                    <Icon name={localNotif.title === "Erreur" ? "AlertTriangle" : "CheckCircle"} size={24} className="shrink-0" />
                    <div><p className="font-bold text-sm">{localNotif.title}</p><p className="text-xs text-green-100">{localNotif.text}</p></div>
                    <button onClick={() => setLocalNotif(null)} className="ml-auto text-green-200 hover:text-white" aria-label="Fermer la notification" title="Fermer"><Icon name="X" size={16}/></button>
                </div>
            )}

            {/* MODALES B2B */}
            {activeTenderModalJob && (
                <CreateTenderModal 
                    availableBudget={(activeTenderModalJob.escrow?.currently_escrowed || 0) - (activeTenderModalJob.escrow?.disbursed_to_subs || 0)}
                    onClose={() => setActiveTenderModalJob(null)} 
                    onSubmit={(tender) => {
                        onUpdateTenders([...tenders, { ...tender, jobId: activeTenderModalJob.id }]);
                        showNotification("Lot publié", "Les artisans peuvent maintenant soumissionner.");
                        setActiveTenderModalJob(null);
                    }}
                />
            )}

            {activeBidTender && (
                <SubmitBidModal 
                    tender={activeBidTender} 
                    artisan={artisan} 
                    onClose={() => setActiveBidTender(null)} 
                    onSubmit={(bid) => {
                        const updatedTenders = tenders.map(t => t.id === activeBidTender.id ? { ...t, bids: [...t.bids, bid] } : t);
                        onUpdateTenders(updatedTenders);
                        showNotification("Devis envoyé", "Votre proposition a été transmise au Maître d'Œuvre.");
                        setActiveBidTender(null);
                    }}
                />
            )}

            {awardModalData && (
                <AwardBidModal 
                    tender={awardModalData.tender} 
                    bid={awardModalData.bid} 
                    artisanName={awardModalData.bid.artisanName}
                    onClose={() => setAwardModalData(null)} 
                    onSubmit={(milestones) => {
                        const updatedTenders = tenders.map(t => t.id === awardModalData.tender.id ? { ...t, status: 'awarded', winningBid: { ...awardModalData.bid, milestones } } : t);
                        onUpdateTenders(updatedTenders);
                        
                        // Ajout de l'artisan dans les subJobs pour déclencher les paiements par le MOE
                        const newSubJob = { id: generateUUID(), artisanId: awardModalData.bid.artisanId, artisanName: awardModalData.bid.artisanName, task: awardModalData.tender.title, amount: awardModalData.bid.amount, status: 'in_progress', payment_status: 'pending' };
                        onUpdateJob(awardModalData.job.id, { subJobs: [...(awardModalData.job.subJobs || []), newSubJob] });
                        
                        showNotification("Contrat validé", `${awardModalData.bid.artisanName} est engagé !`);
                        setAwardModalData(null);
                    }}
                />
            )}

            <div className="pt-12 pb-8 px-6 rounded-b-[2rem] text-white flex justify-between items-center shadow-lg bg-slate-900">
                <div>
                    <p className="text-xs uppercase font-bold tracking-widest mb-1 text-white/70">{artisan.isLeadContractor ? "Espace Maître d'Œuvre" : "Espace Pro"}</p>
                    <h1 className="text-2xl font-bold flex items-center gap-2">{artisan.prenoms} {artisan.nom}</h1>
                </div>
                <Button variant="ghost" onClick={onLogout} className="p-2 text-white hover:text-red-400"><Icon name="LogOut"/></Button>
            </div>
            
            <div className="px-4 mt-6">
                {tab === 'wallet' && (
                    <div className="space-y-6 animate-in slide-in-from-bottom-5">
                        <div className="bg-gradient-to-br from-green-600 to-emerald-800 rounded-2xl p-6 text-white shadow-lg relative overflow-hidden">
                            <p className="text-white/80 text-xs font-bold uppercase mb-1 relative z-10">Fonds Décaissés (Retirables)</p>
                            <h3 className="text-4xl font-bold mb-4 relative z-10">{disbursedAmount.toLocaleString()} <span className="text-xl">F</span></h3>
                            
                            <div className="space-y-1 mb-4 text-xs text-green-100 relative z-10">
                                <div className="flex justify-between"><span>Prestations Directes :</span><span>{stdEarned.toLocaleString()} F</span></div>
                                <div className="flex justify-between font-bold"><span>Missions Sous-traitance :</span><span>{subEarned.toLocaleString()} F</span></div>
                            </div>
                            <Button className="w-full bg-white text-green-800 hover:bg-gray-100 shadow-none relative z-10" iconName="Wallet">Demander un Virement</Button>
                        </div>

                        {artisan.isLeadContractor && (
                            <div className="bg-indigo-50 p-5 rounded-2xl border border-indigo-200 text-center">
                                <h3 className="font-bold text-indigo-900 text-sm uppercase mb-1">Séquestre d'Équipe (Actif)</h3>
                                <p className="text-3xl font-black text-indigo-800 mb-3">{escrowAmount.toLocaleString()} F</p>
                                <p className="text-xs text-indigo-700 bg-white/50 p-3 rounded-lg border border-indigo-100">C'est la trésorerie disponible sur le séquestre client que vous pouvez actuellement redistribuer à votre équipe de sous-traitants.</p>
                            </div>
                        )}
                    </div>
                )}

                {tab === 'tracking' && (
                    <div className="space-y-4 animate-in slide-in-from-bottom-5">
                        {/* VUE MAÎTRE D'ŒUVRE (MOE) */}
                        {artisan.isLeadContractor ? (
                            <>
                                <div className="flex bg-gray-200 p-1 rounded-xl mb-6">
                                    <button onClick={() => setTrackingFilter('new')} className={`flex-1 py-2 text-xs font-bold rounded-lg transition-all ${trackingFilter === 'new' ? 'bg-white shadow text-blue-600' : 'text-gray-500'}`}>Nouveaux</button>
                                    <button onClick={() => setTrackingFilter('active')} className={`flex-1 py-2 text-xs font-bold rounded-lg transition-all ${trackingFilter === 'active' ? 'bg-white shadow text-blue-600' : 'text-gray-500'}`}>En cours</button>
                                </div>

                                {trackingFilter === 'new' && newJobs.map(job => (
                                    <div key={`new-job-${job.id}`} className={`bg-white p-4 rounded-xl shadow-sm border mb-3 ${job.jobType === 'macro' ? 'border-indigo-200' : 'border-gray-100'}`}>
                                        <div className="flex justify-between items-start mb-2">
                                            <div>
                                                <h3 className="font-bold text-sm text-gray-800">{job.clientName}</h3>
                                                {job.jobType === 'macro' && <span className="text-[9px] font-bold text-indigo-600 uppercase flex items-center gap-1 mt-1"><Icon name="Briefcase" size={10}/> Appel d'offres Global</span>}
                                            </div>
                                            <Badge text="À Deviser" color="bg-red-100 text-red-700"/>
                                        </div>
                                        <p className="text-xs text-gray-600 bg-gray-50 p-3 rounded-lg mb-4 italic">"{job.problem}"</p>

                                        {job.planDocument && (
                                            <div className="bg-blue-50 p-3 rounded-xl mb-4 border border-blue-100 flex justify-between items-center shadow-sm">
                                                <div className="flex items-center gap-3 text-blue-900">
                                                    <div className="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center shrink-0">
                                                        <Icon name="FileText" size={16} className="text-blue-600"/>
                                                    </div>
                                                    <div>
                                                        <p className="text-xs font-bold leading-tight">Base de travail (Client)</p>
                                                        <p className="text-[9px] text-blue-600 mt-0.5">Plan, croquis ou document</p>
                                                    </div>
                                                </div>
                                                <a 
                                                    href={job.planDocument} 
                                                    target="_blank" 
                                                    rel="noreferrer" 
                                                    download={`Plan_Client_${job.clientId}`}
                                                    className="text-[10px] bg-blue-600 text-white px-4 py-2 rounded-lg font-bold shadow-md hover:bg-blue-700 transition-colors shrink-0"
                                                >
                                                    Consulter
                                                </a>
                                            </div>
                                        )}
                                        
                                        {quoteBuilderJobId === job.id ? (
                                            <div className="p-4 rounded-xl border bg-indigo-50 border-indigo-200 space-y-4">
                                                <h4 className="font-bold text-indigo-900 text-sm">Définir le Budget et les Jalons</h4>
                                                <p className="text-xs text-indigo-700 mb-2">Saisissez le budget total et chargez votre devis quantitatif (PDF).</p>
                                                
                                                <InputGroup label="Budget Global (FCFA)">
                                                    <input type="number" className="w-full p-3 text-lg font-bold text-center rounded-xl border outline-none border-indigo-200" value={macroQuoteDraft.totalBudget} onChange={e=>setMacroQuoteDraft({...macroQuoteDraft, totalBudget: e.target.value})} placeholder="Ex: 5000000" aria-label="Budget global estimé" />
                                                </InputGroup>

                                                <ImageUploadField 
                                                    label="Devis Quantitatif et Estimatif" 
                                                    image={macroQuoteDraft.quoteDocument} 
                                                    onChange={handleMacroDocChange} 
                                                    iconName="FileText"
                                                    hint="Joignez le document détaillé (PDF ou Image) de votre proposition."
                                                />

                                                {macroQuoteDraft.totalBudget && (
                                                    <div className="bg-white p-3 rounded-lg text-xs space-y-2 border border-indigo-100">
                                                        <p className="text-[10px] text-gray-500 font-bold uppercase border-b pb-1 mb-2">Échéancier Auto (Jalons)</p>
                                                        <div className="flex justify-between text-gray-600"><span>1. Démarrage (30%)</span><span className="font-bold text-indigo-600">{Math.round(parseInt(macroQuoteDraft.totalBudget)*0.3).toLocaleString()} F</span></div>
                                                        <div className="flex justify-between text-gray-600"><span>2. Gros oeuvre (40%)</span><span className="font-bold text-indigo-600">{Math.round(parseInt(macroQuoteDraft.totalBudget)*0.4).toLocaleString()} F</span></div>
                                                        <div className="flex justify-between text-gray-600"><span>3. Finitions (30%)</span><span className="font-bold text-indigo-600">{Math.round(parseInt(macroQuoteDraft.totalBudget)*0.3).toLocaleString()} F</span></div>
                                                    </div>
                                                )}

                                                <div className="flex gap-2">
                                                    <Button variant="secondary" onClick={()=>setQuoteBuilderJobId(null)} className="flex-1 text-xs">Annuler</Button>
                                                    <Button onClick={()=>submitMacroQuote(job)} className="flex-1 text-xs bg-indigo-600 hover:bg-indigo-700 text-white">Soumettre au client</Button>
                                                </div>
                                            </div>
                                        ) : (
                                            <Button className="w-full py-2 text-xs bg-indigo-600 text-white" onClick={() => setQuoteBuilderJobId(job.id)}>Faire une proposition</Button>
                                        )}
                                    </div>
                                ))}

                                {trackingFilter === 'active' && activeJobs.map(job => {
                                    const jobTenders = tenders.filter(t => t.jobId === job.id);
                                    
                                    return (
                                    <div key={`act-job-${job.id}`} className={`bg-white p-5 rounded-2xl shadow-sm border mb-4 ${job.jobType === 'macro' ? 'border-indigo-200' : 'border-blue-100'}`}>
                                        <div className="flex justify-between items-start mb-4">
                                            <div>
                                                <h3 className="font-bold text-sm text-gray-800">Chantier: {job.clientName}</h3>
                                                {job.jobType === 'macro' && <span className="text-[9px] font-bold text-indigo-600 uppercase flex items-center gap-1 mt-1"><Icon name="Briefcase" size={10}/> Projet Global</span>}
                                            </div>
                                            <Badge text={job.status} color="bg-blue-50 text-blue-700" />
                                        </div>

                                        {job.planDocument && (
                                            <div className="bg-gray-50 p-2 rounded-lg mb-4 border border-gray-200 flex justify-between items-center">
                                                <span className="text-xs font-bold text-gray-700 flex items-center gap-2"><Icon name="FileText" size={14}/> Plan initial (Client)</span>
                                                <a href={job.planDocument} download={`Plan_${job.clientName}_${job.id}`} target="_blank" rel="noreferrer" className="text-[10px] bg-gray-800 text-white px-3 py-1.5 rounded font-bold hover:bg-gray-900 transition-colors shadow-sm">Revoir</a>
                                            </div>
                                        )}
                                        
                                        {job.jobType === 'macro' && job.status === 'funded' && (
                                            <div className="mt-2 pt-4 border-t border-indigo-100">
                                                <div className="bg-indigo-900 text-white p-4 rounded-xl text-center shadow-inner mb-4">
                                                    <p className="text-xs text-indigo-300 font-bold uppercase mb-1">Trésorerie Actuelle (Séquestre)</p>
                                                    <p className="text-2xl font-black">{((job.escrow?.currently_escrowed || 0) - (job.escrow?.disbursed_to_subs || 0)).toLocaleString()} F</p>
                                                </div>

                                                <div className="flex justify-between items-center mb-3">
                                                    <h4 className="font-bold text-sm text-indigo-900 flex items-center gap-2"><Icon name="Users" size={16}/> Sous-traitance (Lots)</h4>
                                                    <button onClick={() => setActiveTenderModalJob(job)} className="text-[10px] font-bold text-indigo-600 bg-indigo-50 px-3 py-1.5 rounded-lg hover:bg-indigo-100 transition-colors">+ Publier Appel d'Offres</button>
                                                </div>
                                                
                                                {jobTenders.length === 0 ? (
                                                    <p className="text-[10px] text-gray-500 italic bg-gray-50 p-3 rounded-lg border border-gray-100">Aucun lot publié. Recrutez des artisans en publiant un appel d'offres.</p>
                                                ) : (
                                                    <div className="space-y-3">
                                                        {jobTenders.map(t => (
                                                            <div key={t.id} className="bg-white border border-indigo-100 p-3 rounded-xl shadow-sm">
                                                                <div className="flex justify-between items-center mb-2">
                                                                    <span className="font-bold text-xs text-indigo-900">{t.title}</span>
                                                                    <Badge text={t.status === 'open' ? `${t.bids.length} Devis` : 'Attribué'} type={t.status==='open'?'info':'success'}/>
                                                                </div>
                                                                {t.status === 'open' && t.bids.length > 0 && (
                                                                    <div className="space-y-2 mt-2 pt-2 border-t border-indigo-50">
                                                                        {t.bids.map((b,i)=>(
                                                                            <div key={i} className="flex justify-between bg-indigo-50/50 p-2 rounded items-center border border-indigo-50">
                                                                                <span className="text-[10px] font-bold text-slate-700">{b.artisanName} : <span className="text-indigo-700">{b.amount.toLocaleString()} F</span></span>
                                                                                <button onClick={()=>setAwardModalData({tender: t, bid: b, job: job})} className="text-[10px] bg-indigo-600 text-white px-3 py-1 rounded shadow-sm">Choisir</button>
                                                                            </div>
                                                                        ))}
                                                                    </div>
                                                                )}
                                                                {t.status === 'awarded' && (
                                                                    <div className="mt-2 pt-2 border-t border-emerald-50">
                                                                        <p className="text-[10px] text-emerald-700 font-bold flex items-center gap-1"><Icon name="CheckCircle" size={12}/> Géré par {t.winningBid.artisanName} ({t.winningBid.amount.toLocaleString()} F)</p>
                                                                    </div>
                                                                )}
                                                            </div>
                                                        ))}
                                                    </div>
                                                )}

                                                {/* Affichage des sous-traitants en cours (pour le paiement bottom-up) */}
                                                {job.subJobs && job.subJobs.length > 0 && (
                                                    <div className="mt-4 space-y-2">
                                                        <h4 className="text-[10px] font-bold uppercase text-slate-500 mb-2">Décaissements en attente</h4>
                                                        {job.subJobs.map((sub, idx) => (
                                                            <div key={`sub-${idx}`} className="bg-white border border-gray-200 p-3 rounded-xl flex justify-between items-center shadow-sm">
                                                                <div>
                                                                    <p className="text-xs font-bold text-gray-800">{sub.artisanName}</p>
                                                                    <p className="text-[10px] text-gray-500 bg-gray-50 px-2 py-1 rounded mt-1 inline-block">Lot : {sub.task}</p>
                                                                </div>
                                                                <div className="text-right">
                                                                    <p className="text-xs font-bold text-indigo-600 mb-1">{sub.amount.toLocaleString()} F</p>
                                                                    {sub.payment_status === 'pending' ? (
                                                                        <button onClick={() => {
                                                                            const updatedSubJobs = job.subJobs.map(s => s.id === sub.id ? {...s, status: 'completed', payment_status: 'disbursed'} : s);
                                                                            onUpdateJob(job.id, { subJobs: updatedSubJobs, escrow: { ...job.escrow, disbursed_to_subs: job.escrow.disbursed_to_subs + sub.amount } });
                                                                            showNotification("Sous-traitant Payé", `Les ${sub.amount.toLocaleString()} F ont été libérés pour ${sub.artisanName}.`);
                                                                        }} className="text-[9px] bg-green-100 text-green-700 px-3 py-1 rounded-md font-bold hover:bg-green-200 transition-colors">Décaisser</button>
                                                                    ) : (
                                                                        <Badge text="Décaissé" color="bg-gray-100 text-gray-500" />
                                                                    )}
                                                                </div>
                                                            </div>
                                                        ))}
                                                    </div>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                )})}
                            </>
                        ) : (
                            /* VUE SOUS-TRAITANT (Artisan) */
                            <>
                                <section>
                                    <h3 className="font-bold text-slate-900 text-lg mb-4 flex items-center gap-2"><Icon name="Briefcase" size={20}/> Appels d'Offres</h3>
                                    {tenders.filter(t => t.status === 'open' && !t.bids.some(b => b.artisanId === artisan.id)).length === 0 ? <p className="text-sm text-slate-400 italic">Aucune opportunité dans votre domaine.</p> : 
                                        tenders.filter(t => t.status === 'open' && !t.bids.some(b => b.artisanId === artisan.id)).map(tender => (
                                            <div key={tender.id} className="bg-white p-4 rounded-xl shadow-sm border border-slate-100 mb-4">
                                                <div className="flex justify-between items-start mb-2">
                                                    <h4 className="font-bold text-slate-800">{tender.title}</h4>
                                                    <Badge text="Nouveau" type="success" />
                                                </div>
                                                <p className="text-xs text-slate-500 mb-4">{tender.description}</p>
                                                
                                                {tender.documentUrl && (
                                                    <div className="bg-blue-50 p-3 rounded-xl mb-4 border border-blue-100 flex items-center justify-between">
                                                        <div className="flex items-center gap-2 text-blue-700">
                                                            <Icon name="FileText" size={16} />
                                                            <span className="text-xs font-bold">Document Technique</span>
                                                        </div>
                                                        <a href={tender.documentUrl} target="_blank" rel="noreferrer" className="text-[10px] bg-blue-600 text-white px-3 py-1.5 rounded font-bold">Consulter</a>
                                                    </div>
                                                )}

                                                <Button variant="primary" className="py-3 text-xs w-full shadow-none bg-slate-900" onClick={()=>setActiveBidTender(tender)}>Proposer un devis</Button>
                                            </div>
                                        ))
                                    }
                                </section>
                                <section className="mt-6 border-t pt-6">
                                    <h3 className="font-bold text-slate-900 text-lg mb-4 flex items-center gap-2"><Icon name="Clock" size={20}/> Mes Soumissions</h3>
                                    {tenders.filter(t => t.bids.some(b => b.artisanId === artisan.id) || t.winningBid?.artisanId === artisan.id).length === 0 ? <p className="text-sm text-slate-400 italic">Historique vide.</p> : 
                                        tenders.filter(t => t.bids.some(b => b.artisanId === artisan.id) || t.winningBid?.artisanId === artisan.id).map(tender => {
                                            const myBid = tender.bids.find(b => b.artisanId === artisan.id) || tender.winningBid;
                                            const isWinner = tender.winningBid?.artisanId === artisan.id;
                                            const isLost = tender.status === 'awarded' && !isWinner;

                                            return (
                                                <div key={tender.id} className={`bg-white p-4 rounded-xl shadow-sm border mb-3 ${isWinner ? 'border-emerald-200 bg-emerald-50/30' : 'border-slate-100'}`}>
                                                    <div className="flex justify-between items-start mb-2">
                                                        <h4 className="font-bold text-slate-800 text-sm">{tender.title}</h4>
                                                        <span className="text-xs font-black text-slate-900">{myBid.amount.toLocaleString()} F</span>
                                                    </div>
                                                    <div className="mt-3 pt-3 border-t border-slate-100 flex justify-between items-center">
                                                        <span className="text-[10px] text-slate-500 font-bold uppercase">Statut :</span>
                                                        {tender.status === 'open' && <Badge text="En analyse par le MOE" type="warning" />}
                                                        {isWinner && <Badge text="Offre Retenue !" type="success" />}
                                                        {isLost && <span className="text-xs font-bold text-red-500 bg-red-50 px-2 py-1 rounded-md">Non retenue</span>}
                                                    </div>
                                                </div>
                                            );
                                        })
                                    }
                                </section>
                            </>
                        )}
                    </div>
                )}

                {tab === 'profile' && <ProfileEditor user={artisan} onSave={(u)=>{onUpdateUser(u); showNotification("Profil à jour", "Vos informations ont été sauvegardées.");}} onLogout={onLogout} reviews={artisanReviews} avgRating={avgRating} />}
            </div>
            
            <div className="fixed bottom-0 w-full max-w-md bg-white border-t py-2 px-1 flex justify-between z-50">
                <button onClick={() => setTab('tracking')} className={`flex flex-col items-center gap-1 p-2 w-1/3 ${tab === 'tracking' ? 'text-indigo-600' : 'text-gray-400'}`}>
                    <Icon name="ClipboardList" size={20}/>
                    <span className="text-[9px] font-bold">Chantiers</span>
                </button>
                <button onClick={() => setTab('wallet')} className={`flex flex-col items-center gap-1 p-2 w-1/3 ${tab === 'wallet' ? 'text-indigo-600' : 'text-gray-400'}`}>
                    <Icon name="Wallet" size={20}/>
                    <span className="text-[9px] font-bold">Portefeuille</span>
                </button>
                <button onClick={() => setTab('profile')} className={`flex flex-col items-center gap-1 p-2 w-1/3 ${tab === 'profile' ? 'text-indigo-600' : 'text-gray-400'}`}>
                    <Icon name="User" size={20}/>
                    <span className="text-[9px] font-bold">Profil</span>
                </button>
            </div>
        </div>
    );
};

// --- APP PRINCIPALE ---
export default function App() {
  const [screen, setScreen] = useState('login');
  const [user, setUser] = useState(null);

  // Night Mode Toggle (pour les devs)
  const [simulateNight, setSimulateNight] = useState(false);
  const currentHour = new Date().getHours();
  const isNightMode = simulateNight || (currentHour >= 18 || currentHour < 7);

  const [categories, setCategories] = useState(INITIAL_CATEGORIES);
  const [supplierSectors, setSupplierSectors] = useState(INITIAL_SUPPLIER_SECTORS);
  const [jobs, setJobs] = useState(MOCK_DB.jobs);
  const [orders, setOrders] = useState(MOCK_DB.orders);
  const [artisans, setArtisans] = useState(MOCK_DB.artisans);
  const [suppliers, setSuppliers] = useState(MOCK_DB.suppliers);
  const [tenders, setTenders] = useState([]); // Ajout de l'état pour les appels d'offres B2B
  
  const [platformFees, setPlatformFees] = useState({ labor: 10, material: 3, delivery: 5, artisanCategories: { 'plomberie': 5, 'electricite': 7 }, supplierSectors: { 'Quincaillerie': 8 }, nightSurgeMultiplier: 1.5 });

  const DEMO_USERS = {
    'admin': { id: 0, nom: "Admin", prenoms: "Super", role: 'admin', telephone: "admin", password: "admin" },
    '0707070707': { id: 999, nom: "Amon", prenoms: "Paul", role: 'client', telephone: "0707070707", password: "123", adresseManuelle: "Cocody", lat: 5.345, lng: -4.024 },
    '0505050501': artisans[0], // MOE
    '0505050504': artisans[2], // Electricien
    '0707070701': suppliers[0], 
  };

  const handleLogin = (id, pwd) => {
    const found = [ ...artisans, ...suppliers, DEMO_USERS['admin'], DEMO_USERS['0707070707'] ].find(u => (u.telephone === id || u.contactMobile === id || u.id === id) && u.password === pwd);
    if (found) { setUser(found); setScreen('dashboard'); } // Erreurs gérées par le composant LoginScreen
    return found;
  };

  const handleUpdateUser = (updatedUser) => {
      setUser(updatedUser);
      if (updatedUser.role === 'supplier') { setSuppliers(suppliers.map(s => s.id === updatedUser.id ? updatedUser : s)); } 
      else if (updatedUser.role === 'artisan' || updatedUser.role === 'driver') { setArtisans(artisans.map(a => a.id === updatedUser.id ? updatedUser : a)); }
  };

  return (
    <div className="mx-auto max-w-md min-h-screen bg-gray-50 shadow-2xl relative overflow-hidden font-sans text-gray-900">
      
      {screen === 'dashboard' && user && user.role !== 'admin' && (
          <div className="absolute top-2 left-1/2 transform -translate-x-1/2 z-[200] flex gap-2">
              <button onClick={() => setSimulateNight(!simulateNight)} className={`text-[10px] px-2 py-1 rounded-full font-bold shadow-md ${simulateNight ? 'bg-yellow-400 text-yellow-900' : 'bg-slate-800 text-white'}`}>
                  {simulateNight ? 'Désactiver Nuit' : 'Test Mode Nuit'}
              </button>
          </div>
      )}

      {screen === 'login' && <LoginScreen onLogin={(id, pwd) => { const found = handleLogin(id, pwd); if(!found) return "Identifiants incorrects."; }} onGoToRegister={() => setScreen('role_selection')} />}
      {screen === 'role_selection' && <RoleSelectionScreen onSelectRole={(r) => setScreen(`register_${r}`)} onBack={() => setScreen('login')} />}
      {screen.startsWith('register_') && <RegistrationFlow initialRole={screen.split('_')[1]} onRegister={(u) => {setUser(u); setScreen('dashboard');}} onBack={() => setScreen('role_selection')} categories={categories} supplierSectors={supplierSectors}/>}

      {screen === 'dashboard' && user && (
        <>
          {user.role === 'admin' && <AdminDashboard user={user} onLogout={()=>setScreen('login')} categories={categories} setCategories={setCategories} supplierSectors={supplierSectors} setSupplierSectors={setSupplierSectors} platformFees={platformFees} setPlatformFees={setPlatformFees} jobs={jobs} orders={orders} />}
          {user.role === 'client' && <ClientDashboard user={user} onLogout={()=>setScreen('login')} categories={categories} artisans={artisans} suppliers={suppliers} jobs={jobs} orders={orders} onAddJob={(j)=>setJobs([j, ...jobs])} onAddOrder={(o)=>setOrders([o, ...orders])} platformFees={platformFees} onNotify={()=>console.log("Notified")} onUpdateJob={(id, d)=>setJobs(jobs.map(j=>j.id===id?{...j,...d}:j))} onUpdateOrder={(id, d)=>setOrders(orders.map(o=>o.id===id?{...o,...d}:o))} onUpdateUser={handleUpdateUser} isNightMode={isNightMode} />}
          {(user.role === 'artisan' || user.role === 'driver') && <ArtisanDashboard artisan={user} artisans={artisans} jobs={jobs} orders={orders} tenders={tenders} onUpdateTenders={setTenders} onUpdateJob={(id, d)=>setJobs(jobs.map(j=>j.id===id?{...j,...d}:j))} onUpdateOrder={(id, d)=>setOrders(orders.map(o=>o.id===id?{...o,...d}:o))} onLogout={()=>setScreen('login')} platformFees={platformFees} suppliers={suppliers} onNotify={()=>console.log("Notified")} onUpdateUser={handleUpdateUser} isNightMode={isNightMode} />}
          {user.role === 'supplier' && <SupplierDashboard supplier={user} jobs={jobs} orders={orders} onUpdateJob={(id, d)=>setJobs(jobs.map(j=>j.id===id?{...j,...d}:j))} onUpdateOrder={(id, d)=>setOrders(orders.map(o=>o.id===id?{...o,...d}:o))} onUpdateUser={handleUpdateUser} onNotify={()=>console.log("Notified")} onLogout={()=>setScreen('login')} platformFees={platformFees} />}
        </>
      )}
    </div>
  );
}
