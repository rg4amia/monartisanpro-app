import React from 'react';
import { View, Text, StyleSheet, TouchableOpacity } from 'react-native';
import { Image } from 'expo-image';
import { Star, MapPin } from 'lucide-react-native';
import { Colors } from '@/constants/colors';
import { formatFCFA } from '@/mocks/data';
import type { Artisan } from '@/mocks/data';
import ScoreNzassa from './ScoreNzassa';

interface ArtisanCardProps {
  artisan: Artisan;
  onPress?: () => void;
}

export default function ArtisanCard({ artisan, onPress }: ArtisanCardProps) {
  return (
    <TouchableOpacity
      style={styles.card}
      onPress={onPress}
      activeOpacity={0.7}
      testID={`artisan-card-${artisan.id}`}
    >
      <Image source={{ uri: artisan.photo }} style={styles.photo} />
      <View style={styles.info}>
        <Text style={styles.name} numberOfLines={1}>{artisan.name}</Text>
        <Text style={styles.trade}>{artisan.trade}</Text>
        <View style={styles.row}>
          <Star size={13} color="#F39C12" fill="#F39C12" />
          <Text style={styles.rating}>{artisan.rating}</Text>
          <Text style={styles.missions}>· {artisan.completedMissions} missions</Text>
        </View>
        <View style={styles.row}>
          <MapPin size={12} color={Colors.textMuted} />
          <Text style={styles.distance}>{artisan.distance}</Text>
        </View>
      </View>
      <ScoreNzassa score={artisan.scoreNzassa} size="small" />
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  card: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: Colors.white,
    borderRadius: 14,
    padding: 14,
    marginBottom: 10,
    shadowColor: Colors.primary,
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.06,
    shadowRadius: 8,
    elevation: 2,
  },
  photo: {
    width: 52,
    height: 52,
    borderRadius: 26,
    backgroundColor: Colors.border,
  },
  info: {
    flex: 1,
    marginLeft: 12,
  },
  name: {
    fontSize: 15,
    fontWeight: '600' as const,
    color: Colors.textPrimary,
  },
  trade: {
    fontSize: 13,
    color: Colors.accent,
    fontWeight: '500' as const,
    marginTop: 1,
  },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    marginTop: 3,
    gap: 4,
  },
  rating: {
    fontSize: 12,
    fontWeight: '600' as const,
    color: Colors.textPrimary,
  },
  missions: {
    fontSize: 12,
    color: Colors.textSecondary,
  },
  distance: {
    fontSize: 11,
    color: Colors.textMuted,
  },
});
