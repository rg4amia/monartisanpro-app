import React, { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { EyeIcon, EyeSlashIcon } from '@heroicons/react/24/outline';

export default function Login() {
 const [showPassword, setShowPassword] = useState(false);

 const { data, setData, post, processing, errors } = useForm({
  email: '',
  password: '',
  remember: false,
 });

 const handleSubmit = (e) => {
  e.preventDefault();
  post('/backoffice/login');
 };

 return (
  <>
   <Head title="Connexion - Backoffice" />

   <div className="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div className="max-w-md w-full space-y-8">
     <div>
      <div className="mx-auto h-12 w-auto flex items-center justify-center">
       <span className="text-3xl font-bold text-blue-600">ProSartisan</span>
      </div>
      <h2 className="mt-6 text-center text-3xl font-extrabold text-gray-900">
       Backoffice Administration
      </h2>
      <p className="mt-2 text-center text-sm text-gray-600">
       Connectez-vous pour accéder au panneau d'administration
      </p>
     </div>

     <form className="mt-8 space-y-6" onSubmit={handleSubmit}>
      <div className="rounded-md shadow-sm -space-y-px">
       <div>
        <label htmlFor="email" className="sr-only">
         Adresse email
        </label>
        <input
         id="email"
         name="email"
         type="email"
         autoComplete="email"
         required
         className="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-t-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm"
         placeholder="Adresse email"
         value={data.email}
         onChange={(e) => setData('email', e.target.value)}
        />
        {errors.email && (
         <p className="mt-1 text-sm text-red-600">{errors.email}</p>
        )}
       </div>

       <div className="relative">
        <label htmlFor="password" className="sr-only">
         Mot de passe
        </label>
        <input
         id="password"
         name="password"
         type={showPassword ? 'text' : 'password'}
         autoComplete="current-password"
         required
         className="appearance-none rounded-none relative block w-full px-3 py-2 pr-10 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-b-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm"
         placeholder="Mot de passe"
         value={data.password}
         onChange={(e) => setData('password', e.target.value)}
        />
        <button
         type="button"
         className="absolute inset-y-0 right-0 pr-3 flex items-center"
         onClick={() => setShowPassword(!showPassword)}
        >
         {showPassword ? (
          <EyeSlashIcon className="h-5 w-5 text-gray-400" />
         ) : (
          <EyeIcon className="h-5 w-5 text-gray-400" />
         )}
        </button>
        {errors.password && (
         <p className="mt-1 text-sm text-red-600">{errors.password}</p>
        )}
       </div>
      </div>

      <div className="flex items-center justify-between">
       <div className="flex items-center">
        <input
         id="remember"
         name="remember"
         type="checkbox"
         className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
         checked={data.remember}
         onChange={(e) => setData('remember', e.target.checked)}
        />
        <label htmlFor="remember" className="ml-2 block text-sm text-gray-900">
         Se souvenir de moi
        </label>
       </div>
      </div>

      <div>
       <button
        type="submit"
        disabled={processing}
        className="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
       >
        {processing ? 'Connexion...' : 'Se connecter'}
       </button>
      </div>
     </form>
    </div>
   </div>
  </>
 );
}
