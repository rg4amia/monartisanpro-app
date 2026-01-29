import React, { useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import {
 HomeIcon,
 UserGroupIcon,
 CurrencyDollarIcon,
 ChartBarIcon,
 ExclamationTriangleIcon,
 CogIcon,
 Bars3Icon,
 XMarkIcon,
 StarIcon,
 DocumentTextIcon,
 TicketIcon,
 LockClosedIcon
} from '@heroicons/react/24/outline';

export default function BackofficeLayout({ children }) {
 const [sidebarOpen, setSidebarOpen] = useState(false);
 const { auth } = usePage().props;

 const navigation = [
  { name: 'Dashboard', href: '/backoffice/dashboard', icon: HomeIcon },
  { name: 'Utilisateurs', href: '/backoffice/users', icon: UserGroupIcon },
  {
   name: 'Transactions',
   href: '/backoffice/transactions',
   icon: CurrencyDollarIcon,
   submenu: [
    { name: 'Toutes les transactions', href: '/backoffice/transactions' },
    { name: 'Jetons Matériel', href: '/backoffice/transactions/jetons/index' },
    { name: 'Séquestres', href: '/backoffice/transactions/sequestres/index' },
   ]
  },
  { name: 'KYC', href: '/backoffice/kyc', icon: DocumentTextIcon },
  { name: 'Litiges', href: '/backoffice/disputes', icon: ExclamationTriangleIcon },
  { name: 'Réputation', href: '/backoffice/reputation', icon: StarIcon },
  {
   name: 'Analytics',
   href: '/backoffice/analytics',
   icon: ChartBarIcon,
   submenu: [
    { name: 'Vue d\'ensemble', href: '/backoffice/analytics' },
    { name: 'Revenus', href: '/backoffice/analytics/revenue' },
    { name: 'Utilisateurs', href: '/backoffice/analytics/users' },
    { name: 'Performance', href: '/backoffice/analytics/performance' },
   ]
  },
  { name: 'Paramètres', href: '/backoffice/parameters', icon: CogIcon },
 ];

 return (
  <div className="min-h-screen bg-gray-100">
   {/* Sidebar Mobile */}
   <div className={`fixed inset-0 z-40 lg:hidden ${sidebarOpen ? '' : 'hidden'}`}>
    <div className="fixed inset-0 bg-gray-600 bg-opacity-75" onClick={() => setSidebarOpen(false)} />
    <div className="fixed inset-y-0 left-0 flex w-64 flex-col bg-white">
     <div className="flex items-center justify-between px-4 py-5">
      <span className="text-xl font-bold text-blue-600">ProSartisan</span>
      <button onClick={() => setSidebarOpen(false)}>
       <XMarkIcon className="h-6 w-6" />
      </button>
     </div>
     <nav className="flex-1 space-y-1 px-2 py-4">
      {navigation.map((item) => (
       <div key={item.name}>
        <Link
         href={item.href}
         className="group flex items-center px-2 py-2 text-sm font-medium rounded-md hover:bg-gray-100 transition-colors"
        >
         <item.icon className="mr-3 h-6 w-6" />
         {item.name}
        </Link>
        {item.submenu && (
         <div className="ml-6 space-y-1">
          {item.submenu.map((subitem) => (
           <Link
            key={subitem.name}
            href={subitem.href}
            className="group flex items-center px-2 py-1 text-xs text-gray-600 rounded-md hover:bg-gray-50 transition-colors"
           >
            {subitem.name}
           </Link>
          ))}
         </div>
        )}
       </div>
      ))}
     </nav>
    </div>
   </div>

   {/* Sidebar Desktop */}
   <div className="hidden lg:fixed lg:inset-y-0 lg:flex lg:w-64 lg:flex-col">
    <div className="flex flex-col flex-grow border-r border-gray-200 bg-white overflow-y-auto">
     <div className="flex items-center flex-shrink-0 px-4 py-5">
      <span className="text-2xl font-bold text-blue-600">ProSartisan</span>
     </div>
     <nav className="flex-1 space-y-1 px-2 py-4">
      {navigation.map((item) => (
       <div key={item.name}>
        <Link
         href={item.href}
         className="group flex items-center px-2 py-2 text-sm font-medium rounded-md hover:bg-gray-100 transition-colors"
        >
         <item.icon className="mr-3 h-6 w-6 text-gray-500" />
         {item.name}
        </Link>
        {item.submenu && (
         <div className="ml-6 space-y-1">
          {item.submenu.map((subitem) => (
           <Link
            key={subitem.name}
            href={subitem.href}
            className="group flex items-center px-2 py-1 text-xs text-gray-600 rounded-md hover:bg-gray-50 transition-colors"
           >
            {subitem.name}
           </Link>
          ))}
         </div>
        )}
       </div>
      ))}
     </nav>
    </div>
   </div>

   {/* Main Content */}
   <div className="lg:pl-64 flex flex-col flex-1">
    {/* Top Bar */}
    <div className="sticky top-0 z-10 flex h-16 flex-shrink-0 bg-white shadow">
     <button
      type="button"
      className="px-4 text-gray-500 focus:outline-none lg:hidden"
      onClick={() => setSidebarOpen(true)}
     >
      <Bars3Icon className="h-6 w-6" />
     </button>
     <div className="flex flex-1 justify-between px-4">
      <div className="flex flex-1" />
      <div className="ml-4 flex items-center md:ml-6">
       <span className="text-sm text-gray-700">
        {auth?.user?.email || 'Admin'}
       </span>
      </div>
     </div>
    </div>

    {/* Page Content */}
    <main className="flex-1">
     <div className="py-6">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
       {children}
      </div>
     </div>
    </main>
   </div>
  </div>
 );
}
