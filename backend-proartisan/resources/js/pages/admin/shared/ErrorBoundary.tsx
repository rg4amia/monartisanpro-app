import type { ErrorInfo, ReactNode } from 'react';
import React, { Component } from 'react';

interface ErrorBoundaryProps {
    children: ReactNode;
    fallbackTitle?: string;
}

interface ErrorBoundaryState {
    hasError: boolean;
    error: Error | null;
}

export class ErrorBoundary extends Component<ErrorBoundaryProps, ErrorBoundaryState> {
    constructor(props: ErrorBoundaryProps) {
        super(props);
        this.state = { hasError: false, error: null };
    }

    static getDerivedStateFromError(error: Error): ErrorBoundaryState {
        return { hasError: true, error };
    }

    componentDidCatch(error: Error, errorInfo: ErrorInfo) {
        console.error('ErrorBoundary caught an error:', error, errorInfo);
    }

    handleReload = () => {
        this.setState({ hasError: false, error: null });
        window.location.reload();
    };

    render() {
        if (this.state.hasError) {
            return (
                <div className="rounded-3xl border border-rose-200 bg-rose-50/70 p-6 sm:p-8 text-rose-950 shadow-sm my-6">
                    <div className="flex items-center gap-3">
                        <span className="text-2xl">⚠️</span>
                        <div>
                            <h3 className="text-base font-bold text-rose-900">
                                {this.props.fallbackTitle || 'Une erreur est survenue lors du chargement de cette section'}
                            </h3>
                            <p className="text-xs text-rose-700 mt-1">
                                {this.state.error?.message || 'Un incident inattendu a empêché l’affichage du composant.'}
                            </p>
                        </div>
                    </div>
                    <div className="mt-4 flex items-center gap-3">
                        <button
                            type="button"
                            onClick={this.handleReload}
                            className="rounded-xl bg-rose-800 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-rose-900 transition"
                        >
                            Recharger la page
                        </button>
                        <button
                            type="button"
                            onClick={() => this.setState({ hasError: false, error: null })}
                            className="rounded-xl border border-rose-300 bg-white px-4 py-2 text-xs font-semibold text-rose-800 hover:bg-rose-50 transition"
                        >
                            Réessayer
                        </button>
                    </div>
                </div>
            );
        }

        return this.props.children;
    }
}
