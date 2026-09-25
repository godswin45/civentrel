import React from 'react';
import { TouchableOpacity, Text, StyleSheet, ActivityIndicator } from 'react-native';
import { usePaymentService, PaymentPayload } from './usePaymentService';

interface PaymentButtonProps {
  payload: PaymentPayload;
  onSuccess?: () => void;
  buttonText?: string;
}

export const PaymentButton: React.FC<PaymentButtonProps> = ({ 
  payload, 
  onSuccess,
  buttonText = "Pay Now" 
}) => {
  const { processPayment, isProcessing } = usePaymentService();

  const handlePress = async () => {
    const success = await processPayment(payload);
    if (success && onSuccess) {
      onSuccess();
    }
  };

  return (
    <TouchableOpacity 
      style={[styles.button, isProcessing && styles.buttonDisabled]} 
      onPress={handlePress}
      disabled={isProcessing}
    >
      {isProcessing ? (
        <ActivityIndicator color="#ffffff" />
      ) : (
        <Text style={styles.buttonText}>{buttonText}</Text>
      )}
    </TouchableOpacity>
  );
};

const styles = StyleSheet.create({
  button: {
    backgroundColor: '#005b9f',
    paddingVertical: 15,
    paddingHorizontal: 20,
    borderRadius: 8,
    flexDirection: 'row',
    justifyContent: 'center',
    alignItems: 'center',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  buttonDisabled: {
    backgroundColor: '#8da6b8',
  },
  buttonText: {
    color: '#ffffff',
    fontSize: 16,
    fontWeight: 'bold',
  }
});
