import React, { useState } from 'react';
import BackofficeLayout from '@/Layouts/BackofficeLayout';
import { Head, Link, router } from '@inertiajs/react';
import {
 ArrowLeftIcon,
 StarIcon,
 TrophyIcon,
 ChartBarIcon,
 PencilIcon,
 ClockIcon,
 CurrencyDollarIcon,
 CheckCircleIcon
} from '@heroicons/react/24/outline';
import Button from '@/Components/Common/Button';
import Modal from '@/Components/Common/Modal';
import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';

export default function ReputationShow({ artisan, scoreHistory, detailedMetrics, recentRatings }) {
 const [showAdjustModal, setShowAdjustModal] = useState(false);
 const [adjustForm, setAdjustForm] = useState({
  new_score: artisan.current_score,
  justification: ''
 });

 const handleAdjustScore = () => {
  router.post(`/backoffice/reputation/${artisan.artisan_id}/adjust-score`, adjustForm, {
   onSuccess: () => {
    setShowAdjustModal(false);
    setAdjustForm({ new_score: artisan.current_score, justification: '' });
   }
  });
 };

 const getScoreBadge = (score) => {
  if (score >= 800) {
   return (
    <span className="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
     <TrophyIcon className="w-4 h-4 mr-1" />
     Excellent ({score})
    </span>
   );
  } else if (score >= 600) {
   return (
    <span className="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
     <StarIcon className="w-4 h-4 mr-1" />
     Bon ({score})
    </span>
   );
  } else {
   return (
    <span className="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
     <ChartBarIcon className="w-4 h-4 mr-1" />
     À améliorer ({score})
    </span>
   );
  }
 };

 const formatAmount = (centimes) => {
  return new Intl.NumberFormat('fr-FR', {
   style: 'currency',
   currency: 'XOF',
   minimumFractionDigits: 0,
  }).format(centimes / 100);
 };

 const getReliabilityScore = () => {
  if (detailedMetrics.accepted_projects === 0) return 0;
  return Math.round((detailedMetrics.completed_projects / detailedMetrics.accepted_projects) * 100);
 };

 const getQualityScore = () => {
  if (!detailedMetrics.average_rating) return 0;
  return Math.round((detailedMetrics.average_rating / 5) * 100);
 };

 // Prepare chart data
 const chartData = scoreHistory.slice().reverse().map(item => ({
  date: new Date(item.recorded_at).toLocaleDateString('fr-FR'),
  score: item.new_score,
  reason: item.reason
 }));

 const renderStars = (rating) => {
  return Array.from({ length: 5 }, (_, i) => (
   <StarIcon
    key={i}
    className={`h-4 w-4 ${i < rating ? 'text-yellow-400 fill-current' : 'text-gray-300'}`}
   />
  ));
 };

 return (
  <BackofficeLayout>
   <Head title={`Réputation - ${artisan.email}`} />

   <div className="mb-8">
    <div className="flex items-center mb-4">
     <Link
      href="/backoffice/reputation"
      className="mr-4 p-2 text-gray-400 hover:text-gray-600"
     >
      <ArrowLeftIcon className="h-5 w-5" />
     </Link>
     <div>
      <h1 className="text-3xl font-bold text-gray-900">
       Profil de réputation
      </h1>
      <p className="mt-2 text-sm text-gray-700">
       {artisan.email} • Inscrit le {new Date(artisan.user_created_at).toLocaleDateString('fr-FR')}
      </p>
     </div>
    </div>

    <div className="flex items-center space-x-4">
     {getScoreBadge(artisan.current_score)}

     {artisan.current_score > 700 && (
      <span className="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-purple-100 text-purple-800">
       Éligible micro-crédit
      </span>
     )}

     <Button
      variant="outline"
      size="sm"
      onClick={() => setShowAdjustModal(true)}
     >
      <PencilIcon className="h-4 w-4 mr-2" />
      Ajuster le score
     </Button>
    </div>
   </div>

   <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
    {/* Main Content */}
    <div className="lg:col-span-2 space-y-6">
     {/* Score Breakdown */}
     <div className="bg-white shadow rounded-lg p-6">
      <h2 className="text-lg font-medium text-gray-900 mb-4">Décomposition du Score N'Zassa</h2>
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
       <div>
        <div className="flex items-center justify-between mb-2">
         <span className="text-sm font-medium text-gray-700">Fiabilité (40%)</span>
         <span className="text-sm font-semibold text-gray-900">{getReliabilityScore()}/100</span>
        </div>
        <div className="w-full bg-gray-200rounded-full h-2">
         <div
          className="bg-blue-600 h-2 rounded-full"
          style={{ width: `${getReliabilityScore()}%` }}
         ></div>
        </div>
        <p className="text-xs text-gray-500 mt-1">
         {detailedMetrics.completed_projects} projets terminés sur {detailedMetrics.accepted_projects} acceptés
        </p>
       </div>

       <div>
        <div className="flex items-center justify-between mb-2">
         <span className="text-sm font-medium text-gray-700">Qualité (20%)</span>
         <span className="text-sm font-semibold text-gray-900">{getQualityScore()}/100</span>
        </div>
        <div className="w-full bg-gray-200 rounded-full h-2">
         <div
          className="bg-green-600 h-2 rounded-full"
          style={{ width: `${getQualityScore()}%` }}
         ></div>
        </div>
        <p className="text-xs text-gray-500 mt-1">
         Note moyenne: {detailedMetrics.average_rating ? detailedMetrics.average_rating.toFixed(1) : 'N/A'}/5
         ({detailedMetrics.total_ratings} avis)
        </p>
       </div>

       <div>
        <div className="flex items-center justify-between mb-2">
         <span className="text-sm font-medium text-gray-700">Intégrité (30%)</span>
         <span className="text-sm font-semibold text-gray-900">85/100</span>
        </div>
        <div className="w-full bg-gray-200 rounded-full h-2">
         <div className="bg-purple-600 h-2 rounded-full" style={{ width: '85%' }}></div>
        </div>
        <p className="text-xs text-gray-500 mt-1">
         {detailedMetrics.disputes_involved} litige(s) impliqué(s)
        </p>
       </div>

       <div>
        <div className="flex items-center justify-between mb-2">
         <span className="text-sm font-medium text-gray-700">Réactivité (10%)</span>
         <span className="text-sm font-semibold text-gray-900">75/100</span>
        </div>
        <div className="w-full bg-gray-200 rounded-full h-2">
         <div className="bg-yellow-600 h-2 rounded-full" style={{ width: '75%' }}></div>
        </div>
        <p className="text-xs text-gray-500 mt-1">
         Temps de réponse moyen: {detailedMetrics.response_time_hours}h
        </p>
       </div>
      </div>
     </div>

     {/* Score History Chart */}
     <div className="bg-white shadow rounded-lg p-6">
      <h2 className="text-lg font-medium text-gray-900 mb-4">Évolution du Score</h2>
      {chartData.length > 0 ? (
       <ResponsiveContainer width="100%" height={300}>
        <LineChart data={chartData}>
         <CartesianGrid strokeDasharray="3 3" />
         <XAxis dataKey="date" />
         <YAxis domain={[0, 1000]} />
         <Tooltip
          formatter={(value, name) => [value, 'Score N\'Zassa']}
          labelFormatter={(label) => `Date: ${label}`}
         />
         <Line type="monotone" dataKey="score" stroke="#1E88E5" strokeWidth={2} />
        </LineChart>
       </ResponsiveContainer>
      ) : (
       <p className="text-gray-500 text-center py-8">Aucun historique de score disponible</p>
      )}
     </div>

     {/* Recent Ratings */}
     <div className="bg-white shadow rounded-lg p-6">
      <h2 className="text-lg font-medium text-gray-900 mb-4">Avis Récents</h2>
      {recentRatings.length > 0 ? (
       <div className="space-y-4">
        {recentRatings.map((rating) => (
         <div key={rating.id} className="border-l-4 border-blue-200 pl-4">
          <div className="flex items-center justify-between mb-2">
           <div className="flex items-center space-x-2">
            <div className="flex">
             {renderStars(rating.rating)}
            </div>
            <span className="text-sm font-medium text-gray-900">
             {rating.client_email}
            </span>
           </div>
           <span className="text-xs text-gray-500">
            {new Date(rating.created_at).toLocaleDateString('fr-FR')}
           </span>
          </div>
          {rating.comment && (
           <p className="text-sm text-gray-700 mb-2">{rating.comment}</p>
          )}
          <p className="text-xs text-gray-500">
           Mission: {rating.mission_description}
          </p>
         </div>
        ))}
       </div>
      ) : (
       <p className="text-gray-500 text-center py-4">Aucun avis pour le moment</p>
      )}
     </div>
    </div>

    {/* Sidebar */}
    <div className="space-y-6">
     {/* Key Metrics */}
     <div className="bg-white shadow rounded-lg p-6">
      <h2 className="text-lg font-medium text-gray-900 mb-4">Métriques Clés</h2>
      <div className="space-y-4">
       <div className="flex items-center justify-between">
        <div className="flex items-center">
         <CheckCircleIcon className="h-5 w-5 text-green-500 mr-2" />
         <span className="text-sm text-gray-600">Projets terminés</span>
        </div>
        <span className="text-sm font-medium text-gray-900">
         {detailedMetrics.completed_projects}
        </span>
       </div>

       <div className="flex items-center justify-between">
        <div className="flex items-center">
         <ChartBarIcon className="h-5 w-5 text-blue-500 mr-2" />
         <span className="text-sm text-gray-600">Projets acceptés</span>
        </div>
        <span className="text-sm font-medium text-gray-900">
         {detailedMetrics.accepted_projects}
        </span>
       </div>

       <div className="flex items-center justify-between">
        <div className="flex items-center">
         <CurrencyDollarIcon className="h-5 w-5 text-yellow-500 mr-2" />
         <span className="text-sm text-gray-600">Gains totaux</span>
        </div>
        <span className="text-sm font-medium text-gray-900">
         {formatAmount(detailedMetrics.total_earnings)}
        </span>
       </div>

       <div className="flex items-center justify-between">
        <div className="flex items-center">
         <StarIcon className="h-5 w-5 text-purple-500 mr-2" />
         <span className="text-sm text-gray-600">Note moyenne</span>
        </div>
        <span className="text-sm font-medium text-gray-900">
         {detailedMetrics.average_rating ? detailedMetrics.average_rating.toFixed(1) : 'N/A'}/5
        </span>
       </div>

       <div className="flex items-center justify-between">
        <div className="flex items-center">
         <ClockIcon className="h-5 w-5 text-gray-500 mr-2" />
         <span className="text-sm text-gray-600">Temps de réponse</span>
        </div>
        <span className="text-sm font-medium text-gray-900">
         {detailedMetrics.response_time_hours}h
        </span>
       </div>
      </div>
     </div>

     {/* Score History */}
     <div className="bg-white shadow rounded-lg p-6">
      <h2 className="text-lg font-medium text-gray-900 mb-4">Historique des Ajustements</h2>
      {scoreHistory.length > 0 ? (
       <div className="space-y-3">
        {scoreHistory.slice(0, 5).map((item) => (
         <div key={item.id} className="text-sm">
          <div className="flex items-center justify-between">
           <span className="font-medium text-gray-900">
            {item.old_score} → {item.new_score}
           </span>
           <span className="text-xs text-gray-500">
            {new Date(item.recorded_at).toLocaleDateString('fr-FR')}
           </span>
          </div>
          <p className="text-xs text-gray-600 mt-1 truncate">
           {item.reason}
          </p>
         </div>
        ))}
       </div>
      ) : (
       <p className="text-gray-500 text-sm">Aucun historique disponible</p>
      )}
     </div>
    </div>
   </div>

   {/* Adjust Score Modal */}
   <Modal
    isOpen={showAdjustModal}
    onClose={() => setShowAdjustModal(false)}
    title="Ajuster le Score N'Zassa"
    maxWidth="max-w-md"
   >
    <div className="space-y-4">
     <div>
      <label className="block text-sm font-medium text-gray-700 mb-1">
       Nouveau score (0-1000)
      </label>
      <input
       type="number"
       min="0"
       max="1000"
       className="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
       value={adjustForm.new_score}
       onChange={(e) => setAdjustForm({ ...adjustForm, new_score: parseInt(e.target.value) })}
      />
      <p className="text-xs text-gray-500 mt-1">
       Score actuel: {artisan.current_score}
      </p>
     </div>

     <div>
      <label className="block text-sm font-medium text-gray-700 mb-1">
       Justification
      </label>
      <textarea
       className="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500"
       rows={4}
       value={adjustForm.justification}
       onChange={(e) => setAdjustForm({ ...adjustForm, justification: e.target.value })}
       placeholder="Expliquez la raison de cet ajustement..."
      />
     </div>

     <div className="flex justify-end space-x-3">
      <Button
       variant="outline"
       onClick={() => setShowAdjustModal(false)}
      >
       Annuler
      </Button>
      <Button
       variant="primary"
       onClick={handleAdjustScore}
       disabled={!adjustForm.justification.trim() || adjustForm.new_score === artisan.current_score}
      >
       Ajuster le score
      </Button>
     </div>
    </div>
   </Modal>
  </BackofficeLayout>
 );
}
