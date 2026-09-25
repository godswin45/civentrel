import { useState } from 'react';
import { Alert } from 'react-native';
import * as WebBrowser from 'expo-web-browser';

// Replace with your actual live server URL or ngrok URL
const API_URL = 'https://civentral.tech/api/citizen/treasury/payments.php';

export interface PaymentPayload {
  taxpayer_name: string;
  account_number: string;
  email: string;
  payment_type: string;
  amount: number;
  payment_method: string;
  source_module: string;
  application_id: string;
  citizen_user_id: number;
}

export const usePaymentService = () => {
  const [isProcessing, setIsProcessing] = useState(false);

  const processPayment = async (payload: PaymentPayload) => {
    setIsProcessing(true);
    try {
      // 1. Send the data to your backend API
      const response = await fetch(API_URL, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify(payload)
      });

      const data = await response.json();

      if (data.status === 'success' && data.checkout_url) {
        // 2. Open PayMongo Checkout safely inside the app
        const result = await WebBrowser.openBrowserAsync(data.checkout_url);
        
        // After the user closes the browser, you can optionally check the status
        // or just rely on your backend webhooks to update the dashboard.
        return true;
      } else {
        Alert.alert('Payment Error', data.message || 'Failed to generate checkout link');
        return false;
      }
    } catch (error) {
      console.error(error);
      Alert.alert('Network Error', 'Could not connect to the payment server.');
      return false;
    } finally {
      setIsProcessing(false);
    }
  };

  return { processPayment, isProcessing };
};
