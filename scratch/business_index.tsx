import React, { useState, useEffect } from 'react';
import { ScrollView, StyleSheet, Text, View, TextInput, TouchableOpacity, Alert } from 'react-native';
import { IconSymbol } from '@/src/components/ui/icon-symbol';
import { useTheme } from '@/src/context/ThemeContext';
import { AuthService } from '@/src/services/auth-service';
import { ProfileService } from '@/src/services/profile-service';

export default function BusinessIndexRoute() {
  const { isDarkMode } = useTheme();
  const dm = isDarkMode;

  const [tab, setTab] = useState<'renewal' | 'retirement'>('renewal');
  
  // Form State
  const [ownerName, setOwnerName] = useState('');
  const [email, setEmail] = useState('');
  const [barangay, setBarangay] = useState('');
  
  const [businessName, setBusinessName] = useState('');
  const [lineOfBusiness, setLineOfBusiness] = useState('');
  const [permitNo, setPermitNo] = useState('');
  
  const [isSubmitting, setIsSubmitting] = useState(false);

  useEffect(() => {
    // Auto-fill from profile
    const user = AuthService.getCurrentUser();
    if (user.citizen_user_id || user.email) {
      ProfileService.getProfile(user.email, user.citizen_user_id).then(res => {
        if (res.status === 'success' && res.data) {
          setOwnerName(res.data.fullName || '');
          setEmail(res.data.email || '');
          setBarangay(res.data.barangay || '');
        }
      });
    }
  }, []);

  const handleSubmit = () => {
    if (!businessName || !lineOfBusiness) {
      Alert.alert('Error', 'Please fill in all required business details.');
      return;
    }
    
    setIsSubmitting(true);
    setTimeout(() => {
      setIsSubmitting(false);
      Alert.alert('Success', 'Business Permit Application submitted successfully! Your tracking number is BA-2026-9912X.');
      setBusinessName('');
      setLineOfBusiness('');
      setPermitNo('');
    }, 1500);
  };

  const docs = tab === 'renewal' 
    ? ['Barangay Clearance', 'Previous Mayor\'s Permit', 'Latest Gross Receipt', 'DTI/SEC/CDA Registration']
    : ['Affidavit of Closure', 'Barangay Clearance', 'Previous Mayor\'s Permit', 'Latest Gross Receipt'];

  return (
    <View style={[styles.safeArea, { backgroundColor: dm ? '#0B132B' : '#F8FAFC' }]}>
      <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        
        <View style={[styles.heroCard, { backgroundColor: dm ? '#1C2541' : '#FFFFFF', borderColor: dm ? '#3A506B' : '#E2E8F0' }]}>
          <View style={styles.heroBadgeRow}>
            <View style={[styles.iconWrap, { backgroundColor: dm ? '#0F2942' : '#DBEAFE' }]}>
              <IconSymbol name="briefcase.fill" size={24} color={dm ? '#60A5FA' : '#2563EB'} />
            </View>
            <Text style={[styles.heroBadge, { color: dm ? '#60A5FA' : '#2563EB' }]}>Business Permits</Text>
          </View>
          <Text style={[styles.heroTitle, { color: dm ? '#F8FAFC' : '#0F172A' }]}>Application Portal</Text>
          <Text style={[styles.heroSubtitle, { color: dm ? '#CBD5E1' : '#64748B' }]}>
            Apply for business permit renewal or retirement online. Upload required documents directly from your phone.
          </Text>
        </View>

        <View style={styles.tabContainer}>
          <TouchableOpacity 
            style={[styles.tab, tab === 'renewal' && styles.activeTab]} 
            onPress={() => setTab('renewal')}>
            <Text style={[styles.tabText, tab === 'renewal' ? styles.activeTabText : { color: dm ? '#94A3B8' : '#64748B' }]}>Renewal</Text>
          </TouchableOpacity>
          <TouchableOpacity 
            style={[styles.tab, tab === 'retirement' && styles.activeTab]} 
            onPress={() => setTab('retirement')}>
            <Text style={[styles.tabText, tab === 'retirement' ? styles.activeTabText : { color: dm ? '#94A3B8' : '#64748B' }]}>Retirement</Text>
          </TouchableOpacity>
        </View>

        <View style={[styles.formCard, { backgroundColor: dm ? '#1C2541' : '#FFFFFF', borderColor: dm ? '#3A506B' : '#E2E8F0' }]}>
          <Text style={[styles.sectionTitle, { color: dm ? '#F8FAFC' : '#0F172A' }]}>
            {tab === 'renewal' ? 'Renewal Details' : 'Retirement Details'}
          </Text>

          <View style={styles.fieldGroup}>
            <Text style={[styles.label, { color: dm ? '#CBD5E1' : '#475569' }]}>Business Name</Text>
            <TextInput
              value={businessName}
              onChangeText={setBusinessName}
              placeholder="e.g. Dela Cruz Store"
              placeholderTextColor={dm ? '#64748B' : '#94A3B8'}
              style={[styles.input, { color: dm ? '#F8FAFC' : '#0F172A', backgroundColor: dm ? '#0B132B' : '#F8FAFC', borderColor: dm ? '#3A506B' : '#CBD5E1' }]}
            />
          </View>

          <View style={styles.fieldGroup}>
            <Text style={[styles.label, { color: dm ? '#CBD5E1' : '#475569' }]}>Line of Business</Text>
            <TextInput
              value={lineOfBusiness}
              onChangeText={setLineOfBusiness}
              placeholder="e.g. Retail trade"
              placeholderTextColor={dm ? '#64748B' : '#94A3B8'}
              style={[styles.input, { color: dm ? '#F8FAFC' : '#0F172A', backgroundColor: dm ? '#0B132B' : '#F8FAFC', borderColor: dm ? '#3A506B' : '#CBD5E1' }]}
            />
          </View>

          {tab === 'renewal' && (
            <View style={styles.fieldGroup}>
              <Text style={[styles.label, { color: dm ? '#CBD5E1' : '#475569' }]}>Previous Permit No.</Text>
              <TextInput
                value={permitNo}
                onChangeText={setPermitNo}
                placeholder="e.g. BP-2025-001234"
                placeholderTextColor={dm ? '#64748B' : '#94A3B8'}
                style={[styles.input, { color: dm ? '#F8FAFC' : '#0F172A', backgroundColor: dm ? '#0B132B' : '#F8FAFC', borderColor: dm ? '#3A506B' : '#CBD5E1' }]}
              />
            </View>
          )}

          <Text style={[styles.sectionTitle, { color: dm ? '#F8FAFC' : '#0F172A', marginTop: 10 }]}>Owner Information</Text>

          <View style={styles.fieldGroup}>
            <Text style={[styles.label, { color: dm ? '#CBD5E1' : '#475569' }]}>Owner Name</Text>
            <TextInput
              value={ownerName}
              editable={false}
              style={[styles.input, { color: dm ? '#94A3B8' : '#64748B', backgroundColor: dm ? '#1E293B' : '#F1F5F9', borderColor: dm ? '#334155' : '#E2E8F0' }]}
            />
          </View>

          <View style={styles.fieldGroup}>
            <Text style={[styles.label, { color: dm ? '#CBD5E1' : '#475569' }]}>Email Address</Text>
            <TextInput
              value={email}
              editable={false}
              style={[styles.input, { color: dm ? '#94A3B8' : '#64748B', backgroundColor: dm ? '#1E293B' : '#F1F5F9', borderColor: dm ? '#334155' : '#E2E8F0' }]}
            />
          </View>

          <View style={styles.fieldGroup}>
            <Text style={[styles.label, { color: dm ? '#CBD5E1' : '#475569' }]}>Barangay</Text>
            <TextInput
              value={barangay}
              editable={false}
              style={[styles.input, { color: dm ? '#94A3B8' : '#64748B', backgroundColor: dm ? '#1E293B' : '#F1F5F9', borderColor: dm ? '#334155' : '#E2E8F0' }]}
            />
          </View>
          
          <Text style={[styles.sectionTitle, { color: dm ? '#F8FAFC' : '#0F172A', marginTop: 10 }]}>Required Documents</Text>
          <View style={styles.docsContainer}>
            {docs.map((doc, idx) => (
              <TouchableOpacity key={idx} style={[styles.docItem, { backgroundColor: dm ? '#0B132B' : '#F8FAFC', borderColor: dm ? '#3A506B' : '#E2E8F0' }]}>
                <IconSymbol name="doc.fill" size={16} color={dm ? '#94A3B8' : '#64748B'} />
                <Text style={[styles.docText, { color: dm ? '#CBD5E1' : '#475569' }]}>{doc}</Text>
                <IconSymbol name="plus.circle.fill" size={18} color="#2563EB" />
              </TouchableOpacity>
            ))}
          </View>

          <TouchableOpacity style={styles.submitButton} onPress={handleSubmit} disabled={isSubmitting}>
            <Text style={styles.submitButtonText}>{isSubmitting ? 'Submitting...' : 'Submit Application'}</Text>
          </TouchableOpacity>
        </View>

      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  safeArea: { flex: 1 },
  content: { padding: 16, paddingBottom: 110, gap: 16 },
  heroCard: { borderRadius: 22, borderWidth: 1, padding: 18 },
  heroBadgeRow: { flexDirection: 'row', alignItems: 'center', gap: 8, marginBottom: 10 },
  iconWrap: { width: 42, height: 42, borderRadius: 14, alignItems: 'center', justifyContent: 'center' },
  heroBadge: { fontSize: 12, fontWeight: '800', letterSpacing: 0.5, textTransform: 'uppercase' },
  heroTitle: { fontSize: 24, fontWeight: '800', marginBottom: 6 },
  heroSubtitle: { fontSize: 13, lineHeight: 18, marginBottom: 14 },
  tabContainer: { flexDirection: 'row', borderBottomWidth: 1, borderColor: '#E2E8F0', paddingHorizontal: 10 },
  tab: { flex: 1, paddingVertical: 12, alignItems: 'center', borderBottomWidth: 2, borderColor: 'transparent' },
  activeTab: { borderColor: '#2563EB' },
  tabText: { fontSize: 14, fontWeight: '700' },
  activeTabText: { color: '#2563EB' },
  formCard: { borderRadius: 20, borderWidth: 1, padding: 16 },
  sectionTitle: { fontSize: 16, fontWeight: '800', marginBottom: 14 },
  fieldGroup: { marginBottom: 14 },
  label: { fontSize: 12, fontWeight: '700', marginBottom: 8 },
  input: { borderWidth: 1, borderRadius: 12, minHeight: 46, paddingHorizontal: 12, paddingVertical: 12, fontSize: 14 },
  docsContainer: { gap: 10, marginBottom: 20 },
  docItem: { flexDirection: 'row', alignItems: 'center', padding: 14, borderRadius: 12, borderWidth: 1, gap: 10 },
  docText: { flex: 1, fontSize: 13, fontWeight: '600' },
  submitButton: { backgroundColor: '#2563EB', borderRadius: 12, height: 50, alignItems: 'center', justifyContent: 'center', marginTop: 10 },
  submitButtonText: { color: '#FFFFFF', fontSize: 15, fontWeight: '800' },
});
