'use client';

import { useEffect, useState, useMemo } from 'react';
import { api } from '@/lib/api';

interface Product {
    id: number;
    name: string;
    sku?: string;
    description?: string;
    unit_price: number;
    stock_quantity: number;
    image_url?: string;
    is_active: boolean;
}

export default function SupplierCatalog() {
    const [products, setProducts] = useState<Product[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [searchQuery, setSearchQuery] = useState('');

    // Modals state
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [editingProduct, setEditingProduct] = useState<Product | null>(null);
    const [formLoading, setFormLoading] = useState(false);
    const [uploading, setUploading] = useState(false);

    // Form inputs state
    const [name, setName] = useState('');
    const [sku, setSku] = useState('');
    const [description, setDescription] = useState('');
    const [unitPrice, setUnitPrice] = useState<number>(0);
    const [stockQuantity, setStockQuantity] = useState<number>(0);
    const [imageUrl, setImageUrl] = useState('');
    const [isActive, setIsActive] = useState(true);

    const loadProducts = async () => {
        try {
            const list = await api.getSupplierProducts();
            const normalized: Product[] = (list || []).map((p: any) => ({
                id: p.id,
                name: p.name || 'Article sans nom',
                sku: p.sku || '',
                description: p.description || '',
                unit_price: Number(p.unit_price ?? p.unitPrice ?? 0),
                stock_quantity: Number(p.stock_quantity ?? p.stockQuantity ?? 0),
                image_url: p.image_url ?? p.imageUrl ?? '',
                is_active: p.is_active !== undefined ? Boolean(p.is_active) : (p.isActive !== undefined ? Boolean(p.isActive) : true),
            }));
            setProducts(normalized);
        } catch (err: any) {
            console.error(err);
            setError(err.message || 'Impossible de charger le catalogue');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        loadProducts();
    }, []);

    const filteredProducts = useMemo(() => {
        if (!searchQuery) return products;
        const q = searchQuery.toLowerCase();
        return products.filter((p) =>
            p.name.toLowerCase().includes(q) ||
            (p.sku && p.sku.toLowerCase().includes(q))
        );
    }, [products, searchQuery]);

    const openCreateModal = () => {
        setEditingProduct(null);
        setName('');
        setSku('');
        setDescription('');
        setUnitPrice(0);
        setStockQuantity(0);
        setImageUrl('');
        setIsActive(true);
        setIsModalOpen(true);
    };

    const openEditModal = (product: Product) => {
        setEditingProduct(product);
        setName(product.name);
        setSku(product.sku || '');
        setDescription(product.description || '');
        setUnitPrice(product.unit_price);
        setStockQuantity(product.stock_quantity);
        setImageUrl(product.image_url || '');
        setIsActive(product.is_active);
        setIsModalOpen(true);
    };

    const handleImageUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;

        setUploading(true);
        try {
            const url = await api.uploadSupplierImage(file);
            setImageUrl(url);
        } catch (err: any) {
            alert(err.message || "Erreur lors de l'upload de l'image.");
        } finally {
            setUploading(false);
        }
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setFormLoading(true);

        const cleanPrice = Math.max(0, parseInt(String(unitPrice), 10) || 0);
        const cleanStock = Math.max(0, parseInt(String(stockQuantity), 10) || 0);

        const payload = {
            name: name.trim(),
            sku: sku.trim() || undefined,
            description: description.trim() || undefined,
            unit_price: cleanPrice,
            unitPrice: cleanPrice,
            stock_quantity: cleanStock,
            stockQuantity: cleanStock,
            image_url: imageUrl.trim() || undefined,
            imageUrl: imageUrl.trim() || undefined,
            is_active: isActive,
            isActive: isActive,
        };

        try {
            if (editingProduct) {
                await api.updateSupplierProduct(editingProduct.id, payload);
            } else {
                await api.createSupplierProduct(payload);
            }
            setIsModalOpen(false);
            await loadProducts();
        } catch (err: any) {
            alert(err.message || 'Une erreur est survenue lors de la sauvegarde.');
        } finally {
            setFormLoading(false);
        }
    };

    const toggleProductActive = async (product: Product) => {
        try {
            await api.updateSupplierProduct(product.id, {
                name: product.name,
                unit_price: product.unit_price,
                stock_quantity: product.stock_quantity,
                is_active: !product.is_active,
            });
            await loadProducts();
        } catch (err: any) {
            alert(err.message || 'Une erreur est survenue');
        }
    };

    const archiveProduct = async (product: Product) => {
        if (confirm(`Êtes-vous sûr de vouloir archiver l'article "${product.name}" ?`)) {
            try {
                await api.deleteSupplierProduct(product.id);
                await loadProducts();
            } catch (err: any) {
                alert(err.message || 'Une erreur est survenue');
            }
        }
    };

    const money = (amount: number): string => `${new Intl.NumberFormat('fr-FR').format(amount)} FCFA`;

    if (loading) {
        return (
            <div className="flex items-center justify-center min-h-[400px]">
                <div className="w-8 h-8 border-2 border-amber-500 border-t-transparent rounded-full animate-spin" />
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Catalogue d'Articles</h1>
                    <p className="text-slate-400 text-sm mt-1">
                        Gérez vos produits, mettez à jour vos stocks et ajustez vos prix.
                    </p>
                </div>
                <button
                    onClick={openCreateModal}
                    className="bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold px-4 py-2.5 rounded-lg text-sm transition self-start active:scale-[0.98] shadow-lg shadow-amber-500/15"
                >
                    + Ajouter un article
                </button>
            </div>

            {/* Filters */}
            <div className="flex items-center bg-slate-900 border border-slate-800 rounded-lg px-4 py-2 max-w-md shadow-md">
                <span className="text-slate-500 text-sm">🔍</span>
                <input
                    type="text"
                    placeholder="Rechercher par nom, SKU..."
                    value={searchQuery}
                    onChange={(e) => setSearchQuery(e.target.value)}
                    className="w-full bg-transparent border-none text-white text-sm focus:outline-none pl-3 outline-none"
                />
            </div>

            {/* Product Grid */}
            {filteredProducts.length > 0 ? (
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    {filteredProducts.map((product) => (
                        <div
                            key={product.id}
                            className={`bg-slate-900/60 border rounded-xl overflow-hidden flex flex-col shadow-lg transition hover:scale-[1.01] ${
                                product.is_active ? 'border-slate-800' : 'border-slate-800/40 opacity-75'
                            }`}
                        >
                            {/* Product Image */}
                            <div className="h-44 bg-slate-950 relative flex items-center justify-center border-b border-slate-800">
                                {product.image_url ? (
                                    <img
                                        src={product.image_url}
                                        alt={product.name}
                                        className="w-full h-full object-cover"
                                    />
                                ) : (
                                    <span className="text-4xl">🛠️</span>
                                )}
                                {!product.is_active && (
                                    <span className="absolute top-3 right-3 bg-slate-950/80 border border-slate-700 text-slate-400 font-bold px-2 py-0.5 rounded text-[9px] uppercase tracking-wider">
                                        Désactivé
                                    </span>
                                )}
                            </div>

                            {/* Details */}
                            <div className="p-4 flex-1 flex flex-col justify-between space-y-4">
                                <div className="space-y-1">
                                    <div className="text-xs text-slate-500 font-mono tracking-wider">
                                        {product.sku || 'SANS SKU'}
                                    </div>
                                    <h3 className="font-bold text-white text-base line-clamp-1">{product.name}</h3>
                                    {product.description && (
                                        <p className="text-slate-400 text-xs line-clamp-2 mt-1">{product.description}</p>
                                    )}
                                </div>

                                <div className="space-y-3 pt-2">
                                    <div className="flex items-center justify-between">
                                        <span className="text-xs text-slate-500">Prix unitaire</span>
                                        <span className="font-extrabold text-amber-500">{money(product.unit_price)}</span>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span className="text-xs text-slate-500">Stock</span>
                                        <span className={`font-bold text-xs ${product.stock_quantity > 0 ? 'text-slate-200' : 'text-rose-400'}`}>
                                            {product.stock_quantity > 0 ? `${product.stock_quantity} unités` : 'Rupture de stock'}
                                        </span>
                                    </div>

                                    {/* Action Buttons */}
                                    <div className="flex gap-2 pt-2 border-t border-slate-800/60">
                                        <button
                                            onClick={() => openEditModal(product)}
                                            className="flex-1 bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold py-2 rounded-lg text-xs transition"
                                        >
                                            Modifier
                                        </button>
                                        <button
                                            onClick={() => toggleProductActive(product)}
                                            className={`px-3 py-2 rounded-lg text-xs font-bold transition border ${
                                                product.is_active
                                                    ? 'border-slate-800 hover:bg-yellow-950/20 hover:text-yellow-400'
                                                    : 'border-slate-800 hover:bg-emerald-950/20 hover:text-emerald-400'
                                            }`}
                                            title={product.is_active ? 'Désactiver' : 'Activer'}
                                        >
                                            {product.is_active ? '⏸' : '▶'}
                                        </button>
                                        <button
                                            onClick={() => archiveProduct(product)}
                                            className="px-3 py-2 rounded-lg text-xs font-bold hover:bg-rose-950/20 text-rose-400 hover:border-rose-900 border border-slate-800 transition"
                                            title="Archiver"
                                        >
                                            🗑️
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            ) : (
                <div className="text-center py-16 border border-dashed border-slate-850 rounded-xl">
                    <span className="text-5xl">📦</span>
                    <h3 className="text-slate-300 font-bold mt-4">Aucun article au catalogue</h3>
                    <p className="text-slate-500 text-xs mt-1">Créez votre premier article pour commencer à vendre.</p>
                    <button
                        onClick={openCreateModal}
                        className="bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold px-4 py-2 rounded-lg text-xs mt-6 transition"
                    >
                        + Ajouter un article
                    </button>
                </div>
            )}

            {/* Modal */}
            {isModalOpen && (
                <div className="fixed inset-0 bg-black/75 flex items-center justify-center p-4 z-50 backdrop-blur-sm">
                    <div className="bg-slate-900 border border-slate-800 w-full max-w-lg rounded-2xl overflow-hidden shadow-2xl flex flex-col max-h-[90vh]">
                        {/* Header */}
                        <div className="p-6 border-b border-slate-800 flex justify-between items-center">
                            <h3 className="font-bold text-lg text-white">
                                {editingProduct ? 'Modifier l\'article' : 'Ajouter un article'}
                            </h3>
                            <button
                                onClick={() => setIsModalOpen(false)}
                                className="text-slate-400 hover:text-white text-lg transition outline-none"
                            >
                                ✕
                            </button>
                        </div>

                        {/* Body Form */}
                        <form onSubmit={handleSubmit} className="p-6 overflow-y-auto space-y-4">
                            <div>
                                <label className="block text-slate-300 text-xs font-bold mb-1.5 uppercase">Nom de l'article *</label>
                                <input
                                    type="text"
                                    value={name}
                                    onChange={(e) => setName(e.target.value)}
                                    className="w-full bg-slate-950 border border-slate-800 text-white rounded-lg px-3 py-2.5 text-sm focus:border-amber-500 outline-none transition"
                                    required
                                    placeholder="Ex: Ciment CPJ 42.5 Béton"
                                />
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-slate-300 text-xs font-bold mb-1.5 uppercase">SKU / Référence</label>
                                    <input
                                        type="text"
                                        value={sku}
                                        onChange={(e) => setSku(e.target.value)}
                                        className="w-full bg-slate-950 border border-slate-800 text-white rounded-lg px-3 py-2.5 text-sm focus:border-amber-500 outline-none transition"
                                        placeholder="Ex: CIM-CPJ42"
                                    />
                                </div>
                                <div>
                                    <label className="block text-slate-300 text-xs font-bold mb-1.5 uppercase">Stock disponible *</label>
                                    <input
                                        type="number"
                                        value={stockQuantity}
                                        onChange={(e) => setStockQuantity(parseInt(e.target.value) || 0)}
                                        className="w-full bg-slate-950 border border-slate-800 text-white rounded-lg px-3 py-2.5 text-sm focus:border-amber-500 outline-none transition"
                                        required
                                        min={0}
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-slate-300 text-xs font-bold mb-1.5 uppercase">Prix unitaire (FCFA) *</label>
                                    <input
                                        type="number"
                                        value={unitPrice}
                                        onChange={(e) => setUnitPrice(parseInt(e.target.value) || 0)}
                                        className="w-full bg-slate-950 border border-slate-800 text-white rounded-lg px-3 py-2.5 text-sm focus:border-amber-500 outline-none transition"
                                        required
                                        min={0}
                                    />
                                </div>
                                <div>
                                    <label className="block text-slate-300 text-xs font-bold mb-1.5 uppercase">Visibilité</label>
                                    <select
                                        value={isActive ? '1' : '0'}
                                        onChange={(e) => setIsActive(e.target.value === '1')}
                                        className="w-full bg-slate-950 border border-slate-800 text-white rounded-lg px-3 py-2.5 text-sm focus:border-amber-500 outline-none transition"
                                    >
                                        <option value="1">Actif (Visible catalogue)</option>
                                        <option value="0">Désactivé (Masqué)</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label className="block text-slate-300 text-xs font-bold mb-1.5 uppercase">Description</label>
                                <textarea
                                    value={description}
                                    onChange={(e) => setDescription(e.target.value)}
                                    rows={2}
                                    className="w-full bg-slate-950 border border-slate-800 text-white rounded-lg px-3 py-2.5 text-sm focus:border-amber-500 outline-none transition"
                                    placeholder="Description ou fiche technique abrégée de l'article..."
                                />
                            </div>

                            <div>
                                <label className="block text-slate-300 text-xs font-bold mb-1.5 uppercase">Photo du produit</label>
                                <div className="flex items-center gap-3">
                                    <input
                                        type="text"
                                        value={imageUrl}
                                        onChange={(e) => setImageUrl(e.target.value)}
                                        className="flex-1 bg-slate-950 border border-slate-800 text-white rounded-lg px-3 py-2.5 text-sm focus:border-amber-500 outline-none transition"
                                        placeholder="URL de l'image (ex: https://...)"
                                    />
                                    <label className="bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold px-3.5 py-2.5 rounded-lg text-xs cursor-pointer border border-slate-700 transition flex items-center gap-1.5">
                                        {uploading ? (
                                            <>
                                                <span className="inline-block w-3 h-3 border-2 border-amber-500 border-t-transparent rounded-full animate-spin" />
                                                <span>Upload...</span>
                                            </>
                                        ) : (
                                            <>
                                                <span>📁</span>
                                                <span>Importer</span>
                                            </>
                                        )}
                                        <input
                                            type="file"
                                            accept="image/*"
                                            onChange={handleImageUpload}
                                            className="hidden"
                                            disabled={uploading}
                                        />
                                    </label>
                                </div>

                                {imageUrl && (
                                    <div className="mt-3 flex items-center gap-3 p-2 bg-slate-950 rounded-lg border border-slate-800">
                                        <img
                                            src={imageUrl}
                                            alt="Aperçu"
                                            className="w-14 h-14 object-cover rounded-lg border border-slate-700"
                                            onError={(e) => {
                                                (e.target as HTMLElement).style.display = 'none';
                                            }}
                                        />
                                        <div className="flex-1 text-xs text-slate-400 truncate">
                                            Aperçu de la photo
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() => setImageUrl('')}
                                            className="text-xs text-rose-400 hover:text-rose-300 font-bold px-2 py-1"
                                        >
                                            Supprimer
                                        </button>
                                    </div>
                                )}
                            </div>

                            <div className="flex gap-3 pt-4 border-t border-slate-800/60">
                                <button
                                    type="button"
                                    onClick={() => setIsModalOpen(false)}
                                    className="flex-1 bg-slate-800 hover:bg-slate-750 text-slate-300 font-bold py-3 rounded-lg text-xs transition"
                                    disabled={formLoading}
                                >
                                    Annuler
                                </button>
                                <button
                                    type="submit"
                                    className="flex-1 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-bold py-3 rounded-lg text-xs transition"
                                    disabled={formLoading}
                                >
                                    {formLoading ? 'Sauvegarde...' : 'Sauvegarder'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}
