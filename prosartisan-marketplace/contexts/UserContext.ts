import { useState, useEffect, useCallback } from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';
import createContextHook from '@nkzw/create-context-hook';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import type { UserRole } from '@/mocks/data';

interface UserState {
  isOnboarded: boolean;
  role: UserRole | null;
  phone: string | null;
  name: string | null;
}

const STORAGE_KEY = 'prosartisan_user';

export const [UserProvider, useUser] = createContextHook(() => {
  const queryClient = useQueryClient();
  const [user, setUser] = useState<UserState>({
    isOnboarded: false,
    role: null,
    phone: null,
    name: null,
  });

  const userQuery = useQuery({
    queryKey: ['user'],
    queryFn: async () => {
      const stored = await AsyncStorage.getItem(STORAGE_KEY);
      if (stored) {
        return JSON.parse(stored) as UserState;
      }
      return null;
    },
  });

  useEffect(() => {
    if (userQuery.data) {
      setUser(userQuery.data);
    }
  }, [userQuery.data]);

  const saveMutation = useMutation({
    mutationFn: async (newUser: UserState) => {
      await AsyncStorage.setItem(STORAGE_KEY, JSON.stringify(newUser));
      return newUser;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['user'] });
    },
  });

  const setRole = useCallback((role: UserRole) => {
    const updated = { ...user, role };
    setUser(updated);
    saveMutation.mutate(updated);
  }, [user, saveMutation]);

  const setPhone = useCallback((phone: string) => {
    const updated = { ...user, phone };
    setUser(updated);
    saveMutation.mutate(updated);
  }, [user, saveMutation]);

  const completeOnboarding = useCallback((name: string) => {
    const updated = { ...user, name, isOnboarded: true };
    setUser(updated);
    saveMutation.mutate(updated);
  }, [user, saveMutation]);

  const logout = useCallback(async () => {
    await AsyncStorage.removeItem(STORAGE_KEY);
    setUser({ isOnboarded: false, role: null, phone: null, name: null });
    queryClient.invalidateQueries({ queryKey: ['user'] });
  }, [queryClient]);

  return {
    ...user,
    isLoading: userQuery.isLoading,
    setRole,
    setPhone,
    completeOnboarding,
    logout,
  };
});
