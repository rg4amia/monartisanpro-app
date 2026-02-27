import React from 'react';
import { View, Text, StyleSheet, ScrollView, TouchableOpacity } from 'react-native';
import { Banknote, CheckCircle, AlertTriangle, AlertOctagon } from 'lucide-react-native';
import { Colors } from '@/constants/colors';
import { notificationsList } from '@/mocks/data';
import type { NotificationItem } from '@/mocks/data';

const iconMap = {
  payment: { icon: Banknote, color: Colors.success },
  validation: { icon: CheckCircle, color: Colors.primary },
  alert: { icon: AlertTriangle, color: Colors.warning },
  litige: { icon: AlertOctagon, color: Colors.danger },
};

export default function NotificationsScreen() {
  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
      {notificationsList.map((notif) => {
        const config = iconMap[notif.type];
        const Icon = config.icon;
        return (
          <TouchableOpacity
            key={notif.id}
            style={[styles.notifCard, !notif.read && styles.notifCardUnread]}
            activeOpacity={0.7}
          >
            <View style={[styles.iconCircle, { backgroundColor: config.color + '15' }]}>
              <Icon size={20} color={config.color} />
            </View>
            <View style={styles.notifContent}>
              <View style={styles.notifHeader}>
                <Text style={styles.notifTitle}>{notif.title}</Text>
                {!notif.read && <View style={styles.unreadDot} />}
              </View>
              <Text style={styles.notifMessage} numberOfLines={2}>{notif.message}</Text>
              <Text style={styles.notifDate}>{notif.date}</Text>
            </View>
          </TouchableOpacity>
        );
      })}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: Colors.background,
  },
  content: {
    padding: 20,
    paddingBottom: 40,
  },
  notifCard: {
    flexDirection: 'row',
    backgroundColor: Colors.white,
    borderRadius: 14,
    padding: 14,
    marginBottom: 8,
    gap: 12,
    alignItems: 'flex-start',
  },
  notifCardUnread: {
    borderLeftWidth: 3,
    borderLeftColor: Colors.accent,
  },
  iconCircle: {
    width: 42,
    height: 42,
    borderRadius: 21,
    justifyContent: 'center',
    alignItems: 'center',
    marginTop: 2,
  },
  notifContent: {
    flex: 1,
  },
  notifHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  notifTitle: {
    fontSize: 15,
    fontWeight: '600' as const,
    color: Colors.textPrimary,
  },
  unreadDot: {
    width: 8,
    height: 8,
    borderRadius: 4,
    backgroundColor: Colors.accent,
  },
  notifMessage: {
    fontSize: 13,
    color: Colors.textSecondary,
    lineHeight: 19,
    marginTop: 4,
  },
  notifDate: {
    fontSize: 11,
    color: Colors.textMuted,
    marginTop: 6,
  },
});
