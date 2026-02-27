import React from 'react';
import { View, Text, StyleSheet, ScrollView, TouchableOpacity } from 'react-native';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { Image } from 'expo-image';
import { Star, MapPin, Briefcase, MessageCircle, Phone } from 'lucide-react-native';
import { Colors } from '@/constants/colors';
import { artisans, getScoreColor } from '@/mocks/data';
import ScoreNzassa from '@/components/ScoreNzassa';

export default function ArtisanProfileScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const router = useRouter();
  const artisan = artisans.find(a => a.id === id) ?? artisans[0];
  const scoreColor = getScoreColor(artisan.scoreNzassa);

  return (
    <ScrollView style={styles.container} showsVerticalScrollIndicator={false}>
      <View style={styles.profileHeader}>
        <Image source={{ uri: artisan.photo }} style={styles.photo} />
        <Text style={styles.name}>{artisan.name}</Text>
        <Text style={styles.trade}>{artisan.trade}</Text>
        <View style={styles.locationRow}>
          <MapPin size={14} color={Colors.textMuted} />
          <Text style={styles.location}>{artisan.location}</Text>
        </View>
      </View>

      <View style={styles.statsContainer}>
        <View style={styles.statItem}>
          <ScoreNzassa score={artisan.scoreNzassa} size="medium" showLabel />
        </View>
        <View style={styles.statDivider} />
        <View style={styles.statItem}>
          <View style={styles.ratingRow}>
            <Star size={20} color="#F39C12" fill="#F39C12" />
            <Text style={styles.ratingText}>{artisan.rating}</Text>
          </View>
          <Text style={styles.statLabel}>Évaluation</Text>
        </View>
        <View style={styles.statDivider} />
        <View style={styles.statItem}>
          <Text style={styles.missionCount}>{artisan.completedMissions}</Text>
          <Text style={styles.statLabel}>Missions</Text>
        </View>
      </View>

      <View style={styles.actionButtons}>
        <TouchableOpacity style={styles.primaryBtn} activeOpacity={0.8}>
          <Phone size={18} color={Colors.white} />
          <Text style={styles.primaryBtnText}>Appeler</Text>
        </TouchableOpacity>
        <TouchableOpacity
          style={styles.secondaryBtn}
          activeOpacity={0.8}
          onPress={() => router.push('/(tabs)/(home)/mission-request')}
        >
          <MessageCircle size={18} color={Colors.accent} />
          <Text style={styles.secondaryBtnText}>Demander un devis</Text>
        </TouchableOpacity>
      </View>

      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Avis clients ({artisan.reviews.length})</Text>
        {artisan.reviews.map((review) => (
          <View key={review.id} style={styles.reviewCard}>
            <View style={styles.reviewHeader}>
              <Text style={styles.reviewName}>{review.clientName}</Text>
              <View style={styles.reviewStars}>
                {Array.from({ length: 5 }).map((_, i) => (
                  <Star
                    key={i}
                    size={13}
                    color={i < review.rating ? '#F39C12' : Colors.border}
                    fill={i < review.rating ? '#F39C12' : 'transparent'}
                  />
                ))}
              </View>
            </View>
            <Text style={styles.reviewComment}>{review.comment}</Text>
            <Text style={styles.reviewDate}>{review.date}</Text>
          </View>
        ))}
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: Colors.background,
  },
  profileHeader: {
    alignItems: 'center',
    paddingVertical: 24,
    backgroundColor: Colors.white,
  },
  photo: {
    width: 96,
    height: 96,
    borderRadius: 48,
    marginBottom: 14,
    backgroundColor: Colors.border,
  },
  name: {
    fontSize: 22,
    fontWeight: '700' as const,
    color: Colors.textPrimary,
  },
  trade: {
    fontSize: 15,
    color: Colors.accent,
    fontWeight: '600' as const,
    marginTop: 4,
  },
  locationRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    marginTop: 6,
  },
  location: {
    fontSize: 13,
    color: Colors.textMuted,
  },
  statsContainer: {
    flexDirection: 'row',
    backgroundColor: Colors.white,
    marginHorizontal: 20,
    marginTop: 16,
    borderRadius: 16,
    padding: 20,
    shadowColor: Colors.primary,
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.06,
    shadowRadius: 8,
    elevation: 2,
  },
  statItem: {
    flex: 1,
    alignItems: 'center',
  },
  statDivider: {
    width: 1,
    backgroundColor: Colors.border,
  },
  ratingRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
  },
  ratingText: {
    fontSize: 20,
    fontWeight: '700' as const,
    color: Colors.textPrimary,
  },
  missionCount: {
    fontSize: 22,
    fontWeight: '700' as const,
    color: Colors.textPrimary,
  },
  statLabel: {
    fontSize: 11,
    color: Colors.textMuted,
    marginTop: 4,
  },
  actionButtons: {
    flexDirection: 'row',
    paddingHorizontal: 20,
    marginTop: 16,
    gap: 10,
  },
  primaryBtn: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: Colors.primary,
    borderRadius: 14,
    paddingVertical: 14,
    gap: 8,
  },
  primaryBtnText: {
    fontSize: 15,
    fontWeight: '600' as const,
    color: Colors.white,
  },
  secondaryBtn: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: Colors.accent + '12',
    borderRadius: 14,
    paddingVertical: 14,
    gap: 8,
  },
  secondaryBtnText: {
    fontSize: 15,
    fontWeight: '600' as const,
    color: Colors.accent,
  },
  section: {
    paddingHorizontal: 20,
    marginTop: 24,
    paddingBottom: 20,
  },
  sectionTitle: {
    fontSize: 17,
    fontWeight: '700' as const,
    color: Colors.textPrimary,
    marginBottom: 14,
  },
  reviewCard: {
    backgroundColor: Colors.white,
    borderRadius: 14,
    padding: 16,
    marginBottom: 10,
  },
  reviewHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 8,
  },
  reviewName: {
    fontSize: 14,
    fontWeight: '600' as const,
    color: Colors.textPrimary,
  },
  reviewStars: {
    flexDirection: 'row',
    gap: 2,
  },
  reviewComment: {
    fontSize: 13,
    color: Colors.textSecondary,
    lineHeight: 20,
  },
  reviewDate: {
    fontSize: 11,
    color: Colors.textMuted,
    marginTop: 8,
  },
});
