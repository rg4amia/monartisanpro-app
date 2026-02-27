import React, { useEffect } from 'react';
import { View, Text, StyleSheet, ScrollView, TouchableOpacity, TextInput, FlatList } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useRouter } from 'expo-router';
import { Image } from 'expo-image';
import { Search, MapPin, Bell, Droplets, Zap, Hammer, Paintbrush, LayoutGrid, Wallet, CheckCircle, Clock, ScanLine, AlertTriangle, BrickWall } from 'lucide-react-native';
import { Colors } from '@/constants/colors';
import { useUser } from '@/contexts/UserContext';
import { categories, artisans, missions, formatFCFA } from '@/mocks/data';
import ArtisanCard from '@/components/ArtisanCard';
import ScoreNzassa from '@/components/ScoreNzassa';

const iconMap: Record<string, typeof Droplets> = {
  Droplets,
  Zap,
  Brick: BrickWall,
  Hammer,
  Paintbrush,
  LayoutGrid,
};

function ClientHome() {
  const router = useRouter();
  const insets = useSafeAreaInsets();

  return (
    <ScrollView style={styles.container} contentContainerStyle={{ paddingBottom: 20 }} showsVerticalScrollIndicator={false}>
      <View style={[styles.heroSection, { paddingTop: insets.top + 16 }]}>
        <View style={styles.heroHeader}>
          <View>
            <Text style={styles.heroGreeting}>Bonjour 👋</Text>
            <Text style={styles.heroTitle}>De quoi avez-vous besoin ?</Text>
          </View>
          <TouchableOpacity style={styles.bellBtn}>
            <Bell size={22} color={Colors.white} />
            <View style={styles.bellDot} />
          </TouchableOpacity>
        </View>

        <TouchableOpacity
          style={styles.searchBar}
          onPress={() => router.push('/(tabs)/(home)/mission-request')}
          activeOpacity={0.8}
        >
          <Search size={18} color={Colors.textMuted} />
          <Text style={styles.searchPlaceholder}>Décrivez votre besoin...</Text>
        </TouchableOpacity>
      </View>

      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Catégories</Text>
        <View style={styles.categoriesGrid}>
          {categories.map((cat) => {
            const Icon = iconMap[cat.icon] || Hammer;
            return (
              <TouchableOpacity key={cat.id} style={styles.categoryCard} activeOpacity={0.7}>
                <View style={[styles.categoryIcon, { backgroundColor: cat.color + '15' }]}>
                  <Icon size={22} color={cat.color} />
                </View>
                <Text style={styles.categoryName}>{cat.name}</Text>
              </TouchableOpacity>
            );
          })}
        </View>
      </View>

      <View style={styles.section}>
        <View style={styles.sectionHeader}>
          <Text style={styles.sectionTitle}>Artisans à proximité</Text>
          <TouchableOpacity>
            <Text style={styles.seeAll}>Voir tout</Text>
          </TouchableOpacity>
        </View>
        <View style={styles.locationRow}>
          <MapPin size={14} color={Colors.accent} />
          <Text style={styles.locationText}>Cocody, Abidjan</Text>
        </View>
        {artisans.map((artisan) => (
          <ArtisanCard
            key={artisan.id}
            artisan={artisan}
            onPress={() => router.push({ pathname: '/(tabs)/(home)/artisan-profile', params: { id: artisan.id } })}
          />
        ))}
      </View>
    </ScrollView>
  );
}

function ArtisanDashboard() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const activeMissions = missions.filter(m => m.status === 'en_cours' || m.status === 'jalons');

  return (
    <ScrollView style={styles.container} contentContainerStyle={{ paddingBottom: 20 }} showsVerticalScrollIndicator={false}>
      <View style={[styles.artisanHero, { paddingTop: insets.top + 16 }]}>
        <View style={styles.heroHeader}>
          <View style={styles.artisanProfile}>
            <Image
              source={{ uri: artisans[0].photo }}
              style={styles.artisanAvatar}
            />
            <View>
              <Text style={styles.artisanWelcome}>Bienvenue</Text>
              <Text style={styles.artisanName}>{artisans[0].name}</Text>
            </View>
          </View>
          <TouchableOpacity style={styles.bellBtn}>
            <Bell size={22} color={Colors.white} />
            <View style={styles.bellDot} />
          </TouchableOpacity>
        </View>
      </View>

      <View style={styles.statsRow}>
        <TouchableOpacity style={styles.statCard}>
          <View style={[styles.statIcon, { backgroundColor: Colors.accent + '15' }]}>
            <Clock size={20} color={Colors.accent} />
          </View>
          <Text style={styles.statNumber}>{activeMissions.length}</Text>
          <Text style={styles.statLabel}>En cours</Text>
        </TouchableOpacity>
        <TouchableOpacity style={styles.statCard} onPress={() => router.push('/(tabs)/(home)/score')}>
          <ScoreNzassa score={artisans[0].scoreNzassa} size="small" />
          <Text style={styles.statNumber}>{artisans[0].scoreNzassa}</Text>
          <Text style={styles.statLabel}>Score</Text>
        </TouchableOpacity>
        <TouchableOpacity style={styles.statCard}>
          <View style={[styles.statIcon, { backgroundColor: Colors.success + '15' }]}>
            <Wallet size={20} color={Colors.success} />
          </View>
          <Text style={styles.statNumber}>{formatFCFA(275000)}</Text>
          <Text style={styles.statLabel}>Solde</Text>
        </TouchableOpacity>
      </View>

      <View style={styles.section}>
        <View style={styles.walletBars}>
          <Text style={styles.walletTitle}>Répartition Escrow</Text>
          <View style={styles.walletRow}>
            <Text style={styles.walletLabel}>Matériaux</Text>
            <View style={styles.walletBarBg}>
              <View style={[styles.walletBarFill, { width: '65%', backgroundColor: Colors.primary }]} />
            </View>
            <Text style={styles.walletAmount}>{formatFCFA(110000)}</Text>
          </View>
          <View style={styles.walletRow}>
            <Text style={styles.walletLabel}>Main d'œuvre</Text>
            <View style={styles.walletBarBg}>
              <View style={[styles.walletBarFill, { width: '35%', backgroundColor: Colors.accent }]} />
            </View>
            <Text style={styles.walletAmount}>{formatFCFA(75000)}</Text>
          </View>
        </View>
      </View>

      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Nouvelle demande</Text>
        <View style={styles.newMissionCard}>
          <View style={styles.newMissionHeader}>
            <View style={styles.urgentBadge}>
              <Text style={styles.urgentText}>Urgent</Text>
            </View>
            <Text style={styles.newMissionDistance}>2.3 km</Text>
          </View>
          <Text style={styles.newMissionTitle}>Fuite d'eau - Cuisine</Text>
          <Text style={styles.newMissionClient}>Awa Traoré · Cocody Angré</Text>
          <Text style={styles.newMissionDesc}>Fuite importante sous l'évier de la cuisine...</Text>
          <View style={styles.actionRow}>
            <TouchableOpacity style={styles.declineBtn} activeOpacity={0.7}>
              <Text style={styles.declineBtnText}>Refuser</Text>
            </TouchableOpacity>
            <TouchableOpacity
              style={styles.acceptBtn}
              activeOpacity={0.7}
              onPress={() => router.push('/(tabs)/(home)/quote-builder')}
            >
              <Text style={styles.acceptBtnText}>Accepter</Text>
            </TouchableOpacity>
          </View>
        </View>
      </View>

      <View style={styles.section}>
        <View style={styles.sectionHeader}>
          <Text style={styles.sectionTitle}>Raccourcis</Text>
        </View>
        <View style={styles.quickActions}>
          <TouchableOpacity
            style={styles.quickAction}
            onPress={() => router.push('/(tabs)/(home)/jcode')}
          >
            <View style={[styles.quickActionIcon, { backgroundColor: Colors.primary + '12' }]}>
              <ScanLine size={22} color={Colors.primary} />
            </View>
            <Text style={styles.quickActionText}>J-Code</Text>
          </TouchableOpacity>
          <TouchableOpacity
            style={styles.quickAction}
            onPress={() => router.push('/(tabs)/(home)/score')}
          >
            <View style={[styles.quickActionIcon, { backgroundColor: Colors.success + '12' }]}>
              <CheckCircle size={22} color={Colors.success} />
            </View>
            <Text style={styles.quickActionText}>Score</Text>
          </TouchableOpacity>
          <TouchableOpacity
            style={styles.quickAction}
            onPress={() => router.push('/(tabs)/(home)/quote-builder')}
          >
            <View style={[styles.quickActionIcon, { backgroundColor: Colors.accent + '12' }]}>
              <Wallet size={22} color={Colors.accent} />
            </View>
            <Text style={styles.quickActionText}>Devis</Text>
          </TouchableOpacity>
        </View>
      </View>
    </ScrollView>
  );
}

function SupplierHome() {
  const insets = useSafeAreaInsets();
  const router = useRouter();

  return (
    <ScrollView style={styles.container} contentContainerStyle={{ paddingBottom: 20 }} showsVerticalScrollIndicator={false}>
      <View style={[styles.supplierHero, { paddingTop: insets.top + 16 }]}>
        <Text style={styles.supplierTitle}>Scanner J-Code</Text>
        <Text style={styles.supplierSubtitle}>Scannez le QR code de l'artisan pour valider l'achat</Text>
      </View>

      <TouchableOpacity
        style={styles.scannerArea}
        activeOpacity={0.8}
        onPress={() => router.push('/(tabs)/(home)/transaction-confirm')}
      >
        <View style={styles.scannerFrame}>
          <ScanLine size={64} color={Colors.success} />
          <Text style={styles.scannerText}>Appuyez pour scanner</Text>
        </View>
        <View style={styles.scannerCornerTL} />
        <View style={styles.scannerCornerTR} />
        <View style={styles.scannerCornerBL} />
        <View style={styles.scannerCornerBR} />
      </TouchableOpacity>

      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Ou entrez le code manuellement</Text>
        <View style={styles.manualCodeRow}>
          <TextInput
            style={styles.codeInput}
            placeholder="PA-XXXX"
            placeholderTextColor={Colors.textMuted}
          />
          <TouchableOpacity
            style={styles.validateCodeBtn}
            onPress={() => router.push('/(tabs)/(home)/transaction-confirm')}
          >
            <Text style={styles.validateCodeText}>Valider</Text>
          </TouchableOpacity>
        </View>
      </View>

      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Dernières transactions</Text>
        <View style={styles.recentTransaction}>
          <View style={[styles.txStatusDot, { backgroundColor: Colors.success }]} />
          <View style={styles.txInfo}>
            <Text style={styles.txCode}>PA-4827</Text>
            <Text style={styles.txArtisan}>Kouamé Yao Jean</Text>
          </View>
          <Text style={styles.txAmount}>{formatFCFA(110000)}</Text>
        </View>
        <View style={styles.recentTransaction}>
          <View style={[styles.txStatusDot, { backgroundColor: Colors.warning }]} />
          <View style={styles.txInfo}>
            <Text style={styles.txCode}>PA-6012</Text>
            <Text style={styles.txArtisan}>Touré Abdoulaye</Text>
          </View>
          <View style={styles.gpsWarning}>
            <AlertTriangle size={14} color={Colors.danger} />
          </View>
          <Text style={styles.txAmount}>{formatFCFA(250000)}</Text>
        </View>
      </View>
    </ScrollView>
  );
}

export default function HomeScreen() {
  const { role, isOnboarded, isLoading } = useUser();
  const router = useRouter();

  useEffect(() => {
    if (!isLoading && !isOnboarded) {
      router.replace('/login');
    }
  }, [isLoading, isOnboarded]);

  if (isLoading || !isOnboarded) {
    return (
      <View style={styles.loadingContainer}>
        <Text style={styles.loadingText}>ProsArtisan</Text>
      </View>
    );
  }

  if (role === 'artisan') return <ArtisanDashboard />;
  if (role === 'fournisseur') return <SupplierHome />;
  return <ClientHome />;
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: Colors.background,
  },
  heroSection: {
    backgroundColor: Colors.primary,
    paddingHorizontal: 20,
    paddingBottom: 24,
    borderBottomLeftRadius: 24,
    borderBottomRightRadius: 24,
  },
  heroHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 20,
  },
  heroGreeting: {
    fontSize: 14,
    color: 'rgba(255,255,255,0.7)',
  },
  heroTitle: {
    fontSize: 22,
    fontWeight: '700' as const,
    color: Colors.white,
    marginTop: 4,
  },
  bellBtn: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: 'rgba(255,255,255,0.12)',
    justifyContent: 'center',
    alignItems: 'center',
  },
  bellDot: {
    position: 'absolute',
    top: 10,
    right: 12,
    width: 8,
    height: 8,
    borderRadius: 4,
    backgroundColor: Colors.danger,
    borderWidth: 1.5,
    borderColor: Colors.primary,
  },
  searchBar: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: Colors.white,
    borderRadius: 14,
    paddingHorizontal: 16,
    paddingVertical: 14,
    gap: 10,
  },
  searchPlaceholder: {
    fontSize: 15,
    color: Colors.textMuted,
  },
  section: {
    paddingHorizontal: 20,
    marginTop: 24,
  },
  sectionHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: '700' as const,
    color: Colors.textPrimary,
    marginBottom: 14,
  },
  seeAll: {
    fontSize: 13,
    color: Colors.accent,
    fontWeight: '600' as const,
    marginBottom: 14,
  },
  categoriesGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 12,
  },
  categoryCard: {
    width: '30%',
    alignItems: 'center',
    backgroundColor: Colors.white,
    borderRadius: 14,
    paddingVertical: 16,
    shadowColor: Colors.primary,
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.04,
    shadowRadius: 8,
    elevation: 1,
  },
  categoryIcon: {
    width: 48,
    height: 48,
    borderRadius: 24,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 8,
  },
  categoryName: {
    fontSize: 12,
    fontWeight: '600' as const,
    color: Colors.textPrimary,
  },
  locationRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    marginBottom: 12,
    marginTop: -8,
  },
  locationText: {
    fontSize: 13,
    color: Colors.textSecondary,
  },
  artisanHero: {
    backgroundColor: Colors.accent,
    paddingHorizontal: 20,
    paddingBottom: 24,
    borderBottomLeftRadius: 24,
    borderBottomRightRadius: 24,
  },
  artisanProfile: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  artisanAvatar: {
    width: 48,
    height: 48,
    borderRadius: 24,
    borderWidth: 2,
    borderColor: 'rgba(255,255,255,0.3)',
  },
  artisanWelcome: {
    fontSize: 13,
    color: 'rgba(255,255,255,0.7)',
  },
  artisanName: {
    fontSize: 18,
    fontWeight: '700' as const,
    color: Colors.white,
  },
  statsRow: {
    flexDirection: 'row',
    paddingHorizontal: 20,
    gap: 10,
    marginTop: -20,
  },
  statCard: {
    flex: 1,
    backgroundColor: Colors.white,
    borderRadius: 16,
    padding: 14,
    alignItems: 'center',
    shadowColor: Colors.primary,
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.08,
    shadowRadius: 12,
    elevation: 3,
    gap: 4,
  },
  statIcon: {
    width: 36,
    height: 36,
    borderRadius: 18,
    justifyContent: 'center',
    alignItems: 'center',
  },
  statNumber: {
    fontSize: 14,
    fontWeight: '700' as const,
    color: Colors.textPrimary,
  },
  statLabel: {
    fontSize: 10,
    color: Colors.textMuted,
    fontWeight: '500' as const,
  },
  walletBars: {
    backgroundColor: Colors.white,
    borderRadius: 16,
    padding: 18,
  },
  walletTitle: {
    fontSize: 14,
    fontWeight: '700' as const,
    color: Colors.textPrimary,
    marginBottom: 14,
  },
  walletRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 10,
    gap: 8,
  },
  walletLabel: {
    fontSize: 11,
    color: Colors.textSecondary,
    width: 72,
  },
  walletBarBg: {
    flex: 1,
    height: 8,
    borderRadius: 4,
    backgroundColor: Colors.border,
    overflow: 'hidden',
  },
  walletBarFill: {
    height: 8,
    borderRadius: 4,
  },
  walletAmount: {
    fontSize: 11,
    fontWeight: '600' as const,
    color: Colors.textPrimary,
    width: 80,
    textAlign: 'right',
  },
  newMissionCard: {
    backgroundColor: Colors.white,
    borderRadius: 16,
    padding: 18,
    borderLeftWidth: 4,
    borderLeftColor: Colors.danger,
  },
  newMissionHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 10,
  },
  urgentBadge: {
    backgroundColor: Colors.danger + '15',
    paddingHorizontal: 10,
    paddingVertical: 3,
    borderRadius: 8,
  },
  urgentText: {
    fontSize: 11,
    color: Colors.danger,
    fontWeight: '700' as const,
  },
  newMissionDistance: {
    fontSize: 12,
    color: Colors.textMuted,
  },
  newMissionTitle: {
    fontSize: 16,
    fontWeight: '600' as const,
    color: Colors.textPrimary,
    marginBottom: 4,
  },
  newMissionClient: {
    fontSize: 13,
    color: Colors.accent,
    marginBottom: 6,
  },
  newMissionDesc: {
    fontSize: 13,
    color: Colors.textSecondary,
    lineHeight: 18,
    marginBottom: 16,
  },
  actionRow: {
    flexDirection: 'row',
    gap: 10,
  },
  declineBtn: {
    flex: 1,
    paddingVertical: 12,
    borderRadius: 12,
    borderWidth: 1.5,
    borderColor: Colors.border,
    alignItems: 'center',
  },
  declineBtnText: {
    fontSize: 14,
    fontWeight: '600' as const,
    color: Colors.textSecondary,
  },
  acceptBtn: {
    flex: 1,
    paddingVertical: 12,
    borderRadius: 12,
    backgroundColor: Colors.success,
    alignItems: 'center',
  },
  acceptBtnText: {
    fontSize: 14,
    fontWeight: '600' as const,
    color: Colors.white,
  },
  quickActions: {
    flexDirection: 'row',
    gap: 12,
  },
  quickAction: {
    flex: 1,
    backgroundColor: Colors.white,
    borderRadius: 14,
    padding: 16,
    alignItems: 'center',
    gap: 8,
  },
  quickActionIcon: {
    width: 44,
    height: 44,
    borderRadius: 22,
    justifyContent: 'center',
    alignItems: 'center',
  },
  quickActionText: {
    fontSize: 12,
    fontWeight: '600' as const,
    color: Colors.textPrimary,
  },
  supplierHero: {
    backgroundColor: Colors.success,
    paddingHorizontal: 20,
    paddingBottom: 30,
    borderBottomLeftRadius: 24,
    borderBottomRightRadius: 24,
  },
  supplierTitle: {
    fontSize: 24,
    fontWeight: '700' as const,
    color: Colors.white,
    marginBottom: 6,
  },
  supplierSubtitle: {
    fontSize: 14,
    color: 'rgba(255,255,255,0.7)',
  },
  scannerArea: {
    marginHorizontal: 20,
    marginTop: 24,
    aspectRatio: 1,
    backgroundColor: Colors.white,
    borderRadius: 20,
    justifyContent: 'center',
    alignItems: 'center',
    borderWidth: 2,
    borderColor: Colors.success + '30',
    overflow: 'hidden',
  },
  scannerFrame: {
    alignItems: 'center',
    gap: 16,
  },
  scannerText: {
    fontSize: 15,
    color: Colors.textSecondary,
    fontWeight: '500' as const,
  },
  scannerCornerTL: {
    position: 'absolute',
    top: 16,
    left: 16,
    width: 30,
    height: 30,
    borderTopWidth: 3,
    borderLeftWidth: 3,
    borderColor: Colors.success,
    borderTopLeftRadius: 8,
  },
  scannerCornerTR: {
    position: 'absolute',
    top: 16,
    right: 16,
    width: 30,
    height: 30,
    borderTopWidth: 3,
    borderRightWidth: 3,
    borderColor: Colors.success,
    borderTopRightRadius: 8,
  },
  scannerCornerBL: {
    position: 'absolute',
    bottom: 16,
    left: 16,
    width: 30,
    height: 30,
    borderBottomWidth: 3,
    borderLeftWidth: 3,
    borderColor: Colors.success,
    borderBottomLeftRadius: 8,
  },
  scannerCornerBR: {
    position: 'absolute',
    bottom: 16,
    right: 16,
    width: 30,
    height: 30,
    borderBottomWidth: 3,
    borderRightWidth: 3,
    borderColor: Colors.success,
    borderBottomRightRadius: 8,
  },
  manualCodeRow: {
    flexDirection: 'row',
    gap: 10,
  },
  codeInput: {
    flex: 1,
    backgroundColor: Colors.white,
    borderRadius: 14,
    paddingHorizontal: 16,
    paddingVertical: 14,
    fontSize: 18,
    fontWeight: '600' as const,
    color: Colors.textPrimary,
    letterSpacing: 2,
    textAlign: 'center',
  },
  validateCodeBtn: {
    backgroundColor: Colors.success,
    borderRadius: 14,
    paddingHorizontal: 24,
    justifyContent: 'center',
  },
  validateCodeText: {
    fontSize: 15,
    fontWeight: '600' as const,
    color: Colors.white,
  },
  recentTransaction: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: Colors.white,
    borderRadius: 12,
    padding: 14,
    marginBottom: 8,
    gap: 10,
  },
  txStatusDot: {
    width: 10,
    height: 10,
    borderRadius: 5,
  },
  txInfo: {
    flex: 1,
  },
  txCode: {
    fontSize: 14,
    fontWeight: '700' as const,
    color: Colors.textPrimary,
  },
  txArtisan: {
    fontSize: 12,
    color: Colors.textSecondary,
    marginTop: 1,
  },
  txAmount: {
    fontSize: 14,
    fontWeight: '700' as const,
    color: Colors.textPrimary,
  },
  gpsWarning: {
    marginRight: 4,
  },
  loadingContainer: {
    flex: 1,
    backgroundColor: Colors.primary,
    justifyContent: 'center',
    alignItems: 'center',
  },
  loadingText: {
    fontSize: 28,
    fontWeight: '700' as const,
    color: Colors.white,
  },
});
