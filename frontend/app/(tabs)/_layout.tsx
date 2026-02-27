import React from 'react';
import { Tabs } from 'expo-router';
import { Home, Briefcase, Bell, Settings, ScanLine, BarChart3 } from 'lucide-react-native';
import { Colors } from '@/constants/colors';
import { useUser } from '@/contexts/UserContext';

export default function TabLayout() {
  const { role } = useUser();

  return (
    <Tabs
      screenOptions={{
        headerShown: false,
        tabBarActiveTintColor: role === 'fournisseur' ? Colors.success : role === 'artisan' ? Colors.accent : Colors.primary,
        tabBarInactiveTintColor: Colors.textMuted,
        tabBarStyle: {
          backgroundColor: Colors.white,
          borderTopColor: Colors.border,
          borderTopWidth: 1,
        },
        tabBarLabelStyle: {
          fontSize: 11,
          fontWeight: '600',
        },
      }}
    >
      <Tabs.Screen
        name="(home)"
        options={{
          title: role === 'artisan' ? 'Tableau de bord' : role === 'fournisseur' ? 'Scanner' : 'Accueil',
          tabBarIcon: ({ color, size }) =>
            role === 'fournisseur' ? <ScanLine size={size} color={color} /> : <Home size={size} color={color} />,
        }}
      />
      <Tabs.Screen
        name="missions"
        options={{
          title: role === 'fournisseur' ? 'Transactions' : 'Missions',
          tabBarIcon: ({ color, size }) =>
            role === 'fournisseur' ? <BarChart3 size={size} color={color} /> : <Briefcase size={size} color={color} />,
        }}
      />
      <Tabs.Screen
        name="notifications"
        options={{
          title: 'Alertes',
          tabBarIcon: ({ color, size }) => <Bell size={size} color={color} />,
        }}
      />
      <Tabs.Screen
        name="settings"
        options={{
          title: 'Paramètres',
          tabBarIcon: ({ color, size }) => <Settings size={size} color={color} />,
        }}
      />
    </Tabs>
  );
}
