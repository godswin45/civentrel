import { Badge } from '@/src/components/ui/Badge';
import { IconSymbol } from '@/src/components/ui/icon-symbol';
import { useTheme } from '@/src/context/ThemeContext';
import { AuthService } from '@/src/services/auth-service';
import { useLocalSearchParams, useRouter } from 'expo-router';
import React, { useState } from 'react';
import {
    Modal,
    ScrollView,
    Text,
    TextInput,
    TouchableOpacity,
    View
} from 'react-native';
import { styles } from './styles/ServicesCatalogScreen.styles';

export interface ServiceCatalogItem {
  id: string;
  title: string;
  category: 'EDUCATION' | 'BARANGAY' | 'BUSINESS' | 'TREASURY' | 'HEALTH' | 'SOCIAL' | 'DISASTER' | 'HOUSING' | 'TRANSPORT' | 'FACILITIES';
  description: string;
  iconName: string;
  iconBg: string;
  iconColor: string;
  badgeLabel: string;
  badgeVariant: 'info' | 'success' | 'warning' | 'danger' | 'neutral';
  route: string;
}

const CATEGORY_LABELS: Record<string, string> = {
  ALL: 'All Services',
  DISASTER: 'Disaster & Emergency',
  HOUSING: 'Zoning & Housing',
  TRANSPORT: 'Transport & Mobility',
  FACILITIES: 'Public Facilities',
  EDUCATION: 'Education',
  BARANGAY: 'Barangay',
  BUSINESS: 'Business',
  TREASURY: 'Treasury & RPT',
  HEALTH: 'Health',
  SOCIAL: 'Social Welfare',
};

const CATEGORIES = [
  'ALL',
  'DISASTER',
  'HOUSING',
  'TRANSPORT',
  'FACILITIES',
  'EDUCATION',
  'BARANGAY',
  'BUSINESS',
  'TREASURY',
  'HEALTH',
  'SOCIAL',
] as const;

const SERVICES_CATALOG: ServiceCatalogItem[] = [
  {
    id: 'SVC-RPT',
    title: 'Real Property Tax',
    category: 'TREASURY',
    description: 'Real property tax payments and related local assessment collection.',
    iconName: 'building.columns.fill',
    iconBg: '#E0F2FE',
    iconColor: '#0369A1',
    badgeLabel: 'RPT',
    badgeVariant: 'info',
    route: '/treasury?taxType=Real%20Property%20Tax',
  },
  {
    id: 'SVC-BUSINESS-TAX',
    title: 'Business Tax & Fees',
    category: 'TREASURY',
    description: 'Business tax and fees payment through a secure digital payment form.',
    iconName: 'creditcard.fill',
    iconBg: '#E0F2FE',
    iconColor: '#0369A1',
    badgeLabel: 'BUSINESS',
    badgeVariant: 'info',
    route: '/treasury?taxType=Business%20Tax%20%26%20Fees',
  },
  {
    id: 'SVC-MARKET-STALL',
    title: 'Market Stall Rental',
    category: 'TREASURY',
    description: 'Market stall rental dues and business stall charges payment.',
    iconName: 'cart.fill',
    iconBg: '#DCFCE7',
    iconColor: '#15803D',
    badgeLabel: 'MARKET',
    badgeVariant: 'success',
    route: '/treasury?taxType=Market%20Stall%20Rental',
  },
  {
    id: 'SVC-COMMUNITY-TAX',
    title: 'Community Tax Certificate',
    category: 'TREASURY',
    description: 'Community tax certification processing and payment for official local tax documents.',
    iconName: 'doc.text.fill',
    iconBg: '#F5F3FF',
    iconColor: '#7C3AED',
    badgeLabel: 'CERTIFICATE',
    badgeVariant: 'neutral',
    route: '/treasury?taxType=Community%20Tax%20Certificate',
  },
  {
    id: 'SVC-GOVT-FEES',
    title: 'General government payment / miscellaneous fees',
    category: 'TREASURY',
    description: 'Miscellaneous public fees and general government payment transactions.',
    iconName: 'banknote.fill',
    iconBg: '#FEF3C7',
    iconColor: '#B45309',
    badgeLabel: 'MISC FEES',
    badgeVariant: 'warning',
    route: '/treasury?taxType=General%20government%20payment%20%2F%20miscellaneous%20fees',
  },
  {
    id: 'SVC-PERMIT-RENEWAL',
    title: 'Business permit renewal or retirement payment',
    category: 'TREASURY',
    description: 'Renewal and retirement-related permit payment processing using the online treasury flow.',
    iconName: 'briefcase.fill',
    iconBg: '#E0F2FE',
    iconColor: '#0F766E',
    badgeLabel: 'PERMIT',
    badgeVariant: 'info',
    route: '/treasury?taxType=Business%20permit%20renewal%20or%20retirement%20payment',
  },
  {
    id: 'SVC-BUSINESS-PERMIT-APP',
    title: 'Business Permit Application',
    category: 'BUSINESS',
    description: 'Apply for a new business permit, renew an existing one, or apply for retirement.',
    iconName: 'briefcase.fill',
    iconBg: '#E0F2FE',
    iconColor: '#0F766E',
    badgeLabel: 'PERMIT',
    badgeVariant: 'info',
    route: '/business',
  },
];

export function ServicesCatalogScreen() {
  const router = useRouter();
  const { isDarkMode } = useTheme();
  const params = useLocalSearchParams<{ isGuest?: string }>();

  // Detect guest mode via params OR session
  const session = AuthService.getCurrentUser();
  const isGuestMode = params.isGuest === 'true' || (!session.email && !session.citizen_user_id);

  const [searchQuery, setSearchQuery] = useState('');
  const [selectedCategory, setSelectedCategory] = useState<typeof CATEGORIES[number]>('ALL');
  const [isAuthGateVisible, setIsAuthGateVisible] = useState(false);

  const filteredServices = SERVICES_CATALOG.filter((item) => {
    const matchesCat = selectedCategory === 'ALL' || item.category === selectedCategory;
    const query = searchQuery.trim().toLowerCase();
    const matchesQuery =
      query === '' ||
      item.title.toLowerCase().includes(query) ||
      item.description.toLowerCase().includes(query);

    return matchesCat && matchesQuery;
  });

  const handleServicePress = (route: string) => {
    if (isGuestMode) {
      setIsAuthGateVisible(true);
      return;
    }
    router.push(route as any);
  };

  return (
    <View style={[styles.container, isDarkMode && { backgroundColor: '#0B132B' }]}>
      <ScrollView
        contentContainerStyle={styles.scrollContent}
        showsVerticalScrollIndicator={false}>
        {/* Header Title */}
        <View style={styles.headerContainer}>
          <Text style={[styles.headerTitle, isDarkMode && { color: '#F8FAFC' }]}>Municipal Services Directory</Text>
          <Text style={[styles.headerSubtitle, isDarkMode && { color: '#94A3B8' }]}>
            Access official Caloocan City government e-services, permits, education grants & digital clearance.
          </Text>
        </View>

        {/* Search Bar */}
        <View style={[styles.searchBox, isDarkMode && { backgroundColor: '#1C2541', borderColor: '#3A506B' }]}>
          <IconSymbol name="magnifyingglass" size={18} color={isDarkMode ? '#94A3B8' : '#64748B'} />
          <TextInput
            style={[styles.searchInput, isDarkMode && { color: '#F8FAFC' }]}
            placeholder="Search municipal service, scholarship or permit..."
            placeholderTextColor={isDarkMode ? '#64748B' : '#94A3B8'}
            value={searchQuery}
            onChangeText={setSearchQuery}
          />
          {searchQuery.length > 0 ? (
            <TouchableOpacity onPress={() => setSearchQuery('')} style={styles.clearSearchBtn}>
              <Text style={styles.clearSearchText}>âœ•</Text>
            </TouchableOpacity>
          ) : null}
        </View>

        <View style={styles.categoryScroll} />

        {/* Guest Warning Banner */}
        {isGuestMode && (
          <View style={styles.guestWarningBanner}>
            <IconSymbol name="lock.fill" size={14} color="#B45309" />
            <Text style={styles.guestWarningText}>
              {' '}Sign in or register to access municipal e-services.
            </Text>
          </View>
        )}

        {/* Services List */}
        <View style={styles.servicesList}>
          {filteredServices.map((service) => (
            <TouchableOpacity
              key={service.id}
              style={[
                styles.serviceCard,
                isDarkMode && { backgroundColor: '#1C2541', borderColor: '#3A506B' },
                isGuestMode && styles.serviceCardLocked
              ]}
              onPress={() => handleServicePress(service.route)}
              activeOpacity={0.85}>
              <View style={styles.cardHeaderRow}>
                <View style={[styles.iconCircle, { backgroundColor: isDarkMode ? '#0F2942' : service.iconBg }]}>
                  <IconSymbol name={service.iconName as any} size={22} color={isDarkMode ? '#38BDF8' : service.iconColor} />
                </View>
                <View style={styles.badgeRow}>
                  <Badge label={service.badgeLabel} variant={service.badgeVariant} />
                  {isGuestMode && (
                    <View style={styles.lockBadge}>
                      <IconSymbol name="lock.fill" size={11} color="#94A3B8" />
                    </View>
                  )}
                </View>
              </View>

              <Text style={[styles.serviceTitle, isDarkMode && { color: '#F8FAFC' }]}>{service.title}</Text>
              <Text style={[styles.serviceSub, isDarkMode && { color: '#CBD5E1' }]}>{service.description}</Text>

              <View style={styles.cardFooterRow}>
                <Text style={[styles.launchText, isDarkMode && { color: '#38BDF8' }, isGuestMode && styles.launchTextLocked]}>
                  {isGuestMode ? 'Login Required' : 'Open E-Service'}
                </Text>
                <IconSymbol name={isGuestMode ? 'lock.fill' : 'chevron.right'} size={14} color={isGuestMode ? '#94A3B8' : isDarkMode ? '#38BDF8' : '#176B87'} />
              </View>
            </TouchableOpacity>
          ))}
        </View>
      </ScrollView>

      {/* AUTH GATE MODAL */}
      <Modal
        visible={isAuthGateVisible}
        transparent
        animationType="slide"
        onRequestClose={() => setIsAuthGateVisible(false)}>
        <View style={styles.authGateOverlay}>
          <View style={[styles.authGateCard, isDarkMode && { backgroundColor: '#1C2541', borderColor: '#3A506B', borderWidth: 1 }]}>
            {/* Icon Ring */}
            <View style={[styles.authGateIconRing, isDarkMode && { backgroundColor: '#0F2942' }]}>
              <IconSymbol name="lock.shield.fill" size={34} color={isDarkMode ? '#38BDF8' : '#165B7E'} />
            </View>

            {/* Title */}
            <Text style={[styles.authGateTitle, isDarkMode && { color: '#F8FAFC' }]}>Sign In Required</Text>
            <Text style={[styles.authGateSub, isDarkMode && { color: '#CBD5E1' }]}>
              This municipal e-service is only accessible to registered Caloocan City citizens. Please sign in to continue.
            </Text>

            {/* Divider with city branding */}
            <View style={styles.authGateBrandRow}>
              <View style={[styles.authGateBrandLine, isDarkMode && { backgroundColor: '#3A506B' }]} />
              <Text style={[styles.authGateBrandText, isDarkMode && { color: '#94A3B8' }]}>CALOOCAN CITY GOVERNMENT</Text>
              <View style={[styles.authGateBrandLine, isDarkMode && { backgroundColor: '#3A506B' }]} />
            </View>

            {/* Buttons */}
            <View style={styles.authGateActions}>
              <TouchableOpacity
                style={styles.authGateLoginBtn}
                onPress={() => {
                  setIsAuthGateVisible(false);
                  router.replace('/(auth)/login' as any);
                }}
                activeOpacity={0.88}>
                <IconSymbol name="person.fill" size={16} color="#FFFFFF" />
                <Text style={styles.authGateLoginText}>Sign In to My Account</Text>
              </TouchableOpacity>

              <TouchableOpacity
                style={[styles.authGateCancelBtn, isDarkMode && { backgroundColor: '#334155', borderColor: '#475569' }]}
                onPress={() => setIsAuthGateVisible(false)}
                activeOpacity={0.7}>
                <Text style={[styles.authGateCancelText, isDarkMode && { color: '#F8FAFC' }]}>Continue Browsing as Guest</Text>
              </TouchableOpacity>
            </View>

            {/* Footer */}
            <View style={styles.authGateFooter}>
              <IconSymbol name="shield.fill" size={11} color="#94A3B8" />
              <Text style={[styles.authGateFooterText, isDarkMode && { color: '#94A3B8' }]}>  Protected by Caloocan City E-Governance Portal</Text>
            </View>
          </View>
        </View>
      </Modal>
    </View>
  );
}
